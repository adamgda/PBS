import { CanActivateFn, Router } from '@angular/router';
import { inject } from '@angular/core';

import { AuthService } from '../services/auth.service';

/**
 * DefaultRouteGuard — kieruje użytkownika do pierwszej dostępnej sekcji
 * (na bazie uprawnień) lub do /login, gdy niezalogowany / bez uprawnień.
 *
 * Używane dla trasy domyślnej ('') oraz wildcard (**), aby nie twardo
 * przekierowywać do /dashboard (pętla dla użytkowników bez tego uprawnienia).
 */
export const DefaultRouteGuard: CanActivateFn = () => {
  const authService = inject(AuthService);
  const router = inject(Router);

  return router.createUrlTree([authService.firstAvailableRoute()]);
};
