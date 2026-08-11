<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/**
 * Repozytorium szybkich notatek to-do — dostęp do tabeli `user_notes`.
 *
 * Etap 19 — Notatki są prywatne i przypisane wyłącznie do konta (user_id).
 * Każda operacja jest domyślnie ograniczona do właściciela (IDOR protection).
 */
class UserNoteRepository extends BaseRepository
{
    protected string $table = 'user_notes';
    protected string $primaryKey = 'id';

    /** @var array<string, int> */
    protected array $columnTypes = [
        'user_id' => PDO::PARAM_INT,
        'is_done' => PDO::PARAM_BOOL,
        'kolejnosc' => PDO::PARAM_INT,
    ];

    /**
     * Lista notatek użytkownika z opcjonalnym filtrem `is_done` i sortowaniem.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAllForUser(int $userId, ?bool $isDone, string $sort, string $direction): array
    {
        $allowedSort = ['id', 'tresc', 'is_done', 'kolejnosc', 'created_at', 'updated_at'];
        $sortColumn = in_array($sort, $allowedSort, true) ? $sort : 'kolejnosc';
        $dir = $direction === 'desc' ? 'DESC' : 'ASC';

        $sql = 'SELECT * FROM `user_notes` WHERE `user_id` = :user_id';
        $params = [':user_id' => $userId];

        if ($isDone !== null) {
            $sql .= ' AND `is_done` = :is_done';
            $params[':is_done'] = $isDone ? 1 : 0;
        }

        $sql .= " ORDER BY `{$sortColumn}` {$dir}, `id` ASC";

        $stmt = $this->executeQuery($sql, $params);

        /** @var array<int, array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * Wyszukuje notatkę po ID, ale wyłącznie jeśli należy do danego użytkownika.
     *
     * @return array<string, mixed>|null
     */
    public function findByIdForUser(int $id, int $userId): ?array
    {
        $sql = 'SELECT * FROM `user_notes` WHERE `id` = :id AND `user_id` = :user_id LIMIT 1';
        $stmt = $this->executeQuery($sql, [':id' => $id, ':user_id' => $userId]);

        /** @var array<string, mixed>|false $result */
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * Aktualizuje notatkę wyłącznie jeśli należy do użytkownika.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function updateForUser(int $id, int $userId, array $data): ?array
    {
        $existing = $this->findByIdForUser($id, $userId);
        if ($existing === null) {
            return null;
        }

        return $this->update($id, $data);
    }

    /**
     * Usuwa pojedynczą notatkę wyłącznie jeśli należy do użytkownika.
     */
    public function deleteForUser(int $id, int $userId): bool
    {
        $existing = $this->findByIdForUser($id, $userId);
        if ($existing === null) {
            return false;
        }

        return $this->delete($id);
    }

    /**
     * Czyści listę notatek użytkownika (wszystkie lub wyłącznie wykonane).
     *
     * Zwraca liczbę usuniętych rekordów.
     */
    public function clearForUser(int $userId, ?bool $isDone): int
    {
        $sql = 'DELETE FROM `user_notes` WHERE `user_id` = :user_id';
        $params = [':user_id' => $userId];

        if ($isDone !== null) {
            $sql .= ' AND `is_done` = :is_done';
            $params[':is_done'] = $isDone ? 1 : 0;
        }

        $stmt = $this->executeQuery($sql, $params);

        return $stmt->rowCount();
    }
}
