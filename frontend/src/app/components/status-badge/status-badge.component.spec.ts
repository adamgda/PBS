/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { StatusBadgeComponent, StatusTone } from './status-badge.component';
import { TranslateService } from '../../services/translate.service';

/** Stub TranslateService — zwraca klucz (wystarczy do testów). */
class TranslateServiceStub {
  instant(key: string, _params?: Record<string, string | number>): string {
    return key;
  }
}

describe('StatusBadgeComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [StatusBadgeComponent],
      providers: [
        provideRouter([]),
        { provide: TranslateService, useClass: TranslateServiceStub },
      ],
    }).compileComponents();
  });

  function create(initial?: Partial<{ status: string; label: string; labelKey: string; tone: StatusTone }>) {
    const fixture = TestBed.createComponent(StatusBadgeComponent);
    const comp = fixture.componentInstance;
    comp.status = initial?.status ?? 'active';
    if (initial?.label !== undefined) comp.label = initial.label;
    if (initial?.labelKey !== undefined) comp.labelKey = initial.labelKey;
    if (initial?.tone !== undefined) comp.tone = initial.tone;
    fixture.detectChanges();
    return { fixture, comp, el: fixture.nativeElement as HTMLElement };
  }

  it('powinien użyć tonu success dla statusu "active"', () => {
    const { comp, el } = create({ status: 'active' });
    expect(comp.resolvedTone()).toBe('success');
    expect(el.querySelector('span')!.className).toContain('green');
  });

  it('powinien użyć tonu neutral dla statusu "inactive"', () => {
    const { comp } = create({ status: 'inactive' });
    expect(comp.resolvedTone()).toBe('neutral');
  });

  it('powinien użyć tonu warning dla statusu "in_progress"', () => {
    const { comp } = create({ status: 'in_progress' });
    expect(comp.resolvedTone()).toBe('warning');
  });

  it('powinien fallbackować na neutral dla nieznanego statusu', () => {
    const { comp } = create({ status: 'nieznany_status' });
    expect(comp.resolvedTone()).toBe('neutral');
  });

  it('powinien pozwolić na override tonu przez input "tone"', () => {
    const { comp } = create({ status: 'active', tone: 'danger' });
    expect(comp.resolvedTone()).toBe('danger');
  });

  it('powinien renderować jawny label bez tłumaczenia', () => {
    const { comp, el } = create({ status: 'blocked', label: 'Zablokowany' });
    expect(comp.isPlainText()).toBe(true);
    expect(el.textContent!.trim()).toContain('Zablokowany');
  });

  it('powinien renderować klucz tłumaczeniowy gdy brak label', () => {
    const { comp, el } = create({ status: 'active' });
    expect(comp.isPlainText()).toBe(false);
    expect(comp.translationKey()).toBe('common.status.active');
    // Stub zwraca klucz jako tekst
    expect(el.textContent!.trim()).toContain('common.status.active');
  });

  it('powinien użyć labelKey gdy podany', () => {
    const { comp } = create({ status: 'in_progress', labelKey: 'harmonogram.status.in_progress' });
    expect(comp.translationKey()).toBe('harmonogram.status.in_progress');
  });
});