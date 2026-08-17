import { Component } from '@angular/core';

/**
 * Komponent-fallback dla trasy domyślnej (`''`) oraz wildcard (`**`).
 *
 * Nigdy się nie renderuje — `DefaultRouteGuard` zawsze zwraca `UrlTree`
 * (przekierowanie do pierwszej dostępnej sekcji na bazie uprawnień, lub /login).
 * Obecność komponentu jest wymagana przez walidację konfiguracji routingu
 * (NG04014), gdy trasa nie używa `redirectTo`/`children`/`loadChildren`.
 */
@Component({
  selector: 'app-redirect',
  standalone: true,
  template: '',
})
export class RedirectComponent {}
