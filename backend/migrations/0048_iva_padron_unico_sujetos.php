<?php

/**
 * Padrón Único de Sujetos IVA (clientes/proveedores), pedido del contador (22/07/2026,
 * ver documentacion/pedido-padron-unico-contribuyentes.md): una sola fila por CUIT
 * compartida por todas las empresas del tenant — antes `iva_clientes`/`iva_proveedores`
 * duplicaban identidad (nombre/domicilio/CUIT) por cada empresa. `cuenta_id`/`rubro_id`
 * de esas tablas ya estaban muertos (nada los lee: la imputación contable real vive en
 * `venta_discriminaciones`/`compra_discriminaciones` desde la migración 0043, y el rubro
 * F2002 en la cabecera de venta/compra) — no se migran, quedan afuera del nuevo modelo.
 *
 * Modelo nuevo:
 *  - `iva_sujetos`: padrón único por tenant (1 fila por CUIT).
 *  - `iva_sujeto_empresas`: activación por empresa (empresa+sujeto+rol cliente/proveedor),
 *    solo para armar el listado "Clientes"/"Proveedores" de cada empresa.
 *
 * La migración de datos (dedupe por tenant+CUIT) corre acá con PDO plano (sin clases
 * `App\`, como el resto de las migraciones), reapuntando `ventas.cliente_id`,
 * `compras.proveedor_id` y `actividad_receptor.cliente_id` a la nueva tabla.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS iva_sujetos (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id        CHAR(36)     NOT NULL,
            cuit             VARCHAR(13)  NOT NULL,
            nombre           VARCHAR(100) NOT NULL,
            domicilio        VARCHAR(100) DEFAULT NULL,
            localidad        VARCHAR(50)  DEFAULT NULL,
            cp               VARCHAR(8)   DEFAULT NULL,
            condicion_iva_id INT          DEFAULT NULL,
            provincia_id     INT          DEFAULT NULL,
            ingresos_brutos  VARCHAR(20)  DEFAULT NULL,
            telefono         VARCHAR(25)  DEFAULT NULL,
            cai              VARCHAR(15)  DEFAULT NULL,
            fecha_cai        DATE         DEFAULT NULL,
            cais             TEXT         DEFAULT NULL,
            CONSTRAINT fk_sujeto_tenant    FOREIGN KEY (tenant_id)        REFERENCES tenants(id)        ON DELETE CASCADE,
            CONSTRAINT fk_sujeto_condicion FOREIGN KEY (condicion_iva_id) REFERENCES condiciones_iva(id),
            CONSTRAINT fk_sujeto_provincia FOREIGN KEY (provincia_id)     REFERENCES provincias(id),
            UNIQUE KEY uq_sujeto_tenant_cuit (tenant_id, cuit),
            INDEX idx_sujeto_tenant (tenant_id)
        ) {$charset}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS iva_sujeto_empresas (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            sujeto_id  INT NOT NULL,
            rol        ENUM('cliente','proveedor') NOT NULL,
            activo     CHAR(1) NOT NULL DEFAULT 'S',
            CONSTRAINT fk_sujemp_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id)    ON DELETE CASCADE,
            CONSTRAINT fk_sujemp_sujeto  FOREIGN KEY (sujeto_id)  REFERENCES iva_sujetos(id) ON DELETE CASCADE,
            UNIQUE KEY uq_sujemp (empresa_id, sujeto_id, rol),
            INDEX idx_sujemp_empresa (empresa_id, rol)
        ) {$charset}");

        // Se sueltan las FKs viejas ANTES de remapear los ids (si no, el UPDATE de abajo
        // choca contra la FK todavía apuntando a iva_clientes/iva_proveedores).
        $pdo->exec('ALTER TABLE ventas DROP FOREIGN KEY fk_ventas_cliente');
        $pdo->exec('ALTER TABLE compras DROP FOREIGN KEY fk_compras_proveedor');
        $pdo->exec('ALTER TABLE actividad_receptor DROP FOREIGN KEY fk_arec_cliente');

        $this->migrarDatos($pdo);

        $pdo->exec(
            'ALTER TABLE ventas ADD CONSTRAINT fk_ventas_cliente'
            . ' FOREIGN KEY (cliente_id) REFERENCES iva_sujetos(id)'
        );
        $pdo->exec(
            'ALTER TABLE compras ADD CONSTRAINT fk_compras_proveedor'
            . ' FOREIGN KEY (proveedor_id) REFERENCES iva_sujetos(id)'
        );
        $pdo->exec(
            'ALTER TABLE actividad_receptor ADD CONSTRAINT fk_arec_cliente'
            . ' FOREIGN KEY (cliente_id) REFERENCES iva_sujetos(id) ON DELETE CASCADE'
        );

        $pdo->exec('DROP TABLE iva_proveedores');
        $pdo->exec('DROP TABLE iva_clientes');
    }

    /**
     * Traslada `iva_clientes`/`iva_proveedores` a `iva_sujetos`+`iva_sujeto_empresas`,
     * deduplicando por (tenant, CUIT normalizado) y dejando el mapeo old_id→new_id en una
     * tabla temporal para reapuntar `ventas.cliente_id`/`compras.proveedor_id`/
     * `actividad_receptor.cliente_id` con un solo `UPDATE ... JOIN` por tabla.
     */
    private function migrarDatos(\PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TEMPORARY TABLE _migr_map (
                tabla_origen ENUM('cliente','proveedor') NOT NULL,
                old_id       INT NOT NULL,
                new_id       INT NOT NULL,
                PRIMARY KEY (tabla_origen, old_id)
            ) ENGINE=InnoDB"
        );

        $this->migrarTabla($pdo, 'iva_clientes', 'cliente', 'C');
        $this->migrarTabla($pdo, 'iva_proveedores', 'proveedor', 'P');

        $pdo->exec(
            'UPDATE ventas v JOIN _migr_map m ON m.tabla_origen = "cliente" AND m.old_id = v.cliente_id'
            . ' SET v.cliente_id = m.new_id'
        );
        $pdo->exec(
            'UPDATE compras c JOIN _migr_map m ON m.tabla_origen = "proveedor" AND m.old_id = c.proveedor_id'
            . ' SET c.proveedor_id = m.new_id'
        );
        $pdo->exec(
            'UPDATE actividad_receptor ar JOIN _migr_map m'
            . ' ON m.tabla_origen = "cliente" AND m.old_id = ar.cliente_id'
            . ' SET ar.cliente_id = m.new_id'
        );

        $pdo->exec('DROP TEMPORARY TABLE _migr_map');
    }

    /**
     * @param 'cliente'|'proveedor' $rol
     * @param 'C'|'P'               $prefijoPlaceholder para CUIT ausente (dato legacy raro)
     */
    private function migrarTabla(\PDO $pdo, string $tablaOrigen, string $rol, string $prefijoPlaceholder): void
    {
        $filas = $pdo->query(
            "SELECT s.*, e.tenant_id AS empresa_tenant_id
               FROM {$tablaOrigen} s JOIN empresas e ON e.id = s.empresa_id"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $insertSujeto = $pdo->prepare(
            'INSERT INTO iva_sujetos
                (tenant_id, cuit, nombre, domicilio, localidad, cp, condicion_iva_id,
                 provincia_id, ingresos_brutos, telefono, cai, fecha_cai, cais)
             VALUES (:tenant_id, :cuit, :nombre, :domicilio, :localidad, :cp, :condicion_iva_id,
                     :provincia_id, :ingresos_brutos, :telefono, :cai, :fecha_cai, :cais)'
        );
        $findSujeto = $pdo->prepare('SELECT id FROM iva_sujetos WHERE tenant_id = ? AND cuit = ?');
        $activar    = $pdo->prepare(
            'INSERT INTO iva_sujeto_empresas (empresa_id, sujeto_id, rol) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE activo = "S"'
        );
        $mapear = $pdo->prepare('INSERT INTO _migr_map (tabla_origen, old_id, new_id) VALUES (?, ?, ?)');

        foreach ($filas as $fila) {
            $tenantId  = (string) $fila['empresa_tenant_id'];
            $cuitCrudo = trim((string) ($fila['cuit'] ?? ''));
            // CUIT sin dato (raro, no debería pasar con datos reales): placeholder único
            // por origen+id para no romper la unicidad (tenant, cuit) del padrón.
            $cuit = $cuitCrudo !== '' ? preg_replace('/\D/', '', $cuitCrudo) : null;
            if ($cuit === null || $cuit === '') {
                $cuit = 'SC' . $prefijoPlaceholder . $fila['id'];
            }

            $findSujeto->execute([$tenantId, $cuit]);
            $existente = $findSujeto->fetchColumn();
            $sujetoId  = $existente !== false ? (int) $existente : null;

            if ($sujetoId === null) {
                $insertSujeto->execute([
                    'tenant_id'        => $tenantId,
                    'cuit'             => $cuit,
                    'nombre'           => $fila['nombre'],
                    'domicilio'        => $fila['domicilio'] ?? null,
                    'localidad'        => $fila['localidad'] ?? null,
                    'cp'               => $fila['cp'] ?? null,
                    'condicion_iva_id' => $fila['condicion_iva_id'] ?? null,
                    'provincia_id'     => $fila['provincia_id'] ?? null,
                    'ingresos_brutos'  => $fila['ingresos_brutos'] ?? null,
                    'telefono'         => $fila['telefono'] ?? null,
                    'cai'              => $fila['cai'] ?? null,
                    'fecha_cai'        => $fila['fecha_cai'] ?? null,
                    'cais'             => $fila['cais'] ?? null,
                ]);
                $sujetoId = (int) $pdo->lastInsertId();
            }

            $activar->execute([(int) $fila['empresa_id'], $sujetoId, $rol]);
            $mapear->execute([$rol, (int) $fila['id'], $sujetoId]);
        }
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS iva_sujeto_empresas');
        $pdo->exec('DROP TABLE IF EXISTS iva_sujetos');
    }
};
