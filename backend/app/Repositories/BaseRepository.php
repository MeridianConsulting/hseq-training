<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

abstract class BaseRepository
{
    protected Database $db;
    protected string $table;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findAll(array $conditions = [], string $orderBy = 'id DESC', int $limit = 50, int $offset = 0): array
    {
        $where = '';
        $params = [];

        if (!empty($conditions)) {
            $clauses = [];
            foreach ($conditions as $key => $value) {
                if ($value === null) {
                    $clauses[] = "{$key} IS NULL";
                } else {
                    $clauses[] = "{$key} = ?";
                    $params[] = $value;
                }
            }
            $where = 'WHERE ' . implode(' AND ', $clauses);
        }

        $sql = "SELECT * FROM {$this->table} {$where} ORDER BY {$orderBy} LIMIT {$limit} OFFSET {$offset}";
        return $this->db->fetchAll($sql, $params);
    }

    public function findById(int|string $id): ?array
    {
        return $this->db->fetch("SELECT * FROM {$this->table} WHERE id = ?", [$id]);
    }

    public function create(array $data): string
    {
        return $this->db->insert($this->table, $data);
    }

    public function update(int|string $id, array $data): int
    {
        return $this->db->update($this->table, $data, 'id = ?', [$id]);
    }

    public function delete(int|string $id): int
    {
        return $this->db->delete($this->table, 'id = ?', [$id]);
    }

    public function count(array $conditions = []): int
    {
        $where = '';
        $params = [];

        if (!empty($conditions)) {
            $clauses = [];
            foreach ($conditions as $key => $value) {
                if ($value === null) {
                    $clauses[] = "{$key} IS NULL";
                } else {
                    $clauses[] = "{$key} = ?";
                    $params[] = $value;
                }
            }
            $where = 'WHERE ' . implode(' AND ', $clauses);
        }

        $result = $this->db->fetch("SELECT COUNT(*) as total FROM {$this->table} {$where}", $params);
        return (int)($result['total'] ?? 0);
    }

    public function exists(array $conditions): bool
    {
        return $this->count($conditions) > 0;
    }
}
