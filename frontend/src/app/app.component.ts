import { Component, inject, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterOutlet, RouterLink, RouterLinkActive } from '@angular/router';

import { AuthService } from './services/auth.service';
import { OfflineService } from './services/offline.service';
import { TranslatePipe } from './pipes/translate.pipe';
import { ToastNotificationComponent } from './components/toast-notification/toast-notification.component';
import { ConfirmDialogComponent } from './components/confirm-dialog/confirm-dialog.component';
import { OfflineBannerComponent } from './components/offline-banner/offline-banner.component';

/**
 * Główny komponent aplikacji PBS.
 * Zawiera layout (nawigacja, outlet) i globalne komponenty (toast, confirm, offline banner).
 */
@Component({
  selector: 'app-root',
  standalone: true,
  imports: [
    CommonModule,
    RouterOutlet,
    RouterLink,
    RouterLinkActive,
    TranslatePipe,
    ToastNotificationComponent,
    ConfirmDialogComponent,
    OfflineBannerComponent,
  ],
  templateUrl: './app.component.html',
  styleUrl: './app.component.css',
})
export class AppComponent {
  private readonly authService = inject(AuthService);
  private readonly offlineService = inject(OfflineService);

  readonly isLoggedIn = this.authService.isLoggedIn;
  readonly online = this.offlineService.online;

  readonly menuItems = [
    { path: '/dashboard', label: 'common.menu.dashboard', permission: 'dashboard' },
    { path: '/pracownicy', label: 'common.menu.pracownicy', permission: 'pracownicy' },
    { path: '/sprzet', label: 'common.menu.sprzet', permission: 'sprzet' },
    { path: '/terminale', label: 'common.menu.terminale', permission: 'terminale' },
    { path: '/harmonogram', label: 'common.menu.harmonogram', permission: 'harmonogram' },
    { path: '/analityka', label: 'common.menu.analityka', permission: 'analityka' },
    { path: '/raportowanie', label: 'common.menu.raportowanie', permission: 'raportowanie' },
    { path: '/awaria', label: 'common.menu.awaria', permission: 'awaria' },
    { path: '/ustawienia', label: 'common.menu.ustawienia', permission: 'ustawienia' },
  ];

  readonly visibleMenuItems = computed(() =>
    this.menuItems.filter((item) => this.authService.hasPermission(item.permission)),
  );

  logout(): void {
    this.authService.logout().subscribe({
      error: () => {},
      complete: () => {},
    });
  }
}