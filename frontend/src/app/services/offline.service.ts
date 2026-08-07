import { Injectable, signal, inject } from '@angular/core';
import { HttpClient, HttpRequest, HttpResponse } from '@angular/common/http';
import { Observable, from, of, throwError } from 'rxjs';

/**
 * Żądanie w kolejce background sync (POST/PUT/DELETE).
 */
interface QueuedRequest {
  id: string;
  method: string;
  url: string;
  body: unknown;
  headers: Record<string, string>;
  timestamp: number;
}

/**
 * Serwis offline — zarządza statusem połączenia i kolejką żądań (background sync).
 *
 * Dokumentacja: docs/technical-documentation.md — sekcja 14.6 PWA i offline-first.
 */
@Injectable({ providedIn: 'root' })
export class OfflineService {
  private readonly http = inject(HttpClient);

  /** Signal statusu online */
  private readonly _online = signal<boolean>(navigator.onLine);
  readonly online = this._online.asReadonly();

  /** Signal z listą żądań w kolejce */
  private readonly _queue = signal<QueuedRequest[]>(this.loadQueue());
  readonly queue = this._queue.asReadonly();

  /** Signal z informacją o trwającej synchronizacji */
  private readonly _syncing = signal<boolean>(false);
  readonly syncing = this._syncing.asReadonly();

  constructor() {
    window.addEventListener('online', () => {
      this._online.set(true);
      this.processQueue();
    });
    window.addEventListener('offline', () => {
      this._online.set(false);
    });

    // Próba synchronizacji przy starcie, jeśli jesteśmy online
    if (this._online() && this._queue().length > 0) {
      this.processQueue();
    }
  }

  /**
   * Czy jesteśmy offline.
   */
  isOffline(): boolean {
    return !this._online();
  }

  /**
   * Dodanie żądania do kolejki (gdy offline).
   */
  enqueue(req: HttpRequest<unknown>): void {
    const queued: QueuedRequest = {
      id: crypto.randomUUID(),
      method: req.method,
      url: req.url,
      body: req.body,
      headers: req.headers.keys().reduce<Record<string, string>>((acc, key) => {
        acc[key] = req.headers.get(key) ?? '';
        return acc;
      }, {}),
      timestamp: Date.now(),
    };
    this._queue.update((q) => [...q, queued]);
    this.saveQueue();
  }

  /**
   * Przetwarzanie kolejki — wysyłka żądań po odzyskaniu połączenia.
   */
  async processQueue(): Promise<void> {
    if (this._syncing() || this.isOffline()) return;
    const queue = this._queue();
    if (queue.length === 0) return;

    this._syncing.set(true);
    const remaining: QueuedRequest[] = [];

    for (const item of queue) {
      try {
        await this.replayRequest(item);
      } catch {
        // Jeśli błąd sieci, zostaw w kolejce
        remaining.push(item);
      }
    }

    this._queue.set(remaining);
    this.saveQueue();
    this._syncing.set(false);
  }

  private replayRequest(item: QueuedRequest): Promise<unknown> {
    let req$: Observable<unknown>;
    const body = item.body;
    switch (item.method) {
      case 'POST':
        req$ = this.http.post(item.url, body, { headers: item.headers });
        break;
      case 'PUT':
        req$ = this.http.put(item.url, body, { headers: item.headers });
        break;
      case 'PATCH':
        req$ = this.http.patch(item.url, body, { headers: item.headers });
        break;
      case 'DELETE':
        req$ = this.http.delete(item.url, { headers: item.headers });
        break;
      default:
        return Promise.reject(new Error(`Method ${item.method} not supported`));
    }
    return new Promise((resolve, reject) => {
      req$.subscribe({ next: resolve, error: reject });
    });
  }

  private saveQueue(): void {
    localStorage.setItem('pbs_offline_queue', JSON.stringify(this._queue()));
  }

  private loadQueue(): QueuedRequest[] {
    try {
      const data = localStorage.getItem('pbs_offline_queue');
      return data ? (JSON.parse(data) as QueuedRequest[]) : [];
    } catch {
      return [];
    }
  }
}