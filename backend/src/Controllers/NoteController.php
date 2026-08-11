<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\NoteService;

/**
 * Kontroler szybkich notatek to-do — globalny widget (Etap 19).
 *
 * Endpointy (wymagane zalogowanie, bez uprawnienia sekcji — notatki są prywatne):
 * GET    /api/v1/notes
 * POST   /api/v1/notes
 * PATCH  /api/v1/notes/{id}
 * PATCH  /api/v1/notes/{id}/done
 * DELETE /api/v1/notes/{id}
 * DELETE /api/v1/notes
 */
final class NoteController extends Controller
{
    public function __construct(
        private readonly NoteService $noteService,
    ) {}

    /**
     * GET /api/v1/notes — lista notatek zalogowanego użytkownika.
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        return $this->json($this->noteService->list($request), 200);
    }

    /**
     * POST /api/v1/notes — utworzenie notatki.
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        $result = $this->noteService->create($request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 201);
    }

    /**
     * PATCH /api/v1/notes/{id} — edycja treści notatki.
     *
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->noteService->update($id, $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * PATCH /api/v1/notes/{id}/done — odznaczanie / cofnięcie wykonania.
     *
     * @param array<string, string> $params
     */
    public function toggleDone(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->noteService->toggleDone($id, $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * DELETE /api/v1/notes/{id} — usunięcie pojedynczej notatki.
     *
     * @param array<string, string> $params
     */
    public function destroy(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->noteService->destroy($id, $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * DELETE /api/v1/notes — czyszczenie listy (opcjonalnie ?is_done=1).
     *
     * @param array<string, string> $params
     */
    public function clear(Request $request, array $params = []): Response
    {
        $result = $this->noteService->clear($request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * Bezpiecznie wyciąga błąd z wyniku serwisu (type-safe — PHPStan level 9).
     *
     * @param array<string, mixed> $result
     */
    private function errorResponse(array $result): ?Response
    {
        if (!array_key_exists('error', $result)) {
            return null;
        }

        $code = $result['code'] ?? 500;
        $message = $result['error'];

        if (is_int($code) && is_string($message)) {
            return $this->error($code, $message);
        }

        return $this->error(500, 'Unexpected error');
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
