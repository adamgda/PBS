import { Injectable, inject, signal, effect } from '@angular/core';
import { HttpClient, HttpRequest } from '@angular/common/http';
import { tap } from 'rxjs';

import { environment } from '../../environments/environment';
import { AuthService } from './auth.service';
import { OfflineService } from './offline.service';
import { IndexedDbService } from './indexed-db.service';
import { invalidateCache } from './http.interceptor';
import { Note, NoteListResponse, CreateNoteRequest, UpdateNoteRequest, ClearNotesResponse } from '../models/notes.model';

/**
 * Serwis szybkich notatek to-do (Etap 19) — CRUD + offline-first.
 *
 * - Sygnalizuje stan listy przez Signals (`notes`, `loading`, `syncing`).
 * - Podczas braku sieci zapisuje notatki lokalnie w IndexedDB (store `user_notes`)
 *   i kolejkuje żądania przez OfflineService (background sync).
 * - Po odzyskaniu połączenia lista jest odświeżana z serwera (rekonsyliacja).
 */
@Injectable({ providedIn: 'root' })
export class NotesService {
  private readonly http = inject(HttpClient);
  private readonly offline = inject(OfflineService);
  private readonly indexedDb = inject(IndexedDbService);
  private readonly auth = inject(AuthService);

  private readonly apiUrl = environment.apiUrl;
  private readonly STORE = 'user_notes';

  private readonly _notes = signal<Note[]>([]);
  readonly notes = this._notes.asReadonly();

  private readonly _loading = signal<boolean>(false);
  readonly loading = this._loading.asReadonly();

  private readonly _syncing = signal<boolean>(false);
  readonly syncing = this._syncing.asReadonly();

  /** ID użytkownika, dla którego załadowano listę (do resetu przy zmianie konta). */
  private loadedUserId: number | null = null;

  constructor() {
    // Reset stanu przy zmianie zalogowanego użytkownika i załadowanie jego notatek.
    effect(
      () => {
        const userId = this.auth.currentUser?.id ?? null;
        if (userId !== this.loadedUserId) {
          this.loadedUserId = userId;
          this._notes.set([]);
          if (userId !== null) {
            this.load();
          }
        }
      },
      { allowSignalWrites: true },
    );

    // Po odzyskaniu połączenia odświeżamy listę (po zsynchronizowaniu kolejki).
    effect(
      () => {
        if (this.offline.online() && this.loadedUserId !== null) {
          // Krótkie opóźnienie pozwala OfflineService najpierw wykonać kolejkę.
          setTimeout(() => this.load(), 1200);
        }
      },
      { allowSignalWrites: true },
    );
  }

  /** Ładowanie notatek z serwera. Przy braku sieci przywraca lokalny store. */
  load(): void {
    if (this.loadedUserId === null) {
      return;
    }

    this._loading.set(true);
    this.http.get<NoteListResponse>(`${this.apiUrl}/notes`).subscribe({
      next: (res) => {
        this._notes.set(res.data);
        this._syncing.set(false);
        void this.persist(res.data);
        this._loading.set(false);
      },
      error: () => {
        this._syncing.set(false);
        void this.restoreFromStore();
        this._loading.set(false);
      },
    });
  }

  /** Dodanie notatki (optymistycznie + offline queue). */
  add(tresc: string): void {
    const trimmed = tresc.trim();
    if (trimmed === '') {
      return;
    }

    const temp: Note = {
      id: -Date.now(),
      user_id: this.loadedUserId ?? 0,
      tresc: trimmed,
      is_done: false,
      kolejnosc: this._notes().length,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    };

    this._notes.update((list) => [...list, temp]);
    void this.persist(this._notes());

    const payload: CreateNoteRequest = { tresc: trimmed, kolejnosc: temp.kolejnosc };

    if (this.offline.isOffline()) {
      this.enqueue('POST', '/notes', payload);
      return;
    }

    this.http
      .post<Note>(`${this.apiUrl}/notes`, payload)
      .pipe(tap(() => invalidateCache('/notes')))
      .subscribe({
        next: (created) => this.replaceTemp(temp.id, created),
        error: () => this.enqueue('POST', '/notes', payload),
      });
  }

