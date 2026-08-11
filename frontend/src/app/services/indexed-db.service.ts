import { Injectable } from '@angular/core';

/**
 * Serwis IndexedDB — lokalny store dla danych krytycznych (awaria).
 * Synchronizacja z backendem następuje gdy online.
 *
 * Dokumentacja: docs/technical-documentation.md — sekcja 14.6 PWA i offline-first.
 */
@Injectable({ providedIn: 'root' })
export class IndexedDbService {
  private readonly dbName = 'pbs-db';
  private readonly dbVersion = 1;
  private db: IDBDatabase | null = null;

  /**
   * Inicjalizacja bazy danych (tworzenie object stores).
   */
  async init(): Promise<void> {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(this.dbName, this.dbVersion);

      request.onerror = () => reject(request.error);
      request.onsuccess = () => {
        this.db = request.result;
        resolve();
      };

      request.onupgradeneeded = (event) => {
        const database = (event.target as IDBOpenDBRequest).result;
        // Store dla draftów awarii (offline-first)
        if (!database.objectStoreNames.contains('incidents_drafts')) {
          database.createObjectStore('incidents_drafts', { keyPath: 'id' });
        }
        // Store dla innych danych krytycznych (rozszerzalne)
        if (!database.objectStoreNames.contains('critical_data')) {
          database.createObjectStore('critical_data', { keyPath: 'key' });
        }
        // Store dla szybkich notatek to-do (Etap 19, offline-first)
        if (!database.objectStoreNames.contains('user_notes')) {
          database.createObjectStore('user_notes', { keyPath: 'id' });
        }
      };
    });
  }

  /**
   * Zapis do store.
   */
  async put(storeName: string, value: unknown, key?: IDBValidKey): Promise<void> {
    if (!this.db) await this.init();
    return new Promise((resolve, reject) => {
      const tx = this.db!.transaction(storeName, 'readwrite');
      const store = tx.objectStore(storeName);
      const req = key !== undefined ? store.put(value, key) : store.put(value);
      req.onsuccess = () => resolve();
      req.onerror = () => reject(req.error);
    });
  }

  /**
   * Odczyt z store.
   */
  async get<T>(storeName: string, key: IDBValidKey): Promise<T | undefined> {
    if (!this.db) await this.init();
    return new Promise((resolve, reject) => {
      const tx = this.db!.transaction(storeName, 'readonly');
      const store = tx.objectStore(storeName);
      const req = store.get(key);
      req.onsuccess = () => resolve(req.result as T | undefined);
      req.onerror = () => reject(req.error);
    });
  }

  /**
   * Pobranie wszystkich rekordów z store.
   */
  async getAll<T>(storeName: string): Promise<T[]> {
    if (!this.db) await this.init();
    return new Promise((resolve, reject) => {
      const tx = this.db!.transaction(storeName, 'readonly');
      const store = tx.objectStore(storeName);
      const req = store.getAll();
      req.onsuccess = () => resolve(req.result as T[]);
      req.onerror = () => reject(req.error);
    });
  }

  /**
   * Usunięcie rekordu.
   */
  async delete(storeName: string, key: IDBValidKey): Promise<void> {
    if (!this.db) await this.init();
    return new Promise((resolve, reject) => {
      const tx = this.db!.transaction(storeName, 'readwrite');
      const store = tx.objectStore(storeName);
      const req = store.delete(key);
      req.onsuccess = () => resolve();
      req.onerror = () => reject(req.error);
    });
  }

  /**
   * Czyszczenie store.
   */
  async clear(storeName: string): Promise<void> {
    if (!this.db) await this.init();
    return new Promise((resolve, reject) => {
      const tx = this.db!.transaction(storeName, 'readwrite');
      const store = tx.objectStore(storeName);
      const req = store.clear();
      req.onsuccess = () => resolve();
      req.onerror = () => reject(req.error);
    });
  }
}