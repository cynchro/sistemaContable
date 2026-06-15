<?php

namespace App\Support\Calc\Contracts;

/**
 * Contrato marcador del "motor de cálculos".
 *
 * Las calculadoras de dominio (p. ej. `App\Modules\Iva\Calc\IvaComprobanteCalculator`)
 * lo implementan para identificarse como componentes de cálculo PUROS:
 *
 *  - sin estado y sin efectos colaterales,
 *  - sin acceso a base de datos ni a la red,
 *  - operan sobre {@see \App\Support\Calc\Decimal} y devuelven resultados.
 *
 * Cada módulo registra sus calculadoras en el container desde su ServiceProvider,
 * y los Services las reciben inyectadas. Así el cálculo queda aislado, testeable
 * como función pura y reusable entre módulos (IVA, Sueldos, Contable).
 */
interface Calculator
{
}
