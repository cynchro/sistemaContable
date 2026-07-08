<?php

/**
 * Mayorización por línea (respuesta R1 del contador, 07/07): cada línea de discriminación
 * de IVA puede imputarse a una cuenta del plan. Federico carga un mismo comprobante con
 * varias cuentas (ej. resumen bancario: los montos al 21% van a "Gastos y comisiones de
 * cuenta" y los del 10,5% a "Intereses por giro en descubierto"). El Mayor imputa el NETO
 * de cada línea a su cuenta (el gasto/ingreso), mientras el IVA queda aparte.
 *
 * Complementa la cuenta debe/haber a nivel comprobante (migración 0042), que sigue como
 * contrapartida del total. `cuenta_id` nullable con ON DELETE SET NULL (borrar la cuenta
 * del plan desvincula, no bloquea; deja pasar el cascade al borrar la empresa).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        foreach (['venta_discriminaciones' => 'vd', 'compra_discriminaciones' => 'cd'] as $t => $alias) {
            $pdo->exec("ALTER TABLE {$t}
                ADD COLUMN cuenta_id INT DEFAULT NULL AFTER neto_gravado,
                ADD CONSTRAINT fk_{$alias}_cuenta FOREIGN KEY (cuenta_id)
                    REFERENCES cuentas(id) ON DELETE SET NULL");
        }
    }

    public function down(\PDO $pdo): void
    {
        foreach (['venta_discriminaciones' => 'vd', 'compra_discriminaciones' => 'cd'] as $t => $alias) {
            $pdo->exec("ALTER TABLE {$t}
                DROP FOREIGN KEY fk_{$alias}_cuenta,
                DROP COLUMN cuenta_id");
        }
    }
};
