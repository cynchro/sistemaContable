<?php

namespace App\Modules\Honorarios\Repositories;

use PDO;
use App\Exceptions\NotFoundException;

/**
 * Persistencia del documento de honorarios (cabecera + líneas) por empresa.
 * El multi-insert corre dentro de la transacción que abre el Service.
 */
class HonorarioRepository
{
    private const HEADER_WRITABLE = ['fecha', 'valor_uc', 'descripcion', 'estado', 'total'];

    private const LINEA_WRITABLE = [
        'servicio_id', 'codigo', 'descripcion', 'uc', 'complejidad', 'factor', 'cantidad', 'importe',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function findAllByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM honorarios WHERE empresa_id = ? ORDER BY fecha, id');
        $stmt->execute([$empresaId]);

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed> */
    public function findById(int $id, int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM honorarios WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);
        $hon = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$hon) {
            throw new NotFoundException('Honorario', $id);
        }

        $lin = $this->pdo->prepare('SELECT * FROM honorario_lineas WHERE honorario_id = ? ORDER BY id');
        $lin->execute([$id]);
        $hon['lineas'] = (array) $lin->fetchAll(PDO::FETCH_ASSOC);

        return $hon;
    }

    /**
     * @param  array<string, mixed>       $header
     * @param  list<array<string, mixed>> $lineas
     * @return array<string, mixed>
     */
    public function create(array $header, array $lineas, int $empresaId): array
    {
        $fields = array_intersect_key($header, array_flip(self::HEADER_WRITABLE));
        $fields['empresa_id'] = $empresaId;

        $columns      = array_keys($fields);
        $placeholders = array_map(static fn (string $c) => ":{$c}", $columns);

        $this->pdo->prepare(sprintf(
            'INSERT INTO honorarios (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders),
        ))->execute($fields);

        $honorarioId = (int) $this->pdo->lastInsertId();

        foreach ($lineas as $linea) {
            $lf = array_intersect_key($linea, array_flip(self::LINEA_WRITABLE));
            $lf['honorario_id'] = $honorarioId;

            $cols  = array_keys($lf);
            $place = array_map(static fn (string $c) => ":{$c}", $cols);
            $this->pdo->prepare(sprintf(
                'INSERT INTO honorario_lineas (%s) VALUES (%s)',
                implode(', ', $cols),
                implode(', ', $place),
            ))->execute($lf);
        }

        return $this->findById($honorarioId, $empresaId);
    }

    public function delete(int $id, int $empresaId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM honorarios WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);

        return $stmt->rowCount() > 0;
    }
}
