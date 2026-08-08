import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';

import { AuthService } from '../../services/auth.service';
import { TranslatePipe } from '../../pipes/translate.pipe';
import { ButtonComponent } from '../../components/button/button.component';

/**
 * Placeholder dashboard — docelowo zaimplementowany w Etapie 13.
 */
@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, TranslatePipe, ButtonComponent],
  template: `
    <div class="p-6">
      <h1 class="text-2xl font-bold text-gray-900 mb-4">{{ 'dashboard.title' | translate }}</h1>
      <p class="text-gray-600">Sekcja dashboard — implementacja w Etapie 13.</p>
      <div class="mt-6">
        <span class="text-sm text-gray-500">Zalogowany: {{ userEmail() }}</span>
        <app-button variant="danger" [label]="'common.auth.logout'" extraClass="ml-4" (clicked)="logout()" />
      </div>
    </div>
  `,
})
export class DashboardComponent {
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);

  userEmail(): string {
    return this.authService.currentUser?.email ?? '';
  }

  logout(): void {
    this.authService.logout().subscribe({
      next: () => {},
      error: () => {},
      complete: () => {},
    });
  }
}