<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Bazowe repozytorium — dostarcza operacje CRUD na PDO z prepared statements.
 * Konkretne repozytoria dziedziczą i ustawiają $table oraz $primaryKey.
 */
abstract class BaseRepository implements RepositoryInterface
{
    protected readonly PDO $pdo;
    protected string $table = '';
    protected string $primaryKey = 'id';

    /**
     * @var array<string, int> Mapa kolumna => typ PDO (PDO::PARAM_*)
     */
    protected array $columnTypes = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param array<string, mixed> $conditions
     * @return array<int, array<string, mixed>>
     */
    public function findAll(array $conditions = [], int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT * FROM `{$this->table}`";
        $where = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            $where[] = "`{$column}` = :{$column}";
            $params[":{$column}"] = $value;
        }

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= " LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->executeQuery($sql, $params);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id LIMIT 1";
        $stmt = $this->executeQuery($sql, [':id' => $id]);

        /** @var array<string, mixed>|false */
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $col) => ":{$col}", $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $this->table,
            implode('`, `', $columns),
            implode(', ', $placeholders),
        );

        $this->executeQuery($sql, $this->prefixParams($data));

        $id = (int) $this->pdo->lastInsertId();

        return $this->findById($id) ?? ['id' => $id];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function update(int $id, array $data): ?array
    {
        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = "`{$column}` = :{$column}";
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE `%s` = :id',
            $this->table,
            implode(', ', $set),
            $this->primaryKey,
        );

        $params = $this->prefixParams($data);
        $params[':id'] = $id;

        $this->executeQuery($sql, $params);

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id";

        $stmt = $this->pdo->prepare($sql);
        $this->bindParams($stmt, [':id' => $id]);

        return $stmt->execute();
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function executeQuery(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bindParams($stmt, $params);
            $stmt->execute();

            return $stmt;
        } catch (PDOException $e) {
            throw new \RuntimeException('Database query failed: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function bindParams(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $column = ltrim($key, ':');
            $type = $this->columnTypes[$column] ?? $this->inferType($value);
            $stmt->bindValue($key, $value, $type);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function prefixParams(array $data): array
    {
        $params = [];
        foreach ($data as $column => $value) {
            $params[":{$column}"] = $value;
        }

        return $params;
    }

    protected function inferType(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }
}