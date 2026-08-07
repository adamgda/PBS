import { Injectable, signal } from '@angular/core';

/**
 * Typ powiadomienia toast.
 */
export type ToastType = 'success' | 'error' | 'warning' | 'info';

export interface ToastMessage {
  id: number;
  type: ToastType;
  message: string;
  timeout?: number;
}

/**
 * Serwis powiadomień Toast — globalny, oparty na sygnałach.
 * Używany przez ToastNotificationComponent.
 */
@Injectable({ providedIn: 'root' })
export class ToastService {
  private nextId = 0;

  /** Signal z listą aktywnych powiadomień */
  private readonly _toasts = signal<ToastMessage[]>([]);
  readonly toasts = this._toasts.asReadonly();

  /** Komunikat domyślny: 4 s, error: 6 s */
  show(message: string, type: ToastType = 'info', timeout = 4000): void {
    const id = this.nextId++;
    this._toasts.update((list) => [...list, { id, type, message, timeout }]);
    if (timeout > 0) {
      setTimeout(() => this.dismiss(id), timeout);
    }
  }

  success(message: string, timeout = 4000): void {
    this.show(message, 'success', timeout);
  }

  error(message: string, timeout = 6000): void {
    this.show(message, 'error', timeout);
  }

  warning(message: string, timeout = 5000): void {
    this.show(message, 'warning', timeout);
  }

  info(message: string, timeout = 4000): void {
    this.show(message, 'info', timeout);
  }

  dismiss(id: number): void {
    this._toasts.update((list) => list.filter((t) => t.id !== id));
  }

  clear(): void {
    this._toasts.set([]);
  }
}