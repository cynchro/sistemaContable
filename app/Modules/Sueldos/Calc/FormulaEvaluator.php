<?php

namespace App\Modules\Sueldos\Calc;

use InvalidArgumentException;
use App\Support\Calc\Decimal;
use App\Support\Calc\Contracts\Calculator;

/**
 * Evaluador de las fórmulas de conceptos del legacy Vsueldos, sobre el motor
 * {@see Decimal}. Es el núcleo del cálculo de la liquidación.
 *
 * Gramática (ingeniería inversa de CONCEPTOS.FORMULA):
 *   expr    := term (('+' | '-') term)*
 *   term    := factor (('*' | '/') factor)*
 *   factor  := '-' factor | '(' expr ')' | number | conceptref | identifier
 *   conceptref := DIGITS '#' ('...' DIGITS '#')?   // ref simple o rango (suma)
 *   number  := DIGITS ('.' DIGITS)?
 *   identifier := variable (BASICO, CAN, IMP, ANTIG, NOREM, ...)
 *
 * - `NNN#`  → importe ya calculado del concepto de código NNN en esta liquidación
 *   (0 si no fue liquidado).
 * - `NNN#...MMM#` → suma de los conceptos cuyo código (numérico) ∈ [NNN, MMM].
 * - Las variables las provee el contexto (por empleado/liquidación); una variable
 *   desconocida lanza excepción (no se asume 0, para no liquidar mal en silencio).
 *
 * Puro y sin estado entre llamadas: cada `evaluar()` parsea y evalúa de cero.
 *
 * @phpstan-type Token array{type: string, value: string}
 */
final class FormulaEvaluator implements Calculator
{
    /** @var list<Token> */
    private array $tokens = [];
    private int $pos = 0;

    /** @var array<string, string> nombre de variable => valor numérico */
    private array $variables = [];

    /** @var array<string, string> código de concepto => importe ya calculado */
    private array $conceptos = [];

    /**
     * @param array<string, int|float|string> $variables  p. ej. ['BASICO' => '850000', 'CAN' => 1]
     * @param array<string, int|float|string> $conceptos  código => importe ya liquidado
     */
    public function evaluar(string $formula, array $variables = [], array $conceptos = []): Decimal
    {
        $this->variables = array_map(static fn ($v) => (string) $v, $variables);
        $this->conceptos = array_map(static fn ($v) => (string) $v, $conceptos);
        $this->tokens    = $this->tokenize($formula);
        $this->pos       = 0;

        if ($this->tokens === []) {
            return Decimal::zero();
        }

        $result = $this->parseExpr();

        if ($this->pos < count($this->tokens)) {
            throw new InvalidArgumentException("Fórmula inválida cerca de: '{$this->tokens[$this->pos]['value']}'.");
        }

        return $result;
    }

    /** @phpstan-impure avanza $this->pos */
    private function parseExpr(): Decimal
    {
        $value = $this->parseTerm();

        while (($t = $this->peek()) !== null && ($t['type'] === '+' || $t['type'] === '-')) {
            $this->pos++;
            $rhs   = $this->parseTerm();
            $value = $t['type'] === '+' ? $value->add($rhs) : $value->sub($rhs);
        }

        return $value;
    }

    /** @phpstan-impure avanza $this->pos */
    private function parseTerm(): Decimal
    {
        $value = $this->parseFactor();

        while (($t = $this->peek()) !== null && ($t['type'] === '*' || $t['type'] === '/')) {
            $this->pos++;
            $rhs   = $this->parseFactor();
            $value = $t['type'] === '*' ? $value->mul($rhs) : $value->div($rhs);
        }

        return $value;
    }

    /** @phpstan-impure avanza $this->pos */
    private function parseFactor(): Decimal
    {
        $t = $this->peek();

        if ($t === null) {
            throw new InvalidArgumentException('Fórmula incompleta.');
        }

        if ($t['type'] === '-') {
            $this->pos++;
            return $this->parseFactor()->negate();
        }

        if ($t['type'] === '+') {
            $this->pos++;
            return $this->parseFactor();
        }

        if ($t['type'] === '(') {
            $this->pos++;
            $value = $this->parseExpr();
            $this->expect(')');
            return $value;
        }

        if ($t['type'] === 'number') {
            $this->pos++;
            return Decimal::of($t['value']);
        }

        if ($t['type'] === 'concept') {
            return $this->parseConcept();
        }

        if ($t['type'] === 'ident') {
            $this->pos++;
            return $this->resolverVariable($t['value']);
        }

        throw new InvalidArgumentException("Token inesperado en la fórmula: '{$t['value']}'.");
    }

