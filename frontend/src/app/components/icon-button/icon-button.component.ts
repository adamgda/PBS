import { Component, Input, Output, EventEmitter, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';

import { SvgIconComponent } from '../svg-icon/svg-icon.component';

export type IconButtonTone = 'default' | 'primary' | 'warning' | 'danger';
export type IconButtonSize = 'sm' | 'md';

/**
 * Współdzielony przycisk ikonowy (kwadratowy, sama ikona) — akcje w wierszach tabel,
 * przyciski zamykania modali itp. Kolor hover dobierany przez `tone`.
 *
 * Użycie:
 *   <app-icon-button icon="close" [ariaLabel]="'common.buttons.close' | translate" (clicked)="close()" />
 *   <app-icon-button icon="settings" tone="primary" [disabled]="!canManage(u)"
 *     [ariaLabel]="'...permissions' | translate" (clicked)="openPermissions(u)" />
 */
@Component({
  selector: 'app-icon-button',
  standalone: true,
  imports: [CommonModule, SvgIconComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <button
      type="button"
      [class]="classes"
      [attr.aria-label]="ariaLabel"
      [disabled]="disabled"
      (click)="clicked.emit()"
    >
      <app-svg-icon [name]="icon" />
    </button>
  `,
})
export class IconButtonComponent {
  /** Nazwa ikony (klucz switcha w SvgIconComponent). */
  @Input({ required: true }) icon = '';
  /** Tekst etykiety dostępności (ARIA) — przetłumaczony przez wywołującego (pipe w bindingu). */
  @Input() ariaLabel = '';
  /** Ton koloru hover: default (szary), primary, warning, danger. */
  @Input() tone: IconButtonTone = 'default';
  /** Rozmiar: md (h-9 w-9, domyślny) lub sm (h-8 w-8). */
  @Input() size: IconButtonSize = 'md';
  /** Blokada przycisku. */
  @Input() disabled = false;
  /** Dodatkowe klasy Tailwind — escape hatch. */
  @Input() extraClass = '';
  /** Emitowany po kliknięciu przycisku. */
  @Output() clicked = new EventEmitter<void>();

  get classes(): string {
    const sizeClass = this.size === 'sm' ? 'h-8 w-8' : 'h-9 w-9';
    const textClass = this.tone === 'default' ? 'text-gray-400' : 'text-gray-500';
    const toneClass =
      this.tone === 'primary'
        ? 'hover:text-pbs-primary'
        : this.tone === 'warning'
          ? 'hover:text-amber-600'
          : this.tone === 'danger'
            ? 'hover:text-pbs-danger'
            : '';
    return `grid ${sizeClass} place-items-center rounded-lg ${textClass} transition-colors hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40 ${toneClass} ${this.extraClass}`.trim();
  }
}