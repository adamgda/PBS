import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';

import { AuthService } from '../../services/auth.service';
import { ToastService } from '../../services/toast.service';
import { TranslateService } from '../../services/translate.service';
import { TranslatePipe } from '../../pipes/translate.pipe';

/**
 * Strona logowania — jedyna publiczna strona.
 * Po udanym logowaniu przekierowuje na returnUrl lub /dashboard.
 */
@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, FormsModule, TranslatePipe],
  template: `
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
      <div class="max-w-md w-full space-y-8">
        <div>
          <h2 class="mt-6 text-center text-3xl font-bold text-gray-900">
            {{ 'common.auth.login_title' | translate }}
          </h2>
        </div>
        <form class="mt-8 space-y-6" (ngSubmit)="onSubmit()">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700" for="email">
                {{ 'common.auth.email' | translate }}
              </label>
              <input
                id="email"
                type="email"
                name="email"
                required
                autocomplete="email"
                class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-md shadow-sm focus:ring-2 focus:ring-pbs-primary focus:border-transparent"
                [ngModel]="email()"
                (ngModelChange)="email.set($event)"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700" for="password">
                {{ 'common.auth.password' | translate }}
              </label>
              <input
                id="password"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                class="mt-1 block w-full px-3 py-2 border border-gray-200 rounded-md shadow-sm focus:ring-2 focus:ring-pbs-primary focus:border-transparent"
                [ngModel]="password()"
                (ngModelChange)="password.set($event)"
              />
            </div>
          </div>

          @if (error()) {
            <div class="text-sm text-red-600 bg-red-50 p-3 rounded-md">
              {{ error() }}
            </div>
          }

          <div>
            <button
              type="submit"
              class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-pbs-primary hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pbs-primary transition-colors disabled:opacity-50"
              [disabled]="loading()"
            >
              @if (loading()) {
                <span class="animate-pulse">...</span>
              } @else {
                {{ 'common.auth.login_button' | translate }}
              }
            </button>
          </div>
        </form>
      </div>
    </div>
  `,
})
export class LoginComponent {
  private readonly authService = inject(AuthService);
  private readonly toastService = inject(ToastService);
  private readonly translateService = inject(TranslateService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  readonly email = signal('');
  readonly password = signal('');
  readonly loading = signal(false);
  readonly error = signal<string | null>(null);

  onSubmit(): void {
    if (!this.email() || !this.password()) {
      this.error.set(this.translateService.instant('common.validation.required'));
      return;
    }

    this.loading.set(true);
    this.error.set(null);

    this.authService.login({ email: this.email(), password: this.password() }).subscribe({
      next: () => {
        this.loading.set(false);
        this.toastService.success(this.translateService.instant('common.auth.login'));
        const returnUrl = this.route.snapshot.queryParams['returnUrl'] || '/dashboard';
        this.router.navigateByUrl(returnUrl);
      },
      error: (err) => {
        this.loading.set(false);
        const msg = err?.error?.message || this.translateService.instant('common.messages.error.generic');
        this.error.set(msg);
        this.toastService.error(msg);
      },
    });
  }
}