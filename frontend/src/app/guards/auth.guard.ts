import { CanActivateFn, Router, RouterStateSnapshot } from '@angular/router';
import { inject } from '@angular/core';

import { AuthService } from '../services/auth.service';

/**
 * AuthGuard — chroni wszystkie ścieżki wymagające zalogowania.
 * Jeśli użytkownik nie jest zalogowany, przekierowuje na /login.
 */
export const AuthGuard: CanActivateFn = (
  _route,
  state: RouterStateSnapshot,
) => {
  const authService = inject(AuthService);
  const router = inject(Router);

  if (authService.isLoggedIn()) {
    return true;
  }

  // Zachowaj URL powrotu po zalogowaniu
  const returnUrl = state.url;
  return router.createUrlTree(['/login'], { queryParams: { returnUrl } });
};