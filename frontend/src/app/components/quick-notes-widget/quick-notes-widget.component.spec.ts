/// <reference types="jasmine" />
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { signal, Signal } from '@angular/core';

import { QuickNotesWidgetComponent } from './quick-notes-widget.component';
import { NotesService } from '../../services/notes.service';
import { ConfirmService } from '../../services/confirm.service';
import { ToastService } from '../../services/toast.service';
import { TranslateService } from '../../services/translate.service';
import { Note } from '../../models/notes.model';

class NotesServiceStub {
  notes: Signal<Note[]> = signal<Note[]>([
    { id: 1, user_id: 1, tresc: 'Zadanie A', is_done: false, kolejnosc: 0, created_at: null, updated_at: null },
    { id: 2, user_id: 1, tresc: 'Zadanie B', is_done: true, kolejnosc: 1, created_at: null, updated_at: null },
  ]);
  loading = signal(false);
  syncing = signal(false);
  add = jasmine.createSpy('add');
  toggle = jasmine.createSpy('toggle');
  remove = jasmine.createSpy('remove');
  clear = jasmine.createSpy('clear');
  updateContent = jasmine.createSpy('updateContent');
}

class ConfirmServiceStub {
  confirm = jasmine.createSpy('confirm').and.returnValue(Promise.resolve(true));
}

class ToastServiceStub {
  show = jasmine.createSpy('show');
  success = jasmine.createSpy('success');
  error = jasmine.createSpy('error');
  warning = jasmine.createSpy('warning');
  info = jasmine.createSpy('info');
}

class TranslateServiceStub {
  instant(key: string, _params?: Record<string, string | number>): string {
    return key;
  }
}

describe('QuickNotesWidgetComponent', () => {
  let fixture: ComponentFixture<QuickNotesWidgetComponent>;
  let comp: QuickNotesWidgetComponent;
  let notes: NotesServiceStub;
  let confirm: ConfirmServiceStub;
  let toast: ToastServiceStub;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [QuickNotesWidgetComponent],
      providers: [
        { provide: NotesService, useClass: NotesServiceStub },
        { provide: ConfirmService, useClass: ConfirmServiceStub },
        { provide: ToastService, useClass: ToastServiceStub },
        { provide: TranslateService, useClass: TranslateServiceStub },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(QuickNotesWidgetComponent);
    comp = fixture.componentInstance;
    notes = TestBed.inject(NotesService) as unknown as NotesServiceStub;
    confirm = TestBed.inject(ConfirmService) as unknown as ConfirmServiceStub;
    toast = TestBed.inject(ToastService) as unknown as ToastServiceStub;
    fixture.detectChanges();
  });

  it('powinien utworzyć komponent', () => {
    expect(comp).toBeTruthy();
  });

  it('powinien wyliczać licznik nieodznaczonych notatek', () => {
    expect(comp.pendingCount()).toBe(1);
  });

  it('dodaje notatkę i czyści pole wejściowe', () => {
    comp.openPanel();
    comp.newNote.set('Nowe zadanie');
    comp.addNote();

    expect(notes.add).toHaveBeenCalledWith('Nowe zadanie');
    expect(comp.newNote()).toBe('');
    expect(toast.info).toHaveBeenCalled();
  });

  it('nie dodaje pustej notatki', () => {
    comp.newNote.set('   ');
    comp.addNote();
    expect(notes.add).not.toHaveBeenCalled();
  });

  it('odznacza notatkę jako wykonaną', () => {
    const note = notes.notes()[0];
    comp.toggle(note);
    expect(notes.toggle).toHaveBeenCalledWith(note);
  });

  it('usuwa pojedynczą notatkę po potwierdzeniu', async () => {
    const note = notes.notes()[0];
    comp.deleteNote(note);
    await fixture.whenStable();

    expect(confirm.confirm).toHaveBeenCalled();
    expect(notes.remove).toHaveBeenCalledWith(note);
    expect(toast.info).toHaveBeenCalled();
  });

  it('nie usuwa notatki po odrzuceniu potwierdzenia', async () => {
    confirm.confirm.and.returnValue(Promise.resolve(false));
    comp.deleteNote(notes.notes()[0]);
    await fixture.whenStable();
    expect(notes.remove).not.toHaveBeenCalled();
  });

  it('czyści całą listę po potwierdzeniu', async () => {
    comp.clearAll();
    await fixture.whenStable();
    expect(confirm.confirm).toHaveBeenCalled();
    expect(notes.clear).toHaveBeenCalledWith(false);
    expect(toast.info).toHaveBeenCalled();
  });

  it('usuwa wyłącznie wykonane notatki', async () => {
    comp.clearDone();
    await fixture.whenStable();
    expect(notes.clear).toHaveBeenCalledWith(true);
  });

  it('renderuje wskaźnik offline przy synchronizacji', () => {
    comp.openPanel();
    notes.syncing.set(true);
    fixture.detectChanges();

    const html = fixture.nativeElement as HTMLElement;
    expect(html.textContent).toContain('notatki.messages.offline_saved');
  });
});
