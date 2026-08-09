import { Component, Input, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';

import { SvgIconComponent } from '../svg-icon/svg-icon.component';

/**
 * Współdzielony, klikalny link e-mail (`mailto:` URI) z ikoną.
 *
 * Gdy wartość jest pusta/null, renderuje neutralny placeholder „—"
 * (zamiast pustej komórki/broken link).
 *
 * Użycie:
 *   <app-email-link [value]="terminal.email_operatora" />
 *   <app-email-link [value]="'kontakt@operator.pl'" />
 */
@Component({
  selector: 'app-email-link',
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
          <app-svg-icon name="mail" size="sm" />
        }
        <span class="break-all">{{ value?.trim() }}</span>
      </a>
    } @else {
      <span class="text-gray-400">—</span>
    }
  `,
})
export class EmailLinkComponent {
  /** Adres e-mail do wyrenderowania jako link `mailto:`. */
  @Input() value?: string | null;
  /** Czy pokazywać ikonę e-mail (domyślnie tak). */
  @Input() icon = true;

  get hasValue(): boolean {
    return !!this.value && this.value.trim() !== '';
  }

  get href(): string {
    return 'mailto:' + (this.value ?? '').trim();
  }

  get ariaLabel(): string {
    return 'mailto: ' + (this.value ?? '');
  }
}