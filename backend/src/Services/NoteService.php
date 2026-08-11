<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Request;
use App\Repository\UserNoteRepository;

/**
 * Serwis szybkich notatek to-do — globalny widget (Etap 19).
 *
 * Notatki są prywatne i przypisane wyłącznie do zalogowanego konta (user_id
 * z JWT). Każda operacja weryfikuje własność notatki (IDOR protection).
 */
final class NoteService
{
    private const int MAX_TRESC_LENGTH = 500;

    public function __construct(
        private readonly UserNoteRepository $noteRepository,
    ) {}

    /**
     * Lista notatek zalogowanego użytkownika.
     *
     * Filtry: `?is_done=` (0/1, true/false), sortowanie `?sort=&direction=`.
     *
     * @return array{data: array<int, array<string, mixed>>}
     */
    public function list(Request $request): array
    {
        $userId = $this->requireUserId($request);
        $isDone = $this->parseIsDone($request->query()['is_done'] ?? null);
        $sort = is_string($request->query()['sort'] ?? null) ? $request->query()['sort'] : 'kolejnosc';
        $direction = is_string($request->query()['direction'] ?? null) ? $request->query()['direction'] : 'asc';

        $rows = $this->noteRepository->findAllForUser($userId, $isDone, $sort, $direction);

        return [
            'data' => array_map(fn (array $row): array => $this->toDto($row), $rows),
        ];
    }

    /**
     * Utworzenie notatki.
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function create(Request $request): array
    {
        $userId = $this->requireUserId($request);
        $body = $request->body();

        $tresc = is_string($body['tresc'] ?? null) ? trim($body['tresc']) : '';
        $kolejnosc = $this->toInt($body['kolejnosc'] ?? 0, 0);

        $validation = $this->validateTresc($tresc);
        if ($validation !== null) {
            return $validation;
        }

        $note = $this->noteRepository->create([
            'user_id' => $userId,
            'tresc' => $tresc,
            'is_done' => 0,
            'kolejnosc' => $kolejnosc,
        ]);

        return $this->toDto($note);
    }

    /**
     * Edycja treści notatki.
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function update(int $id, Request $request): array
    {
        $userId = $this->requireUserId($request);
        $existing = $this->noteRepository->findByIdForUser($id, $userId);
        if ($existing === null) {
            return ['error' => 'Note not found', 'code' => 404];
        }

        $body = $request->body();
        $tresc = is_string($body['tresc'] ?? null) ? trim($body['tresc']) : '';

        $validation = $this->validateTresc($tresc);
        if ($validation !== null) {
            return $validation;
        }

        $note = $this->noteRepository->updateForUser($id, $userId, ['tresc' => $tresc]);

        return $note === null ? ['error' => 'Note not found', 'code' => 404] : $this->toDto($note);
    }

    /**
     * Odznaczanie / cofnięcie flagi `is_done`.
     * Jeśli w body nie podano `is_done`, flaga jest przełączana (toggle).
     *
     * @return array<string, mixed>|array{error: string, code: int}
     */
    public function toggleDone(int $id, Request $request): array
    {
        $userId = $this->requireUserId($request);
        $existing = $this->noteRepository->findByIdForUser($id, $userId);
        if ($existing === null) {
            return ['error' => 'Note not found', 'code' => 404];
        }

        $body = $request->body();
        $isDone = array_key_exists('is_done', $body)
            ? (bool) $body['is_done']
            : !((bool) ($existing['is_done'] ?? false));

        $note = $this->noteRepository->updateForUser($id, $userId, [
            'is_done' => $isDone ? 1 : 0,
        ]);

        return $note === null ? ['error' => 'Note not found', 'code' => 404] : $this->toDto($note);
    }

    /**
     * Usunięcie pojedynczej notatki.
     *
     * @return array{success: bool}|array{error: string, code: int}
     */
    public function destroy(int $id, Request $request): array
    {
        $userId = $this->requireUserId($request);

        if (!$this->noteRepository->deleteForUser($id, $userId)) {
            return ['error' => 'Note not found', 'code' => 404];
        }

        return ['success' => true];
    }

    /**
     * Czyszczenie listy notatek użytkownika.
     * Opcjonalny filtr `?is_done=1` — usuwa wyłącznie wykonane.
     *
     * @return array{success: bool, deleted: int}
     */
    public function clear(Request $request): array
    {
        $userId = $this->requireUserId($request);
        $isDone = $this->parseIsDone($request->query()['is_done'] ?? null);

        $deleted = $this->noteRepository->clearForUser($userId, $isDone);

        return ['success' => true, 'deleted' => $deleted];
    }

    /**
     * @return array{error: string, code: int}|null
     */
    private function validateTresc(string $tresc): ?array
    {
        if ($tresc === '') {
            return ['error' => 'Note content is required', 'code' => 422];
        }
        if (mb_strlen($tresc) > self::MAX_TRESC_LENGTH) {
            return [
                'error' => sprintf('Note content must not exceed %d characters', self::MAX_TRESC_LENGTH),
                'code' => 422,
            ];
        }

        return null;
    }

    /**
     * Parsuje filtr is_done (acceptuje '1', '0', 'true', 'false') lub zwraca null.
     */
    private function parseIsDone(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value === '1' || $value === 'true' || $value === 'TRUE') {
            return true;
        }
        if ($value === '0' || $value === 'false' || $value === 'FALSE') {
            return false;
        }

        return null;
    }

    private function requireUserId(Request $request): int
    {
        $value = $request->attribute('user_id');
        if (is_numeric($value)) {
            return (int) $value;
        }

        throw new \RuntimeException('Missing user_id attribute');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function toDto(array $row): array
    {
        return [
            'id' => $this->toInt($row['id'] ?? 0, 0),
            'user_id' => $this->toInt($row['user_id'] ?? 0, 0),
            'tresc' => is_string($row['tresc'] ?? null) ? $row['tresc'] : '',
            'is_done' => (bool) ($row['is_done'] ?? false),
            'kolejnosc' => $this->toInt($row['kolejnosc'] ?? 0, 0),
            'created_at' => is_string($row['created_at'] ?? null) ? $row['created_at'] : null,
            'updated_at' => is_string($row['updated_at'] ?? null) ? $row['updated_at'] : null,
        ];
    }

    private function toInt(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }
}
