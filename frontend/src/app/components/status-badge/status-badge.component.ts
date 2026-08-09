import { Component, Input, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';

import { TranslatePipe } from '../../pipes/translate.pipe';

/** Dopuszczalne tony kolorystyczne badge'a statusu. */
export type StatusTone = 'success' | 'warning' | 'danger' | 'info' | 'neutral';

/** Mapa kanonicznych statusów → ton koloru (auto, gdy brak jawnego `tone`). */
const STATUS_TONE_MAP: Record<string, StatusTone> = {
  active: 'success',
  completed: 'success',
  repaired: 'success',
  new: 'info',
  in_progress: 'warning',
  under_repair: 'warning',
  reported: 'warning',
  inactive: 'neutral',
  closed: 'neutral',
  invited: 'warning',
  blocked: 'danger',
};

/** Tailwind: tło + tekst + obramowanie badge'a per ton. */
const TONE_BADGE_CLASSES: Record<StatusTone, string> = {
  success: 'bg-green-50 text-green-700 border-green-200',
  warning: 'bg-amber-50 text-amber-700 border-amber-200',
  danger: 'bg-red-50 text-red-700 border-red-200',
  info: 'bg-blue-50 text-blue-700 border-blue-200',
  neutral: 'bg-gray-50 text-gray-600 border-gray-200',
};

/** Tailwind: kolor kropki wskaźnika per ton. */
const TONE_DOT_CLASSES: Record<StatusTone, string> = {
  success: 'bg-green-500',
  warning: 'bg-amber-500',
  danger: 'bg-red-500',
  info: 'bg-blue-500',
  neutral: 'bg-gray-400',
};

/**
 * Współdzielony kolorowy badge statusu — pill z kropką wskaźnika.
 *
 * Ton (kolor) dobierany automatycznie z kanonicznego `status` (np. `active` → zielony,
 * `in_progress` → amber, `closed` → szary). Możliwy override przez `tone`.
 *
 * Etykieta: priorytetowo jawny `label`, następnie `labelKey` (klucz tłumaczeniowy),
 * domyślnie `common.status.{status}`.
 *
 * Użycie:
 *   <app-status-badge status="active" />
 *   <app-status-badge status="in_progress" labelKey="harmonogram.status.in_progress" />
 *   <app-status-badge [status]="user.is_active ? 'active' : 'inactive'" />
 *   <app-status-badge status="blocked" [label]="statusLabel(user)" />
 */
@Component({
  selector: 'app-status-badge',
  standalone: true,
  imports: [CommonModule, TranslatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <span
      class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-2.5 py-0.5 text-xs font-medium"
      [class]="badgeClass()"
    >
      <span class="h-1.5 w-1.5 rounded-full" [class]="dotClass()"></span>
      @if (isPlainText()) {
        {{ plainText() }}
      } @else {
        {{ translationKey() | translate }}
      }
    </span>
  `,
})
export class StatusBadgeComponent {
  /** Kanoniczny klucz statusu — determinuje ton (kolor) badge'a. */
  @Input({ required: true }) status = '';
  /** Jawna etykieta tekstowa (najwyższy priorytet). */
  @Input() label?: string;
  /** Klucz tłumaczeniowy etykiety (gdy brak `label`). Domyślnie `common.status.{status}`. */
  @Input() labelKey?: string;
  /** Jawny override tonu koloru (gdy auto-mapa z `status` jest nieodpowiednia). */
  @Input() tone?: StatusTone;

  readonly badgeClass = computed(() => TONE_BADGE_CLASSES[this.resolvedTone()]);
  readonly dotClass = computed(() => TONE_DOT_CLASSES[this.resolvedTone()]);

  readonly resolvedTone = computed<StatusTone>(() => {
    if (this.tone) {
      return this.tone;
    }
    return STATUS_TONE_MAP[this.status] ?? 'neutral';
  });

  /** Czy etykieta jest jawnym tekstem (z `label`) — renderowanym bez tłumaczenia. */
  readonly isPlainText = computed(() => this.label !== undefined && this.label !== '');

  /** Klucz tłumaczeniowy etykiety (gdy brak jawnego `label`). */
  readonly translationKey = computed(() => this.labelKey ?? 'common.status.' + this.status);

  readonly plainText = computed(() => this.label ?? '');
}