import { Component, inject, signal, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink, ActivatedRoute } from '@angular/router';

import { AuthService } from '../../services/auth.service';
import { ToastService } from '../../services/toast.service';
import { TranslateService } from '../../services/translate.service';
import { TranslatePipe } from '../../pipes/translate.pipe';
import { SvgIconComponent } from '../../components/svg-icon/svg-icon.component';
import { FormInputComponent } from '../../components/form-input/form-input.component';
import { ButtonComponent } from '../../components/button/button.component';

/**
 * Strona ustawiania nowego hasła — publiczna (bez AuthGuard).
 * Token pochodzi z linku w e-mailu resetującym (query param `?token=...`).
 *
 * Polityka haseł (zgodna z backendem):
 *  - min. 12 znaków
 *  - min. 3 z 4 klas: małe, duże, cyfry, znaki specjalne
 *  - max 128 znaków, brak popularnych (egzekwowane przez backend)
 *
 * Stany:
 *  - brak tokenu w URL → komunikat o niekompletnym linku
 *  - formularz → pola hasło + potwierdzenie + wskaźnik siły
 *  - błąd backendu (token wygasły/zużyty) → komunikat + link do forgot-password
 *  - sukces → komunikat + przekierowanie na /login
 */
@Component({
  selector: 'app-set-password',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, TranslatePipe, SvgIconComponent, FormInputComponent, ButtonComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './set-password.component.html',
})
export class SetPasswordComponent {
  private readonly authService = inject(AuthService);
  private readonly toastService = inject(ToastService);
  private readonly translateService = inject(TranslateService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  readonly password = signal('');
  readonly confirmPassword = signal('');
  readonly loading = signal(false);
  readonly error = signal<string | null>(null);
  readonly success = signal(false);

  /** Token odczytany z query string (`?token=...`). Pusty = niekompletny link. */
  readonly token = signal<string | null>(this.readToken());

  // --- Wskaźnik siły hasła (signals computed) ---

  readonly hasMinLength = computed(() => this.password().length >= 12);
  readonly hasUppercase = computed(() => /[A-Z]/.test(this.password()));
  readonly hasLowercase = computed(() => /[a-z]/.test(this.password()));
  readonly hasDigit = computed(() => /[0-9]/.test(this.password()));
  readonly hasSpecial = computed(() => /[^A-Za-z0-9]/.test(this.password()));

  /** Liczba spełnionych klas znaków (z 4). */
  readonly classesCount = computed(
    () =>
      Number(this.hasUppercase()) +
      Number(this.hasLowercase()) +
      Number(this.hasDigit()) +
      Number(this.hasSpecial()),
  );

  /** Czy hasło spełnia lokalną politykę (walidacja po stronie klienta). */
  readonly passwordValid = computed(() => this.hasMinLength() && this.classesCount() >= 3);

  /** Poziom siły (0–3) do paska postępu. */
  readonly strengthLevel = computed(() => {
    const p = this.password();
    if (p.length === 0) return 0;
    if (!this.hasMinLength() || this.classesCount() < 2) return 1; // słabe
    if (this.classesCount() < 3) return 2; // średnie
    return 3; // silne
  });

  readonly strengthBarWidth = computed(() => `${(this.strengthLevel() / 3) * 100}%`);
  readonly strengthLabelKey = computed(() => {
    switch (this.strengthLevel()) {
      case 1:
        return 'common.auth.password_strength_weak';
      case 2:
        return 'common.auth.password_strength_medium';
      case 3:
        return 'common.auth.password_strength_strong';
      default:
        return 'common.auth.password_strength';
    }
  });

  onSubmit(): void {
    if (!this.token()) {
      this.error.set(this.translateService.instant('common.auth.set_password_no_token_text'));
      return;
    }

    if (!this.passwordValid()) {
      this.error.set(this.translateService.instant('common.auth.password_strength_classes'));
      return;
    }

    if (this.password() !== this.confirmPassword()) {
      this.error.set(this.translateService.instant('common.auth.set_password_mismatch'));
      return;
    }

    this.loading.set(true);
    this.error.set(null);

    this.authService.setPassword(this.token()!, this.password()).subscribe({
      next: () => {
        this.loading.set(false);
        this.success.set(true);
        this.toastService.success(this.translateService.instant('common.auth.set_password_success_title'));
      },
      error: (err) => {
        this.loading.set(false);
        // Backend zwraca { error: "..." } — odczytujemy komunikat.
        const msg = err?.error?.error || this.translateService.instant('common.messages.error.generic');
        this.error.set(msg);
        this.toastService.error(msg);
      },
    });
  }

  goToLogin(): void {
    this.router.navigateByUrl('/login');
  }

  private readToken(): string | null {
    const t = this.route.snapshot.queryParamMap.get('token');
    return t && t.length > 0 ? t : null;
  }
}