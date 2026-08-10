import { Component, Input, Output, EventEmitter, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';

import { SvgIconComponent } from '../svg-icon/svg-icon.component';
import { TranslatePipe } from '../../pipes/translate.pipe';

/**
 * Współdzielony przycisk „Dodaj …” — ikona plusa + etykieta (klucz tłumaczeniowy).
 * Styl zgodny z konwencją Tailwind + standalone components (primary action).
 *
 * Użycie:
 *   <app-add-button [label]="'ustawienia.users.add'" (add)="openCreate()" />
 */
@Component({
  selector: 'app-add-button',
  standalone: true,
  imports: [CommonModule, SvgIconComponent, TranslatePipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <button
      type="button"
      class="inline-flex items-center gap-2 rounded-lg bg-pbs-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all duration-150 hover:bg-pbs-navy-600 hover:shadow disabled:cursor-not-allowed disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-pbs-primary focus-visible:ring-offset-2"
      [disabled]="disabled"
      (click)="add.emit()"
    >
      <app-svg-icon name="plus" />
      <span>{{ label | translate }}</span>
    </button>
  `,
})
export class AddButtonComponent {
  /** Klucz tłumaczeniowy etykiety przycisku (domyślnie ogólne „Dodaj”). */
  @Input() label = 'common.buttons.add';
  /** Blokada przycisku (np. podczas zapisu). */
  @Input() disabled = false;
  /** Emitowany po kliknięciu przycisku. */
  @Output() add = new EventEmitter<void>();
}