  /** Odznaczanie / cofnięcie wykonania notatki. */
  toggle(note: Note): void {
    if (note.id < 0) {
      // Notatka niezsynchronizowana — aktualizuj wyłącznie lokalnie.
      this._notes.update((list) => list.map((n) => (n.id === note.id ? { ...n, is_done: !n.is_done } : n)));
      void this.persist(this._notes());
      return;
    }

    const nextDone = !note.is_done;
    this._notes.update((list) => list.map((n) => (n.id === note.id ? { ...n, is_done: nextDone } : n)));
    void this.persist(this._notes());

    if (this.offline.isOffline()) {
      this.enqueue('PATCH', `/notes/${note.id}/done`, { is_done: nextDone });
      return;
    }

    this.http
      .patch<Note>(`${this.apiUrl}/notes/${note.id}/done`, { is_done: nextDone })
      .pipe(tap(() => invalidateCache('/notes')))
      .subscribe({
        error: () => this.enqueue('PATCH', `/notes/${note.id}/done`, { is_done: nextDone }),
      });
  }

  /** Usunięcie pojedynczej notatki. */
  remove(note: Note): void {
    this._notes.update((list) => list.filter((n) => n.id !== note.id));
    void this.persist(this._notes());

    if (note.id < 0 || this.offline.isOffline()) {
      if (note.id > 0) {
        this.enqueue('DELETE', `/notes/${note.id}`, null);
      }
      return;
    }

    this.http
      .delete<{ success: boolean }>(`${this.apiUrl}/notes/${note.id}`)
      .pipe(tap(() => invalidateCache('/notes')))
      .subscribe({
        error: () => this.enqueue('DELETE', `/notes/${note.id}`, null),
      });
  }

  /** Czyszczenie listy (wszystkie lub wyłącznie wykonane). */
  clear(doneOnly = false): void {
    this._notes.update((list) => list.filter((n) => !(doneOnly ? n.is_done : true)));
    void this.persist(this._notes());

    const query = doneOnly ? '?is_done=1' : '';

    if (this.offline.isOffline()) {
      this.enqueue('DELETE', `/notes${query}`, null);
      return;
    }

    this.http
      .delete<ClearNotesResponse>(`${this.apiUrl}/notes${query}`)
      .pipe(tap(() => invalidateCache('/notes')))
      .subscribe({
        error: () => this.enqueue('DELETE', `/notes${query}`, null),
      });
  }

  /** Edycja treści notatki (inline edit). */
  updateContent(note: Note, tresc: string): void {
    const trimmed = tresc.trim();
    if (trimmed === '' || note.id < 0) {
      return;
    }

    this._notes.update((list) => list.map((n) => (n.id === note.id ? { ...n, tresc: trimmed } : n)));
    void this.persist(this._notes());

    const payload: UpdateNoteRequest = { tresc: trimmed };

    if (this.offline.isOffline()) {
      this.enqueue('PATCH', `/notes/${note.id}`, payload);
      return;
    }

    this.http
      .patch<Note>(`${this.apiUrl}/notes/${note.id}`, payload)
      .pipe(tap(() => invalidateCache('/notes')))
      .subscribe({
        error: () => this.enqueue('PATCH', `/notes/${note.id}`, payload),
      });
  }

  /** Kolejkowanie żądania w OfflineService (background sync). */
  private enqueue(method: string, path: string, body: unknown): void {
    this._syncing.set(true);
    const request = new HttpRequest<unknown>(method, `${this.apiUrl}${path}`, body);
    this.offline.enqueue(request);
  }

  /** Zastępuje tymczasową notatkę (ujemne id) rekordem z serwera. */
  private replaceTemp(tempId: number, created: Note): void {
    this._notes.update((list) => list.map((n) => (n.id === tempId ? created : n)));
    void this.persist(this._notes());
  }

  private async persist(notes: Note[]): Promise<void> {
    try {
      await this.indexedDb.clear(this.STORE);
      for (const note of notes) {
        await this.indexedDb.put(this.STORE, note);
      }
    } catch {
      // Błąd IndexedDB nie może blokować interfejsu — ignorujemy.
    }
  }

  private async restoreFromStore(): Promise<void> {
    try {
      const cached = await this.indexedDb.getAll<Note>(this.STORE);
      const mine = cached.filter((n) => n.user_id === this.loadedUserId);
      this._notes.set(mine);
    } catch {
      this._notes.set([]);
    }
  }
}
