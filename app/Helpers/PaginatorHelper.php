<?php

namespace App\Helpers;

class PaginatorHelper
{
    private int $page;
    private int $perPage;

    /**
     * @param list<mixed> $params Positional parameters bound to the query (? placeholders).
     *
     * IMPORTANT: $query must be a static, hardcoded SQL string. Never pass user-controlled
     * input as part of $query — use $params for dynamic values.
     */
    public function __construct(
        private \PDO $connection,
        private string $query,
        int $page = 1,
        int $perPage = 10,
        private bool $paginate = true,
        private array $params = [],
    ) {
        $this->page    = max(1, $page);
        $this->perPage = max(1, $perPage);
    }

    private function getTotalItems(): int
    {
        $stmt = $this->connection->prepare("SELECT COUNT(*) FROM ({$this->query}) AS sub");
        $stmt->execute($this->params);
        return (int) $stmt->fetchColumn();
    }

    public function getPaginatedResults(): array
    {
        if ($this->paginate) {
            $offset     = ($this->page - 1) * $this->perPage;
            $stmt       = $this->connection->prepare("{$this->query} LIMIT ? OFFSET ?");
            $allParams  = array_merge($this->params, [$this->perPage, $offset]);
            $stmt->execute($allParams);
        } else {
            $stmt = $this->connection->prepare($this->query);
            $stmt->execute($this->params);
        }

        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $total = empty($items) ? 0 : ($this->paginate ? $this->getTotalItems() : count($items));

        return [
            'total'               => $total,
            'cantidad_por_pagina' => $this->perPage,
            'pagina'              => $this->page,
            'cantidad_total'      => $total,
            'results'             => $items,
        ];
    }
}
