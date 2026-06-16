<?php

namespace App\Modules\Sueldos\Repositories;

use PDO;

/**
 * Configuración de sueldos por empresa (1:1 con `empresas`). Upsert sobre la clave
 * única `empresa_id`.
 */
class EmpresaConfigRepository
{
    private const WRITABLE = [
        'nro_anses', 'caja', 'actividad_id', 'tipo_recibo', 'lugar_pago',
        'jornada_horas', 'jornada_dias', 'ultimo_recibo', 'decimales_en_importe', 'exporta_conta',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(int $empresaId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sueldos_empresa_config WHERE empresa_id = ?');
        $stmt->execute([$empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Inserta o actualiza la config de la empresa.
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function upsert(array $data, int $empresaId): array
    {
        $fields = array_intersect_key($data, array_flip(self::WRITABLE));

        if ($fields === []) {
            // Garantiza que exista la fila aunque no se manden campos.
            $this->pdo->prepare(
                'INSERT INTO sueldos_empresa_config (empresa_id) VALUES (?)
                 ON DUPLICATE KEY UPDATE empresa_id = empresa_id'
            )->execute([$empresaId]);

            return (array) $this->find($empresaId);
        }

        $fields['empresa_id'] = $empresaId;
        $columns      = array_keys($fields);
        $placeholders = array_map(static fn (string $c) => ":{$c}", $columns);
        $updates      = array_map(
            static fn (string $c) => "{$c} = VALUES({$c})",
            array_diff($columns, ['empresa_id']),
        );

        $sql = sprintf(
            'INSERT INTO sueldos_empresa_config (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
            implode(', ', $columns),
            implode(', ', $placeholders),
            implode(', ', $updates),
        );
        $this->pdo->prepare($sql)->execute($fields);

        return (array) $this->find($empresaId);
    }
}
