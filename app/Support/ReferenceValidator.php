<?php

namespace App\Support;

use PDO;
use InvalidArgumentException;
use App\Exceptions\ValidationException;

/**
 * Valida "amablemente" referencias a otras tablas (claves foráneas): que el id exista y,
 * opcionalmente, que pertenezca a un ámbito (p. ej. cuenta de la empresa, rubro del tenant).
 * Junta todos los errores y lanza una sola ValidationException (422) con el campo exacto,
 * en lugar de dejar que la FK explote como error 500 al insertar.
 *
 * Los nombres de tabla/columna provienen del código (no del input) y se validan igual como
 * identificadores por las dudas.
 */
final class ReferenceValidator
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array<string, array{table:string, value:mixed, scope?:array<string,mixed>}> $rules
     *        Indexado por nombre de campo. Los value null/'' se ignoran (campos opcionales).
     */
    public function validate(array $rules): void
    {
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $rule['value'] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            if (!$this->exists($rule['table'], (int) $value, $rule['scope'] ?? [])) {
                $errors[$field] = ["El valor de {$field} no existe o no pertenece a este ámbito."];
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    /** @param array<string, mixed> $scope columna => valor */
    private function exists(string $table, int $id, array $scope): bool
    {
        $this->assertIdent($table);

        $where  = ['id = :id'];
        $params = ['id' => $id];

        foreach ($scope as $col => $val) {
            $this->assertIdent($col);
            $where[]            = "{$col} = :s_{$col}";
            $params["s_{$col}"] = $val;
        }

        $sql  = sprintf('SELECT EXISTS(SELECT 1 FROM %s WHERE %s)', $table, implode(' AND ', $where));
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    private function assertIdent(string $name): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("Identificador inválido: {$name}");
        }
    }
}
