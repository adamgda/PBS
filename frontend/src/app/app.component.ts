import { Component, inject, computed, signal } from '@angular/core';
import { CommonModule, NgTemplateOutlet } from '@angular/common';
import { RouterOutlet, RouterLink, RouterLinkActive, Router, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs';

import { AuthService } from './services/auth.service';
import { OfflineService } from './services/offline.service';
import { ThemeService } from './services/theme.service';
import { UpdateService } from './services/update.service';
import { TranslatePipe } from './pipes/translate.pipe';
import { ToastNotificationComponent } from './components/toast-notification/toast-notification.component';
import { ConfirmDialogComponent } from './components/confirm-dialog/confirm-dialog.component';
import { OfflineBannerComponent } from './components/offline-banner/offline-banner.component';
import { SvgIconComponent } from './components/svg-icon/svg-icon.component';
import { QuickNotesWidgetComponent } from './components/quick-notes-widget/quick-notes-widget.component';

/**
 * Główny komponent aplikacji PORTBS.
 * Zawiera layout mobile-first (górny app-bar + drawer na mobile, stały sidebar na desktop)
 * oraz globalne komponenty (toast, confirm, offline banner).
 */
@Component({
  selector: 'app-root',
  standalone: true,
  imports: [
    CommonModule,
    NgTemplateOutlet,
    RouterOutlet,
    RouterLink,
    RouterLinkActive,
    TranslatePipe,
    ToastNotificationComponent,
    ConfirmDialogComponent,
    OfflineBannerComponent,
    SvgIconComponent,
    QuickNotesWidgetComponent,
  ],
  templateUrl: './app.component.html',
  styleUrl: './app.component.css',
})
export class AppComponent {
  private readonly authService = inject(AuthService);
  private readonly offlineService = inject(OfflineService);
  private readonly themeService = inject(ThemeService);
  private readonly updateService = inject(UpdateService);
  private readonly router = inject(Router);

  readonly isLoggedIn = this.authService.isLoggedIn;
  readonly online = this.offlineService.online;

  /** Czy aktywny jest tryb ciemny (do przełącznika w headerze). */
  readonly dark = this.themeService.dark;

  toggleTheme(): void {
    this.themeService.toggle();
  }

  /** Otwarcie draweru nawigacji na mobile */
  readonly mobileOpen = signal(false);

  /** Aktualny URL (do wyznaczenia aktywnej sekcji w nagłówku) */
  readonly currentUrl = signal('');

  readonly menuItems = [
    { path: '/dashboard', label: 'common.menu.dashboard', permission: 'dashboard', icon: 'dashboard' },
    { path: '/employees', label: 'common.menu.pracownicy', permission: 'pracownicy', icon: 'pracownicy' },
    { path: '/equipment', label: 'common.menu.sprzet', permission: 'sprzet', icon: 'sprzet' },
    { path: '/terminals', label: 'common.menu.terminale', permission: 'terminale', icon: 'terminale' },
    { path: '/schedule', label: 'common.menu.harmonogram', permission: 'harmonogram', icon: 'harmonogram' },
    { path: '/analytics', label: 'common.menu.analytics', permission: 'analityka', icon: 'analytics' },
    { path: '/reporting', label: 'common.menu.reporting', permission: 'raportowanie', icon: 'reporting' },
    { path: '/exports', label: 'common.menu.export_csv', permission: 'export_csv', icon: 'export' },
    { path: '/incidents', label: 'common.menu.awaria', permission: 'awaria', icon: 'awaria' },
    { path: '/audit-logs', label: 'common.menu.logi_audytowe', permission: 'super_admin', icon: 'history' },
    { path: '/settings', label: 'common.menu.ustawienia', permission: 'ustawienia', icon: 'settings' },
  ];

  readonly visibleMenuItems = computed(() =>
    this.menuItems.filter((item) => this.authService.hasPermission(item.permission)),
  );

  /** Tytuł aktywnej sekcji wyświetlany w nagłówku (desktop) */
  readonly activeTitle = computed(() => {
    const url = this.currentUrl();
    return this.visibleMenuItems().find((item) => url.startsWith(item.path))?.label ?? '';
  });

  /** Inicjał użytkownika do awatara */
  readonly userInitial = computed(() => {
    const email = this.authService.currentUser?.email ?? '';
    return email.charAt(0).toUpperCase() || 'U';
  });

  /** E-mail zalogowanego użytkownika (do profilu) */
  readonly userEmail = computed(() => this.authService.currentUser?.email ?? '');

  constructor() {
    // Monitorowanie aktualizacji PWA — po wgraniu nowego buildu automatycznie
    // aktywuj nową wersję i przeładuj stronę (bez czyszczenia danych przeglądarki).
    this.updateService.init();

    // Aktualizacja currentUrl po każdej nawigacji — wykorzystywane do tytułu sekcji.
    this.router.events
      .pipe(filter((e): e is NavigationEnd => e instanceof NavigationEnd))
      .subscribe((e) => {
        this.currentUrl.set(e.urlAfterRedirects);
        // Po nawigacji zamykamy drawer na mobile.
        this.mobileOpen.set(false);
      });

    // Backend jako źródło prawdy — przy starcie aplikacji odśwież uprawnienia,
    // aby menu i guardy odzwierciedlały aktualny stan (np. po zmianie w adminie).
    // Best-effort: błąd (np. brak /auth/me) nie powinien blokować startu aplikacji.
    if (this.authService.isLoggedIn()) {
      this.authService.refreshCurrentUser().subscribe({ error: () => {} });
    }
  }

  toggleMobile(): void {
    this.mobileOpen.update((v) => !v);
  }

  closeMobile(): void {
    this.mobileOpen.set(false);
  }

  logout(): void {
    this.closeMobile();
    this.authService.logout().subscribe({
      error: () => {},
      complete: () => {},
    });
  }
}