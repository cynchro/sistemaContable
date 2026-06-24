<?php

namespace App\Support\Calc;

use InvalidArgumentException;

/**
 * Value object inmutable para aritmética decimal EXACTA (sobre ext-bcmath).
 *
 * Motivación: los importes contables son `DECIMAL(18,2)` y las alícuotas
 * `DECIMAL(7,3)`. Operarlos con `float` introduce errores de representación que
 * impiden reconciliar 1:1 contra el sistema legacy. `Decimal` opera siempre en
 * base 10 con una escala interna amplia y redondea sólo cuando el llamador lo
 * pide explícitamente.
 *
 * Todas las operaciones devuelven una instancia nueva (inmutabilidad).
 */
final class Decimal
{
    /** Escala interna de trabajo (decimales) para multiplicación y división. */
    private const MATH_SCALE = 12;

    /** Redondeo "mitad hacia arriba" alejándose del cero (estándar fiscal AR). */
    public const ROUND_HALF_UP = 'half_up';

    /** Truncamiento hacia cero (descarta decimales sin redondear). */
    public const ROUND_TRUNCATE = 'truncate';

    private readonly string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Construye desde un entero, float o string numérico.
     *
     * Se desaconseja pasar `float` (puede arrastrar imprecisión); preferí string.
     */
    public static function of(int|float|string $value): self
    {
        $normalized = is_float($value)
            ? rtrim(rtrim(sprintf('%.*F', self::MATH_SCALE, $value), '0'), '.')
            : trim((string) $value);

        if ($normalized === '' || $normalized === '-' || $normalized === '+') {
            $normalized = '0';
        }

        if (!preg_match('/^[+-]?\d+(\.\d+)?$/', $normalized)) {
            throw new InvalidArgumentException("Valor decimal inválido: '{$value}'.");
        }

        // bcmath no acepta el signo '+' inicial.
        return new self(ltrim($normalized, '+') ?: '0');
    }

    public static function zero(): self
    {
        return new self('0');
    }

    public function add(self $other): self
    {
        return new self(bcadd($this->value, $other->value, self::MATH_SCALE));
    }

    public function sub(self $other): self
    {
        return new self(bcsub($this->value, $other->value, self::MATH_SCALE));
    }

    public function mul(self $other): self
    {
        return new self(bcmul($this->value, $other->value, self::MATH_SCALE));
    }

    public function div(self $divisor): self
    {
        if ($divisor->isZero()) {
            throw new InvalidArgumentException('División por cero.');
        }

        return new self(bcdiv($this->value, $divisor->value, self::MATH_SCALE));
    }

    /**
     * Aplica un porcentaje: $this * ($rate / 100).
     * Ej.: neto 1000.00 al 21.000% → 210.00 (redondeando luego a 2 decimales).
     */
    public function percentage(self $rate): self
    {
        return $this->mul($rate)->div(self::of(100));
    }

    public function negate(): self
    {
        return self::zero()->sub($this);
    }

    public function abs(): self
    {
        return $this->isNegative() ? $this->negate() : $this;
    }

    /** Redondea a `$scale` decimales con el modo indicado. */
    public function round(int $scale = 2, string $mode = self::ROUND_HALF_UP): self
    {
        if ($scale < 0) {
            throw new InvalidArgumentException('La escala no puede ser negativa.');
        }

        if ($mode === self::ROUND_TRUNCATE) {
            return new self(bcadd($this->value, '0', $scale));
        }

        if ($mode !== self::ROUND_HALF_UP) {
            throw new InvalidArgumentException("Modo de redondeo desconocido: '{$mode}'.");
        }

        // Half-up alejándose del cero: sumamos 0.5·10^-scale al valor absoluto y
        // truncamos (bcadd a `$scale` trunca), luego restauramos el signo.
        $negative = $this->isNegative();
        $abs      = $negative ? bcsub('0', $this->value, self::MATH_SCALE) : $this->value;
        $addend   = '0.' . str_repeat('0', $scale) . '5';
        $rounded  = bcadd($abs, $addend, $scale);

        return new self($negative && bccomp($rounded, '0', $scale) !== 0 ? "-{$rounded}" : $rounded);
    }

    /** -1, 0 ó 1 según $this sea menor, igual o mayor que $other. */
    public function compareTo(self $other): int
    {
        return bccomp($this->value, $other->value, self::MATH_SCALE);
    }

    public function equals(self $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    public function isZero(): bool
    {
        return bccomp($this->value, '0', self::MATH_SCALE) === 0;
    }

    public function isNegative(): bool
    {
        return bccomp($this->value, '0', self::MATH_SCALE) < 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->value, '0', self::MATH_SCALE) > 0;
    }

    /**
     * Representación en string. Si se pasa `$scale`, redondea (half-up) y fija
     * esa cantidad de decimales — apto para persistir en columnas DECIMAL.
     */
    public function value(?int $scale = null): string
    {
        if ($scale === null) {
            return $this->normalized();
        }

        return bcadd($this->round($scale)->value, '0', $scale);
    }

    public function __toString(): string
    {
        return $this->normalized();
    }

    /** Quita ceros decimales sobrantes y normaliza el cero negativo. */
    private function normalized(): string
    {
        $value = $this->value;

        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        if ($value === '' || $value === '-0' || $value === '-') {
            $value = '0';
        }

        return $value;
    }
}
