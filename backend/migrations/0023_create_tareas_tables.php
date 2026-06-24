<?php

/**
 * Módulo Tareas (de sistemaCuarto/Synergys): workflow de tareas del estudio.
 * Ingeniería inversa del clúster Task* (tasks, task_types, task_comments,
 * task_state_history). Ver docs/ingenieria-inversa/sistemacuarto.md.
 *
 * Las tareas pertenecen al tenant (estudio); pueden referir a una empresa
 * (contribuyente=`empresas`) y a usuarios del framework (creador/asignado).
 * Se difieren: derivaciones, documentos, servicios, schedules y multi-asignado.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $cs = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS tarea_tipos (
            id                   INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id            CHAR(36)     NOT NULL,
            nombre               VARCHAR(120) NOT NULL,
            categoria            VARCHAR(80)  DEFAULT NULL,
            descripcion          TEXT         DEFAULT NULL,
            prioridad_sugerida   VARCHAR(20)  DEFAULT NULL,
            tiempo_estimado_horas DECIMAL(8,2) DEFAULT NULL,
            activo               CHAR(1)      NOT NULL DEFAULT 'S',
            CONSTRAINT fk_tareatipo_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            INDEX idx_tareatipo_tenant (tenant_id)
        ) {$cs}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS tareas (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id    CHAR(36)     NOT NULL,
            tipo_id      INT          DEFAULT NULL,
            empresa_id   INT          DEFAULT NULL COMMENT 'contribuyente al que refiere (opcional)',
            titulo       VARCHAR(150) NOT NULL,
            descripcion  TEXT         DEFAULT NULL,
            estado       VARCHAR(30)  NOT NULL DEFAULT 'pendiente',
            prioridad    VARCHAR(20)  NOT NULL DEFAULT 'media',
            fecha_limite DATE         DEFAULT NULL,
            created_by   INT          DEFAULT NULL,
            assigned_to  INT          DEFAULT NULL,
            CONSTRAINT fk_tarea_tenant   FOREIGN KEY (tenant_id)   REFERENCES tenants(id)     ON DELETE CASCADE,
            CONSTRAINT fk_tarea_tipo     FOREIGN KEY (tipo_id)     REFERENCES tarea_tipos(id) ON DELETE SET NULL,
            CONSTRAINT fk_tarea_empresa  FOREIGN KEY (empresa_id)  REFERENCES empresas(id)    ON DELETE SET NULL,
            CONSTRAINT fk_tarea_creador  FOREIGN KEY (created_by)  REFERENCES usuarios(id)    ON DELETE SET NULL,
            CONSTRAINT fk_tarea_asignado FOREIGN KEY (assigned_to) REFERENCES usuarios(id)    ON DELETE SET NULL,
            INDEX idx_tarea_tenant (tenant_id),
            INDEX idx_tarea_estado (estado)
        ) {$cs}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS tarea_comentarios (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            tarea_id   INT      NOT NULL,
            usuario_id INT      DEFAULT NULL,
            comentario TEXT     NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_tareacom_tarea FOREIGN KEY (tarea_id)   REFERENCES tareas(id)   ON DELETE CASCADE,
            CONSTRAINT fk_tareacom_user  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
            INDEX idx_tareacom_tarea (tarea_id)
        ) {$cs}");

        $pdo->exec("CREATE TABLE IF NOT EXISTS tarea_estado_historial (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            tarea_id        INT         NOT NULL,
            estado_anterior VARCHAR(30) DEFAULT NULL,
            estado_nuevo    VARCHAR(30) NOT NULL,
            usuario_id      INT         DEFAULT NULL,
            observacion     TEXT        DEFAULT NULL,
            created_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_tareahist_tarea FOREIGN KEY (tarea_id)   REFERENCES tareas(id)   ON DELETE CASCADE,
            CONSTRAINT fk_tareahist_user  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
            INDEX idx_tareahist_tarea (tarea_id)
        ) {$cs}");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS tarea_estado_historial');
        $pdo->exec('DROP TABLE IF EXISTS tarea_comentarios');
        $pdo->exec('DROP TABLE IF EXISTS tareas');
        $pdo->exec('DROP TABLE IF EXISTS tarea_tipos');
    }
};
