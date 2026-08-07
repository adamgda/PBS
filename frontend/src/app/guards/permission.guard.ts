import { CanActivateFn, Router, RouterStateSnapshot } from '@angular/router';
import { inject } from '@angular/core';

import { AuthService } from '../services/auth.service';

/**
 * PermissionGuard — sprawdza uprawnienia użytkownika do danej sekcji.
 *
 * Użycie w routes:
 *   { path: 'pracownicy', canActivate: [PermissionGuard], data: { permission: 'pracownicy' } }
 *
 * super_admin ma dostęp do wszystkiego.
 * Pozostali użytkownicy muszą mieć `permissions[section] === true`.
 */
export const PermissionGuard: CanActivateFn = (
  route,
  state: RouterStateSnapshot,
) => {
  const authService = inject(AuthService);
  const router = inject(Router);

  const requiredPermission = route.data?.['permission'] as string | undefined;

  if (!requiredPermission) {
    // Brak wymogu uprawnień — tylko zalogowanie
    return true;
  }

  if (!authService.isLoggedIn()) {
    return router.createUrlTree(['/login'], { queryParams: { returnUrl: state.url } });
  }

  if (authService.hasPermission(requiredPermission)) {
    return true;
  }

  // Brak uprawnień — przekierowanie na dashboard lub stronę 403
  return router.createUrlTree(['/dashboard'], {
    queryParams: { error: 'no_permission' },
  });
};