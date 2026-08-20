import {
  Component,
  ElementRef,
  ViewChild,
  inject,
  signal,
  computed,
  ChangeDetectionStrategy,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

import { NotesService } from '../../services/notes.service';
import { ConfirmService } from '../../services/confirm.service';
import { ToastService } from '../../services/toast.service';
import { TranslateService } from '../../services/translate.service';
import { TranslatePipe } from '../../pipes/translate.pipe';
import { SvgIconComponent } from '../svg-icon/svg-icon.component';
import { Note } from '../../models/notes.model';

/**
 * Globalny widget szybkich notatek to-do (Etap 19).
 *
 * Wysuwany panel z prawej krawędzi ekranu, dostępny z poziomu każdej podstrony
 * (renderowany w AppComponent). Obsługuje dodawanie, edycję, odznaczanie,
 * usuwanie pojedynczych notatek i czyszczenie listy, a także offline-first.
 */
@Component({
  selector: 'app-quick-notes-widget',
  standalone: true,
  imports: [CommonModule, FormsModule, TranslatePipe, SvgIconComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './quick-notes-widget.component.html',
})
export class QuickNotesWidgetComponent {
  private readonly notesService = inject(NotesService);
  private readonly confirm = inject(ConfirmService);
  private readonly toast = inject(ToastService);
  private readonly translate = inject(TranslateService);

  @ViewChild('panel') private readonly panelRef?: ElementRef<HTMLElement>;
  @ViewChild('firstFocus') private readonly firstFocus?: ElementRef<HTMLElement>;

  /** Lista notatek (readonly signal z serwisu). */
  readonly notes = this.notesService.notes;
  readonly loading = this.notesService.loading;
  readonly syncing = this.notesService.syncing;

  /** Czy panel jest otwarty. */
  readonly open = signal(false);

  /** Treść nowej notatki (input). */
  readonly newNote = signal('');

  /** ID notatki w trakcie edycji (null = brak). */
  readonly editingId = signal<number | null>(null);
  readonly editText = signal('');

  readonly pendingCount = computed(() => this.notes().filter((n) => !n.is_done).length);
  readonly isEmpty = computed(() => this.notes().length === 0);
  readonly hasDone = computed(() => this.notes().some((n) => n.is_done));

  togglePanel(): void {
    if (this.open()) {
      this.closePanel();
    } else {
      this.openPanel();
    }
  }

  openPanel(): void {
    this.open.set(true);
    // Fokus na pierwszym elemencie po otwarciu (dostępność klawiatury).
    requestAnimationFrame(() => this.firstFocus?.nativeElement.focus());
  }

  closePanel(): void {
    this.open.set(false);
    this.editingId.set(null);
    this.editText.set('');
  }

  /** Obsługa klawiatury w panelu: Esc zamyka, Tab utrzymuje fokus wewnątrz. */
  onPanelKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
      event.preventDefault();
      this.closePanel();
      return;
    }

    if (event.key === 'Tab') {
      this.trapFocus(event);
    }
  }

  addNote(): void {
    const tresc = this.newNote().trim();
    if (tresc === '') {
      return;
    }

    this.notesService.add(tresc);
    this.newNote.set('');
    this.toast.info(this.t('notatki.messages.added'));
  }

  startEdit(note: Note): void {
    this.editingId.set(note.id);
    this.editText.set(note.tresc);
  }

  saveEdit(note: Note): void {
    const tresc = this.editText().trim();
    if (tresc === '' || tresc === note.tresc) {
      this.editingId.set(null);
      return;
    }
    this.notesService.updateContent(note, tresc);
    this.editingId.set(null);
    this.toast.info(this.t('notatki.messages.updated'));
  }

  cancelEdit(): void {
    this.editingId.set(null);
    this.editText.set('');
  }

  toggle(note: Note): void {
    this.notesService.toggle(note);
  }

  deleteNote(note: Note): void {
    this.closePanel()
    void this.confirm
      .confirm({
        title: this.t('notatki.confirm.delete_title'),
        message: this.t('notatki.confirm.delete_message'),
        confirmText: this.t('notatki.confirm.confirm'),
        cancelText: this.t('notatki.confirm.cancel'),
        danger: true,
      })
      .then((ok) => {
        if (ok) {
          this.notesService.remove(note);
          this.toast.info(this.t('notatki.messages.deleted'));
        }
      });
  }

  clearAll(): void {
    this.closePanel()
    void this.confirm
      .confirm({
        title: this.t('notatki.confirm.clear_all_title'),
        message: this.t('notatki.confirm.clear_all_message'),
        confirmText: this.t('notatki.confirm.confirm'),
        cancelText: this.t('notatki.confirm.cancel'),
        danger: true,
      })
      .then((ok) => {
        if (ok) {
          this.notesService.clear(false);
          this.toast.info(this.t('notatki.messages.cleared'));
        }
      });
  }

  clearDone(): void {
    this.closePanel()
    void this.confirm
      .confirm({
        title: this.t('notatki.confirm.clear_done_title'),
        message: this.t('notatki.confirm.clear_done_message'),
        confirmText: this.t('notatki.confirm.confirm'),
        cancelText: this.t('notatki.confirm.cancel'),
        danger: true,
      })
      .then((ok) => {
        if (ok) {
          this.notesService.clear(true);
          this.toast.info(this.t('notatki.messages.cleared'));
        }
      });
  }

  /** Focus trap — utrzymuje fokus w panelu podczas nawigacji Tab. */
  private trapFocus(event: KeyboardEvent): void {
    const container = this.panelRef?.nativeElement;
    if (!container) {
      return;
    }

    const focusables = Array.from(
      container.querySelectorAll<HTMLElement>(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
      ),
    ).filter((el) => !el.hasAttribute('disabled'));

    if (focusables.length === 0) {
      return;
    }

    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    const active = document.activeElement;

    if (event.shiftKey) {
      if (active === first || !container.contains(active)) {
        event.preventDefault();
        last.focus();
      }
    } else if (active === last || !container.contains(active)) {
      event.preventDefault();
      first.focus();
    }
  }

  private t(key: string, params?: Record<string, string | number>): string {
    return this.translate.instant(key, params);
  }
}
