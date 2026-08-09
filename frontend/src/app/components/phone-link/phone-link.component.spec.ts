/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';

import { PhoneLinkComponent } from './phone-link.component';

describe('PhoneLinkComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PhoneLinkComponent],
    }).compileComponents();
  });

  function create(value?: string | null, icon = true) {
    const fixture = TestBed.createComponent(PhoneLinkComponent);
    const comp = fixture.componentInstance;
    comp.value = value ?? null;
    comp.icon = icon;
    fixture.detectChanges();
    return { fixture, comp, el: fixture.nativeElement as HTMLElement };
  }

  it('powinien renderować link tel: dla poprawnego numeru', () => {
    const { el } = create('+48 58 123 45 67');
    const a = el.querySelector('a');
    expect(a).not.toBeNull();
    expect(a!.getAttribute('href')).toBe('tel:+48581234567');
    expect(a!.textContent!.trim()).toContain('+48 58 123 45 67');
  });

  it('powinien renderować ikonę telefonu gdy icon=true', () => {
    const { el } = create('581234567', true);
    expect(el.querySelector('svg')).not.toBeNull();
  });

  it('powinien ukryć ikonę gdy icon=false', () => {
    const { el } = create('581234567', false);
    expect(el.querySelector('svg')).toBeNull();
  });

  it('powinien renderować placeholder dla null', () => {
    const { el } = create(null);
    expect(el.querySelector('a')).toBeNull();
    expect(el.textContent!.trim()).toContain('—');
  });

  it('powinien renderować placeholder dla pustego stringa', () => {
    const { el } = create('   ');
    expect(el.querySelector('a')).toBeNull();
    expect(el.textContent!.trim()).toContain('—');
  });
});