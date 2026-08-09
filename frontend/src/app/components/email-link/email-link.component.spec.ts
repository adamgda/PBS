/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';

import { EmailLinkComponent } from './email-link.component';

describe('EmailLinkComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [EmailLinkComponent],
    }).compileComponents();
  });

  function create(value?: string | null, icon = true) {
    const fixture = TestBed.createComponent(EmailLinkComponent);
    const comp = fixture.componentInstance;
    comp.value = value ?? null;
    comp.icon = icon;
    fixture.detectChanges();
    return { fixture, comp, el: fixture.nativeElement as HTMLElement };
  }

  it('powinien renderować link mailto: dla poprawnego e-maila', () => {
    const { el } = create('kontakt@operator.pl');
    const a = el.querySelector('a');
    expect(a).not.toBeNull();
    expect(a!.getAttribute('href')).toBe('mailto:kontakt@operator.pl');
    expect(a!.textContent!.trim()).toContain('kontakt@operator.pl');
  });

  it('powinien renderować ikonę mail gdy icon=true', () => {
    const { el } = create('a@b.pl', true);
    expect(el.querySelector('svg')).not.toBeNull();
  });

  it('powinien ukryć ikonę gdy icon=false', () => {
    const { el } = create('a@b.pl', false);
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