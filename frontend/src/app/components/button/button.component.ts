import { Component, Input, Output, EventEmitter, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';

import { TranslatePipe } from '../../pipes/translate.pipe';

export type ButtonVariant = 'primary' | 'secondary' | 'outline' | 'danger' | 'block';

/**
 * Współdzielony przycisk tekstowy z wariantami stylowania.
 * Etykieta może być podana jako klucz tłumaczeniowy (input `label`) LUB przez content projection
 * (`<ng-content>`) — przydatne dla złożonej treści (np. spinner + tekst).
 *
 * Warianty:
 *  - primary   — akcja główna (wypełniony, pbs-primary)
 *  - secondary — akcja poboczna (wypełniony szary)
 *  - outline   — akcja poboczna z obrysem (np. Anuluj w modalach)
 *  - danger    — akcja destruktywna (pbs-danger)
 *  - block     — pełnoszerokościowy CTA (np. submit w formularzach auth)
 *
 * Użycie:
 *   <app-button [label]="'common.buttons.filter'" variant="primary" (clicked)="onApply()" />
 *   <app-button [label]="'common.buttons.cancel'" variant="outline" (clicked)="close()" />
 *   <app-button type="submit" variant="block" [disabled]="loading()">
 *     @if (loading()) { <app-svg-icon name="spinner" /> {{ '...loading' | translate }} }
 *     @else { {{ '...button' | translate }} }
 *   </app-button>
 */
@Component({
  selector: 'app-button',
  standalone: true,
  imports: [CommonModule, TranslatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <button
      [type]="type"
      [disabled]="disabled"
      [class]="classes"
      (click)="clicked.emit()"
    >
      @if (label) {
        <span>{{ label | translate }}</span>
      }
      <ng-content />
    </button>
  `,
})
export class ButtonComponent {
  /** Klucz tłumaczeniowy etykiety przycisku (pusty, gdy treść przez ng-content). */
  @Input() label = '';
  /** Wariant stylu — patrz dokumentacja klasy. */
  @Input() variant: ButtonVariant = 'primary';
  /** Typ przycisku HTML. */
  @Input() type: 'button' | 'submit' = 'button';
  /** Rozmiar przycisku — `sm` (kompaktowy, np. „Przypisz" w listach) lub `md` (domyślny). */
  @Input() size: 'md' | 'sm' = 'md';
  /** Blokada przycisku. */
  @Input() disabled = false;
  /** Dodatkowe klasy Tailwind (np. marginesy 'mt-8', 'ml-4') — escape hatch dla rozmieszczenia. */
  @Input() extraClass = '';
  /** Emitowany po kliknięciu przycisku. */
  @Output() clicked = new EventEmitter<void>();

  private readonly baseClasses =
    'inline-flex items-center justify-center gap-2 whitespace-nowrap font-medium rounded-lg transition-all duration-150 disabled:cursor-not-allowed disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-pbs-primary focus-visible:ring-offset-2';

  get classes(): string {
    const variantClass = (() => {
      switch (this.variant) {
        case 'secondary':
          return 'bg-gray-100 text-gray-700 shadow-sm hover:bg-gray-200';
        case 'outline':
          return 'border border-gray-200 bg-white text-gray-700 shadow-sm hover:bg-gray-50 hover:border-gray-300';
        case 'danger':
          return 'bg-pbs-danger text-white shadow-sm hover:bg-red-600 hover:shadow';
        case 'block':
          return 'flex w-full items-center justify-center gap-2 rounded-xl bg-pbs-primary px-4 py-2.5 text-sm font-medium text-white shadow-sm transition-all duration-150 hover:bg-pbs-navy-600 hover:shadow disabled:cursor-not-allowed disabled:opacity-60 focus:outline-none focus-visible:ring-2 focus-visible:ring-pbs-primary focus-visible:ring-offset-2';
        default:
          return 'bg-pbs-primary text-white shadow-sm hover:bg-pbs-navy-600 hover:shadow';
      }
    })();
    const base = this.variant === 'block' ? '' : this.baseClasses;
    const sizeClass = this.variant === 'block' ? '' : this.size === 'sm' ? 'px-2.5 py-1 text-xs' : 'px-4 py-2 text-sm';
    return `${base} ${sizeClass} ${variantClass} ${this.extraClass}`.trim();
  }
}