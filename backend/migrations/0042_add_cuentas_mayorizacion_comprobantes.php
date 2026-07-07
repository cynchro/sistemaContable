<?php

/**
 * Imputación contable para mayorización interna en ventas y compras.
 *
 * Réplica de la "Imputación para mayorización interna" del Visual IVA (IMG del contador):
 * cada comprobante puede imputarse a una cuenta del plan (Debe y/o Haber). Con eso se
 * arman los reportes de Mayor de Cuentas (resumen por cuenta + detalle de comprobantes)
 * que el estudio usa a diario (p. ej. "compra de combustible: total"). Las cuentas son del
 * plan por empresa (`cuentas`). Se repodan así los campos `*_CTA_*` del legacy (ver
 * app/Modules/Iva/pendientes.md §F).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        foreach (['ventas', 'compras'] as $t) {
            // ON DELETE SET NULL: borrar una cuenta del plan desvincula la imputación del
            // comprobante (no la bloquea) y deja pasar el cascade al borrar la empresa.
            $pdo->exec("ALTER TABLE {$t}
                ADD COLUMN cuenta_debe_id  INT DEFAULT NULL AFTER total_informado,
                ADD COLUMN cuenta_haber_id INT DEFAULT NULL AFTER cuenta_debe_id,
                ADD CONSTRAINT fk_{$t}_cuenta_debe  FOREIGN KEY (cuenta_debe_id)
                    REFERENCES cuentas(id) ON DELETE SET NULL,
                ADD CONSTRAINT fk_{$t}_cuenta_haber FOREIGN KEY (cuenta_haber_id)
                    REFERENCES cuentas(id) ON DELETE SET NULL");
        }
    }

    public function down(\PDO $pdo): void
    {
        foreach (['ventas', 'compras'] as $t) {
            $pdo->exec("ALTER TABLE {$t}
                DROP FOREIGN KEY fk_{$t}_cuenta_debe,
                DROP FOREIGN KEY fk_{$t}_cuenta_haber,
                DROP COLUMN cuenta_debe_id,
                DROP COLUMN cuenta_haber_id");
        }
    }
};
