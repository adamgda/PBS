<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\UserService;

/**
 * Kontroler sekcji Użytkownicy (Ustawienia → Użytkownicy) — endpointy:
 * GET    /api/v1/users
 * POST   /api/v1/users
 * GET    /api/v1/users/{id}
 * PUT    /api/v1/users/{id}
 * PATCH  /api/v1/users/{id}/permissions
 * DELETE /api/v1/users/{id}
 *
 * Wymaga uprawnienia sekcji `ustawienia` (wymuszane przez PermissionMiddleware na trasie).
 */
final class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    /**
     * GET /api/v1/users — lista użytkowników z paginacją i filtrami.
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $query = $request->query();

        $filters = [
            'email' => is_string($query['email'] ?? null) ? (string) $query['email'] : '',
            'role' => is_string($query['role'] ?? null) ? (string) $query['role'] : '',
            'is_active' => is_string($query['is_active'] ?? null) ? (string) $query['is_active'] : '',
            'sort' => is_string($query['sort'] ?? null) ? (string) $query['sort'] : 'id',
            'direction' => is_string($query['direction'] ?? null) ? (string) $query['direction'] : 'asc',
        ];

        $page = $this->toInt($query['page'] ?? 1, 1);
        $perPage = $this->toInt($query['per_page'] ?? 25, 25);

        $result = $this->userService->list($filters, $page, $perPage);

        return $this->json($result, 200);
    }

    /**
     * POST /api/v1/users — utworzenie użytkownika (email → link do set-password).
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        $body = $request->body();
        $email = is_string($body['email'] ?? null) ? $body['email'] : '';
        $role = is_string($body['role'] ?? null) ? $body['role'] : 'user';
        $permissions = is_array($body['permissions'] ?? null) ? $body['permissions'] : [];

        if ($email === '') {
            return $this->error(422, 'Email is required');
        }

        $result = $this->userService->create($email, $role, $permissions, $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 201);
    }

    /**
     * GET /api/v1/users/{id} — szczegóły użytkownika.
     *
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->userService->get($id);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * PUT /api/v1/users/{id} — edycja użytkownika (email, role).
     *
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $body = $request->body();
        $email = is_string($body['email'] ?? null) ? $body['email'] : '';
        $role = is_string($body['role'] ?? null) ? $body['role'] : 'user';
        $isActive = array_key_exists('is_active', $body) ? (bool) $body['is_active'] : null;

        if ($email === '') {
            return $this->error(422, 'Email is required');
        }

        $result = $this->userService->update($id, $email, $role, $request, $isActive);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * PATCH /api/v1/users/{id}/permissions — aktualizacja uprawnień per sekcja.
     *
     * @param array<string, string> $params
     */
    public function permissions(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $body = $request->body();
        $permissions = is_array($body['permissions'] ?? null) ? $body['permissions'] : [];

        $result = $this->userService->updatePermissions($id, $permissions, $request);

        $err = $this->errorResponse($result);
        if ($err !== null) {
            return $err;
        }

        return $this->json($result, 200);
    }

    /**
     * DELETE /api/v1/users/{id} — usunięcie użytkownika.
     *
     * @param array<string, string> $params
     */
    public function destroy(Request $request, array $params = []): Response
    {
        $id = $this->toInt($params['id'] ?? 0, 0);
        $result = $this->userService->delete($id, $request);

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