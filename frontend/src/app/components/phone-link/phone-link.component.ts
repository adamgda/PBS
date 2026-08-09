import { Component, Input, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';

import { SvgIconComponent } from '../svg-icon/svg-icon.component';

/**
 * Współdzielony, klikalny link telefonu (`tel:` URI) z ikoną.
 *
 * Gdy wartość jest pusta/null, renderuje neutralny placeholder „—"
 * (zamiast pustej komórki/broken link).
 *
 * Href jest sanitizowany — usuwane spacje, zostawiane cyfry, `+`, `-`.
 *
 * Użycie:
 *   <app-phone-link [value]="terminal.telefon_operatora" />
 *   <app-phone-link [value]="+48 58 123 45 67" />
 */
@Component({
  selector: 'app-phone-link',
  standalone: true,
  imports: [CommonModule, SvgIconComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (hasValue) {
      <a
        [href]="href"
        class="inline-flex items-center gap-1 text-pbs-secondary hover:underline"
        [attr.aria-label]="ariaLabel"
      >
        @if (icon) {
          <app-svg-icon name="phone" size="sm" />
        }
        <span>{{ value?.trim() }}</span>
      </a>
    } @else {
      <span class="text-gray-400">—</span>
    }
  `,
})
export class PhoneLinkComponent {
  /** Numer telefonu do wyrenderowania jako link `tel:`. */
  @Input() value?: string | null;
  /** Czy pokazywać ikonę telefonu (domyślnie tak). */
  @Input() icon = true;

  get hasValue(): boolean {
    return !!this.value && this.value.trim() !== '';
  }

  get href(): string {
    const raw = (this.value ?? '').trim();
    // tel: URI — usuń białe znaki, zachowaj cyfry, +, -, (, )
    const clean = raw.replace(/\s+/g, '');
    return 'tel:' + clean;
  }

  get ariaLabel(): string {
    return 'tel: ' + (this.value ?? '');
  }
}