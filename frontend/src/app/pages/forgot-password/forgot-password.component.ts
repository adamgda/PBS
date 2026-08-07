import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { AuthService } from '../../services/auth.service';
import { ToastService } from '../../services/toast.service';
import { TranslateService } from '../../services/translate.service';
import { TranslatePipe } from '../../pipes/translate.pipe';
import { SvgIconComponent } from '../../components/svg-icon/svg-icon.component';
import { FormInputComponent } from '../../components/form-input/form-input.component';

/**
 * Strona przypomnienia hasła — publiczna (bez AuthGuard).
 * Formularz e-mail → POST /auth/forgot-password.
 * Backend zawsze zwraca 200 (nie ujawnia czy konto istnieje).
 * Po wysłaniu pokazuje komunikat sukcesu z instrukcją sprawdzenia skrzynki.
 */
@Component({
  selector: 'app-forgot-password',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, TranslatePipe, SvgIconComponent, FormInputComponent],
  templateUrl: './forgot-password.component.html',
})
export class ForgotPasswordComponent {
  private readonly authService = inject(AuthService);
  private readonly toastService = inject(ToastService);
  private readonly translateService = inject(TranslateService);

  readonly email = signal('');
  readonly loading = signal(false);
  readonly error = signal<string | null>(null);
  readonly submitted = signal(false);
  /** Link resetujący — ustawiany tylko gdy API zwraca reset_url (tryb debug). */
  readonly resetUrl = signal<string | null>(null);

  onSubmit(): void {
    if (!this.email() || !this.isValidEmail(this.email())) {
      this.error.set(this.translateService.instant('common.validation.email'));
      return;
    }

    this.loading.set(true);
    this.error.set(null);

    this.authService.forgotPassword(this.email()).subscribe({
      next: (res) => {
        this.loading.set(false);
        this.submitted.set(true);
        // W trybie debug backend zwraca reset_url — pokazujemy link do testów dev.
        this.resetUrl.set(res.reset_url ?? null);
        this.toastService.success(this.translateService.instant('common.auth.forgot_password_success_title'));
      },
      error: () => {
        // Backend zawsze zwraca 200 dla forgot-password — błąd sieciowy traktujemy ogólnie.
        this.loading.set(false);
        const msg = this.translateService.instant('common.messages.error.generic');
        this.error.set(msg);
        this.toastService.error(msg);
      },
    });
  }

  private isValidEmail(email: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }
}