<?php

/**
 * Módulo Sueldos — Fase 1: catálogos, config por empresa y legajo (empleados +
 * familiares). Ingeniería inversa del legacy Vsueldos (ver docs/ingenieria-inversa/sueldos.md).
 *
 * Reusa la entidad canónica `empresas` y los catálogos `provincias` / `tipos_documento`
 * de Compartido (sin redundancia). La config payroll-específica de la empresa va en
 * `sueldos_empresa_config` (1:1), para no bloatear la empresa canónica.
 *
 * Se modela el subset núcleo del legajo; los campos de seguros/convenios específicos
 * (UOCRA/FAECYS/COMERCIO/SPEP/SICOSS por empleado) se difieren (ver doc).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $cs = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        // ── Catálogos globales (descripcion simple) ───────────────────────────
        foreach ([
            'sueldos_estados_civiles',
            'sueldos_nacionalidades',
            'sueldos_parentescos',
            'sueldos_modalidades_contratacion',
            'sueldos_situaciones_revista',
            'sueldos_condiciones_laborales',
            'sueldos_regimenes_jubilatorios',
        ] as $tabla) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS {$tabla} (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                descripcion VARCHAR(100) NOT NULL
            ) {$cs}");
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS sueldos_obras_sociales (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            codigo      VARCHAR(15)  DEFAULT NULL,
            descripcion VARCHAR(150) NOT NULL
        ) {$cs}");

        // ── Config por empresa (1:1) ──────────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS sueldos_empresa_config (
            id                     INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id             INT NOT NULL,
            nro_anses              VARCHAR(20)  DEFAULT NULL,
            caja                   VARCHAR(50)  DEFAULT NULL,
            actividad_id           INT          DEFAULT NULL,
            tipo_recibo            VARCHAR(20)  DEFAULT NULL,
            lugar_pago             VARCHAR(100) DEFAULT NULL,
            jornada_horas          DECIMAL(6,2) DEFAULT NULL,
            jornada_dias           DECIMAL(6,2) DEFAULT NULL,
            ultimo_recibo          INT          DEFAULT NULL,
            decimales_en_importe   CHAR(1)      NOT NULL DEFAULT 'N',
            exporta_conta          CHAR(1)      NOT NULL DEFAULT 'N',
            CONSTRAINT fk_sueconf_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
            UNIQUE KEY uq_sueconf_empresa (empresa_id)
        ) {$cs}");

        // ── Catálogos por empresa ─────────────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS sueldos_departamentos (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id  INT          NOT NULL,
            descripcion VARCHAR(100) NOT NULL,
            CONSTRAINT fk_suedepto_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
            INDEX idx_suedepto_empresa (empresa_id)
        ) {$cs}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS sueldos_categorias (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id  INT          NOT NULL,
            numero      INT          DEFAULT NULL,
            descripcion VARCHAR(100) NOT NULL,
            valor       DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'básico de la categoría',
            CONSTRAINT fk_suecateg_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
            INDEX idx_suecateg_empresa (empresa_id)
        ) {$cs}");

        // ── Legajo: empleados ─────────────────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS empleados (
            id                       INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id               INT          NOT NULL,
            legajo                   INT          DEFAULT NULL,
            nombres                  VARCHAR(100) NOT NULL,
            primer_apellido          VARCHAR(60)  DEFAULT NULL,
            segundo_apellido         VARCHAR(60)  DEFAULT NULL,
            cuil                     VARCHAR(13)  DEFAULT NULL,
            tipo_documento_id        INT          DEFAULT NULL,
            numero_documento         VARCHAR(15)  DEFAULT NULL,
            fecha_nacimiento         DATE         DEFAULT NULL,
            genero                   VARCHAR(1)   DEFAULT NULL,
            estado_civil_id          INT          DEFAULT NULL,
            nacionalidad_id          INT          DEFAULT NULL,
            direccion                VARCHAR(150) DEFAULT NULL,
            localidad                VARCHAR(100) DEFAULT NULL,
            provincia_id             INT          DEFAULT NULL,
            telefono                 VARCHAR(25)  DEFAULT NULL,
            celular                  VARCHAR(25)  DEFAULT NULL,
            email                    VARCHAR(100) DEFAULT NULL,
            fecha_ingreso            DATE         DEFAULT NULL,
            fecha_egreso             DATE         DEFAULT NULL,
            categoria_id             INT          DEFAULT NULL,
            basico                   DECIMAL(18,2) NOT NULL DEFAULT 0,
            obra_social_id           INT          DEFAULT NULL,
            regimen_jubilatorio_id   INT          DEFAULT NULL,
            modalidad_contratacion_id INT         DEFAULT NULL,
            situacion_revista_id     INT          DEFAULT NULL,
            condicion_laboral_id     INT          DEFAULT NULL,
            departamento_id          INT          DEFAULT NULL,
            lugar_pago               VARCHAR(100) DEFAULT NULL,
            forma_de_pago            VARCHAR(30)  DEFAULT NULL,
            numero_cuenta            VARCHAR(30)  DEFAULT NULL,
            activo                   CHAR(1)      NOT NULL DEFAULT 'S',
            CONSTRAINT fk_emp_empresa     FOREIGN KEY (empresa_id)                REFERENCES empresas(id) ON DELETE CASCADE,
            CONSTRAINT fk_emp_tipodoc     FOREIGN KEY (tipo_documento_id)         REFERENCES tipos_documento(id),
            CONSTRAINT fk_emp_provincia   FOREIGN KEY (provincia_id)              REFERENCES provincias(id),
            CONSTRAINT fk_emp_estciv      FOREIGN KEY (estado_civil_id)           REFERENCES sueldos_estados_civiles(id),
            CONSTRAINT fk_emp_nac         FOREIGN KEY (nacionalidad_id)           REFERENCES sueldos_nacionalidades(id),
            CONSTRAINT fk_emp_categoria   FOREIGN KEY (categoria_id)              REFERENCES sueldos_categorias(id),
            CONSTRAINT fk_emp_os          FOREIGN KEY (obra_social_id)            REFERENCES sueldos_obras_sociales(id),
            CONSTRAINT fk_emp_regjub      FOREIGN KEY (regimen_jubilatorio_id)    REFERENCES sueldos_regimenes_jubilatorios(id),
            CONSTRAINT fk_emp_modcontr    FOREIGN KEY (modalidad_contratacion_id) REFERENCES sueldos_modalidades_contratacion(id),
            CONSTRAINT fk_emp_sitrev      FOREIGN KEY (situacion_revista_id)      REFERENCES sueldos_situaciones_revista(id),
            CONSTRAINT fk_emp_condlab     FOREIGN KEY (condicion_laboral_id)      REFERENCES sueldos_condiciones_laborales(id),
            CONSTRAINT fk_emp_depto       FOREIGN KEY (departamento_id)           REFERENCES sueldos_departamentos(id),
            INDEX idx_emp_empresa (empresa_id)
        ) {$cs}");

        // ── Legajo: familiares ────────────────────────────────────────────────
        $pdo->exec("CREATE TABLE IF NOT EXISTS familiares (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            empleado_id       INT          NOT NULL,
            parentesco_id     INT          DEFAULT NULL,
            nombre            VARCHAR(100) NOT NULL,
            tipo_documento_id INT          DEFAULT NULL,
            numero_documento  VARCHAR(15)  DEFAULT NULL,
            fecha_nacimiento  DATE         DEFAULT NULL,
            escolarizado      CHAR(1)      NOT NULL DEFAULT 'N',
            discapacitado     CHAR(1)      NOT NULL DEFAULT 'N',
            deduce_ganancias  CHAR(1)      NOT NULL DEFAULT 'N',
            porc_deduccion    DECIMAL(6,2) DEFAULT NULL,
            CONSTRAINT fk_fam_empleado   FOREIGN KEY (empleado_id)       REFERENCES empleados(id) ON DELETE CASCADE,
            CONSTRAINT fk_fam_parentesco FOREIGN KEY (parentesco_id)     REFERENCES sueldos_parentescos(id),
            CONSTRAINT fk_fam_tipodoc    FOREIGN KEY (tipo_documento_id) REFERENCES tipos_documento(id),
            INDEX idx_fam_empleado (empleado_id)
        ) {$cs}");
    }

    public function down(\PDO $pdo): void
    {
        foreach ([
            'familiares', 'empleados', 'sueldos_categorias', 'sueldos_departamentos',
            'sueldos_empresa_config', 'sueldos_obras_sociales', 'sueldos_regimenes_jubilatorios',
            'sueldos_condiciones_laborales', 'sueldos_situaciones_revista',
            'sueldos_modalidades_contratacion', 'sueldos_parentescos',
            'sueldos_nacionalidades', 'sueldos_estados_civiles',
        ] as $tabla) {
            $pdo->exec("DROP TABLE IF EXISTS {$tabla}");
        }
    }
};
