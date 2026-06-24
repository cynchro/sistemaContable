<?php

/**
 * Exportador TXT configurable (réplica funcional de EXPOTXT_ARCHIVOS / EXPOTXT_CAMPOS
 * del legacy, ver app/Modules/Iva/pendientes.md §D).
 *
 * El estudio define "formatos" de exportación del subdiario: un formato es delimitado
 * (con separador) o de ancho fijo, y lista qué campos exportar, en qué orden y con qué
 * formato (longitud/relleno/alineación para fijo, decimales y formato de fecha).
 *
 * Diferencia con el legacy: los campos NO son tabla/columna arbitraria (EXPOTXT.EXP_TABLA),
 * sino una lista blanca de campos del subdiario resuelta en código
 * ({@see App\Modules\Iva\Export\ExportCampoCatalogo}) — sin SQL dinámico.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS iva_export_formatos (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id  CHAR(36)    NOT NULL,
            nombre     VARCHAR(80) NOT NULL,
            tipo       VARCHAR(12) NOT NULL DEFAULT 'delimitado',
            separador  VARCHAR(4)  NOT NULL DEFAULT ';',
            eol        VARCHAR(4)  NOT NULL DEFAULT 'lf',
            created_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_export_formato_nombre (tenant_id, nombre),
            INDEX idx_export_formato_tenant (tenant_id)
        ) {$charset}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS iva_export_formato_campos (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            formato_id    INT          NOT NULL,
            campo         VARCHAR(40)  NOT NULL,
            orden         INT          NOT NULL DEFAULT 0,
            longitud      INT          NOT NULL DEFAULT 0,
            relleno       VARCHAR(1)   NOT NULL DEFAULT ' ',
            alinea        VARCHAR(10)  NOT NULL DEFAULT 'izquierda',
            decimales     INT          NOT NULL DEFAULT 2,
            sep_decimal   VARCHAR(1)   NOT NULL DEFAULT '.',
            formato_fecha VARCHAR(12)  NOT NULL DEFAULT 'Y-m-d',
            CONSTRAINT fk_expcampo_formato FOREIGN KEY (formato_id)
                REFERENCES iva_export_formatos(id) ON DELETE CASCADE,
            INDEX idx_expcampo_formato (formato_id)
        ) {$charset}");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS iva_export_formato_campos');
        $pdo->exec('DROP TABLE IF EXISTS iva_export_formatos');
    }
};
