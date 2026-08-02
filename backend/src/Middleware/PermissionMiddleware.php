<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;

/**
 * Middleware Permission — sprawdza uprawnienia użytkownika per sekcja.
 * Wymaga wcześniejszego AuthMiddleware (user_id i permissions w atrybutach).
 */
final class PermissionMiddleware implements MiddlewareInterface
{
    /**
     * @param array<int, string> $requiredSections wymagane sekcje uprawnień (np. ['ustawienia'])
     * @param array<int, string> $superAdminBypass role pomijające sprawdzenie uprawnień
     */
    public function __construct(
        private readonly array $requiredSections = [],
        private readonly array $superAdminBypass = ['super_admin'],
    ) {}

    public function process(Request $request, callable $next): Response
    {
        $role = $request->attribute('role');

        if (is_string($role) && in_array($role, $this->superAdminBypass, true)) {
            return $next($request);
        }

        /** @var array<string, bool> $permissions */
        $permissions = $request->attribute('permissions');
        if (!is_array($permissions)) {
            return Response::error(403, 'No permissions found');
        }

        foreach ($this->requiredSections as $section) {
            $hasPermission = $permissions[$section] ?? false;
            if (!$hasPermission) {
                return Response::error(403, "Access denied to section: {$section}");
            }
        }

        return $next($request);
    }
}