    /** @phpstan-impure avanza $this->pos */
    private function parseConcept(): Decimal
    {
        $desde = $this->tokens[$this->pos]['value'];
        $this->pos++;

        // Rango: NNN#...MMM#
        if (($t = $this->peek()) !== null && $t['type'] === 'range') {
            $this->pos++;
            $hasta = $this->peek();
            if ($hasta === null || $hasta['type'] !== 'concept') {
                throw new InvalidArgumentException('Rango de conceptos mal formado.');
            }
            $this->pos++;

            return $this->sumarRango($desde, $hasta['value']);
        }

        return Decimal::of($this->conceptos[$this->normalizarCodigo($desde)] ?? '0');
    }

    private function sumarRango(string $desde, string $hasta): Decimal
    {
        $ini = (int) $desde;
        $fin = (int) $hasta;
        $total = Decimal::zero();

        foreach ($this->conceptos as $codigo => $importe) {
            $num = (int) $codigo;
            if ($num >= $ini && $num <= $fin) {
                $total = $total->add(Decimal::of($importe));
            }
        }

        return $total;
    }

    private function resolverVariable(string $nombre): Decimal
    {
        if (!array_key_exists($nombre, $this->variables)) {
            throw new InvalidArgumentException("Variable desconocida en la fórmula: '{$nombre}'.");
        }

        return Decimal::of($this->variables[$nombre]);
    }

    /** Normaliza el código de concepto para indexar (quita ceros a izquierda manteniendo "0"). */
    private function normalizarCodigo(string $codigo): string
    {
        return isset($this->conceptos[$codigo])
            ? $codigo
            : (string) (int) $codigo;
    }

    /** @phpstan-impure avanza $this->pos */
    private function expect(string $type): void
    {
        $t = $this->peek();
        if ($t === null || $t['type'] !== $type) {
            throw new InvalidArgumentException("Se esperaba '{$type}' en la fórmula.");
        }
        $this->pos++;
    }

    /** @return Token|null */
    private function peek(): ?array
    {
        return $this->tokens[$this->pos] ?? null;
    }

    /**
     * @return list<Token>
     */
    private function tokenize(string $formula): array
    {
        $tokens = [];
        $len    = strlen($formula);
        $i      = 0;

        while ($i < $len) {
            $ch = $formula[$i];

            if (ctype_space($ch)) {
                $i++;
                continue;
            }

            if (in_array($ch, ['+', '-', '*', '/', '(', ')'], true)) {
                $tokens[] = ['type' => $ch, 'value' => $ch];
                $i++;
                continue;
            }

            // Rango '...'
            if ($ch === '.' && substr($formula, $i, 3) === '...') {
                $tokens[] = ['type' => 'range', 'value' => '...'];
                $i += 3;
                continue;
            }

            if (ctype_digit($ch)) {
                $start = $i;
                while ($i < $len && ctype_digit($formula[$i])) {
                    $i++;
                }
                $digits = substr($formula, $start, $i - $start);

                // Referencia a concepto: DIGITS '#'
                if ($i < $len && $formula[$i] === '#') {
                    $i++;
                    $tokens[] = ['type' => 'concept', 'value' => $digits];
                    continue;
                }

                // Número con decimales: DIGITS '.' DIGITS (no confundir con '...')
                if ($i + 1 < $len && $formula[$i] === '.' && ctype_digit($formula[$i + 1])) {
                    $i++; // '.'
                    $dstart = $i;
                    while ($i < $len && ctype_digit($formula[$i])) {
                        $i++;
                    }
                    $digits .= '.' . substr($formula, $dstart, $i - $dstart);
                }

                $tokens[] = ['type' => 'number', 'value' => $digits];
                continue;
            }

            if (ctype_alpha($ch) || $ch === '_') {
                $start = $i;
                while ($i < $len && (ctype_alnum($formula[$i]) || $formula[$i] === '_')) {
                    $i++;
                }
                $tokens[] = ['type' => 'ident', 'value' => substr($formula, $start, $i - $start)];
                continue;
            }

            throw new InvalidArgumentException("Carácter inválido en la fórmula: '{$ch}'.");
        }

        return $tokens;
    }
}
