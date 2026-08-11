import { Injectable, signal } from '@angular/core';

/**
 * Serwis zarządzający motywem (jasny / ciemny).
 * - Przechowuje wybór w `localStorage` (klucz `pbs-theme`).
 * - Domyślnie respektuje preferencję systemu (`prefers-color-scheme`).
 * - Przełącza klasę `dark` na elemencie `<html>` (Tailwind `darkMode: 'class'`).
 */
@Injectable({ providedIn: 'root' })
export class ThemeService {
  private readonly STORAGE_KEY = 'pbs-theme';

  /** Czy aktywny jest tryb ciemny. */
  readonly dark = signal<boolean>(false);

  constructor() {
    this.dark.set(this.readInitial());
    this.apply();
  }

  /** Przełącza motyw i zapisuje wybór. */
  toggle(): void {
    this.dark.update((v) => !v);
    this.apply();
  }

  private readInitial(): boolean {
    try {
      const stored = localStorage.getItem(this.STORAGE_KEY);
      if (stored) return stored === 'dark';
      return window.matchMedia('(prefers-color-scheme: dark)').matches;
    } catch {
      return false;
    }
  }

  private apply(): void {
    const isDark = this.dark();
    document.documentElement.classList.toggle('dark', isDark);
    try {
      localStorage.setItem(this.STORAGE_KEY, isDark ? 'dark' : 'light');
    } catch {
      /* localStorage może być niedostępny (np. tryb prywatny) */
    }
  }
}
