import { Injectable, signal, computed } from '@angular/core';

/**
 * Słownik tłumaczeń — wczytywany z plików JSON w `locales/pl/`.
 * Struktura zagnieżdżona, klucze z kropką (np. "common.buttons.save").
 */
type TranslationDict = Record<string, unknown>;

@Injectable({ providedIn: 'root' })
export class TranslateService {
  /** Signal z aktualnym słownikiem (domyślnie pusty) */
  private readonly _translations = signal<TranslationDict>({});
  private readonly _lang = signal<string>('pl');

  /** Readonly sygnały */
  readonly lang = this._lang.asReadonly();
  readonly translations = this._translations.asReadonly();

  /**
   * Rejestracja słownika dla danej sekcji (np. "common", "dashboard").
   * Pliki JSON są importowane statycznie — brak lazy-load dla języka domyślnego.
   */
  register(key: string, data: TranslationDict): void {
    const current = this._translations();
    this._translations.set({ ...current, [key]: data });
  }

  /**
   * Rejestracja wielu sekcji naraz.
   */
  registerMany(data: TranslationDict): void {
    this._translations.set({ ...this._translations(), ...data });
  }

  /**
   * Tłumaczenie klucza (np. "common.buttons.save").
   * Obsługa interpolacji zmiennych: "Usunięto {{name}}".
   * Jeśli brak klucza, zwraca klucz (łatwiejsze debugowanie).
   */
  translate(key: string, params?: Record<string, string | number>): string {
    const dict = this._translations();
    const value = this.resolveKey(dict, key);
    if (value === undefined || value === null) {
      return key;
    }
    if (typeof value !== 'string') {
      return String(value);
    }
    return this.interpolate(value, params);
  }

  /**
   * Alias do translate() — używany w pipe.
   */
  instant(key: string, params?: Record<string, string | number>): string {
    return this.translate(key, params);
  }

  /**
   * Ustawienie języka (na razie tylko "pl", ale struktura gotowa na i18n).
   */
  setLanguage(lang: string): void {
    this._lang.set(lang);
  }

  // --- Pomocnicze ---

  private resolveKey(obj: unknown, path: string): unknown {
    return path.split('.').reduce<unknown>((acc, part) => {
      if (acc && typeof acc === 'object' && part in (acc as Record<string, unknown>)) {
        return (acc as Record<string, unknown>)[part];
      }
      return undefined;
    }, obj);
  }

  private interpolate(str: string, params?: Record<string, string | number>): string {
    if (!params) return str;
    return str.replace(/\{\{(\w+)\}\}/g, (_, name: string) => {
      return params[name] !== undefined ? String(params[name]) : `{{${name}}}`;
    });
  }
}