<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Interfejs repozytorium — kontrakt dla warstwy dostępu do danych.
 */
interface RepositoryInterface
{
    /**
     * @param array<string, mixed> $conditions
     * @return array<int, array<string, mixed>>
     */
    public function findAll(array $conditions = [], int $limit = 100, int $offset = 0): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function update(int $id, array $data): ?array;

    public function delete(int $id): bool;
}