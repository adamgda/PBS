import { Injectable, signal } from '@angular/core';

export interface ConfirmConfig {
  title?: string;
  message: string;
  confirmText?: string;
  cancelText?: string;
  danger?: boolean;
}

export interface ConfirmState extends ConfirmConfig {
  active: boolean;
  resolve: (value: boolean) => void;
}

/**
 * Serwis potwierdzeń — globalny, oparty na sygnałach.
 * Używany przez ConfirmDialogComponent.
 * Użycie: const ok = await confirmService.confirm({ message: 'Usunąć?' });
 */
@Injectable({ providedIn: 'root' })
export class ConfirmService {
  private readonly _state = signal<ConfirmState | null>(null);
  readonly state = this._state.asReadonly();

  confirm(config: ConfirmConfig): Promise<boolean> {
    return new Promise<boolean>((resolve) => {
      this._state.set({
        ...config,
        active: true,
        resolve,
      });
    });
  }

  respond(value: boolean): void {
    const current = this._state();
    if (current) {
      current.resolve(value);
      this._state.set(null);
    }
  }
}