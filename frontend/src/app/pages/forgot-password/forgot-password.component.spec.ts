import { TestBed, fakeAsync, tick } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';

import { ForgotPasswordComponent } from './forgot-password.component';
import { AuthService } from '../../services/auth.service';
import { TranslateService } from '../../services/translate.service';
import { environment } from '../../../environments/environment';

/** Stub TranslateService — zwraca klucz (wystarczy do testów renderowania). */
class TranslateServiceStub {
  instant(key: string): string {
    return key;
  }
}

describe('ForgotPasswordComponent', () => {
  let httpMock: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ForgotPasswordComponent],
      providers: [
        provideRouter([
          { path: 'login', loadComponent: () => Promise.resolve({ default: class {} as any }) },
          { path: 'forgot-password', loadComponent: () => Promise.resolve({ default: ForgotPasswordComponent }) },
        ]),
        provideHttpClient(),
        provideHttpClientTesting(),
        AuthService,
        { provide: TranslateService, useClass: TranslateServiceStub },
      ],
    }).compileComponents();
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpMock.verify());

  function create() {
    const fixture = TestBed.createComponent(ForgotPasswordComponent);
    fixture.detectChanges();
    return { fixture, component: fixture.componentInstance };
  }

  it('powinien utworzyć komponent', () => {
    const { component } = create();
    expect(component).toBeTruthy();
  });

  it('powinien pokazać formularz początkowo (submitted=false)', () => {
    const { fixture } = create();
    const el: HTMLElement = fixture.nativeElement;
    expect(el.querySelector('form')).toBeTruthy();
    expect(el.querySelector('button[type="submit"]')).toBeTruthy();
  });

  it('powinien ustawić błąd przy pustym e-mailu', () => {
    const { component } = create();
    component.onSubmit();
    expect(component.error()).toBeTruthy();
  });

  it('powinien ustawić błąd przy nieprawidłowym e-mailu', () => {
    const { component } = create();
    component.email.set('nie-email');
    component.onSubmit();
    expect(component.error()).toBeTruthy();
  });

  it('powinien wysłać żądanie i przejść w stan sukcesu', () => {
    const { fixture, component } = create();
    component.email.set('user@pbs.local');
    component.onSubmit();
    fixture.detectChanges();

    const req = httpMock.expectOne(`${environment.apiUrl}/auth/forgot-password`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({ email: 'user@pbs.local' });

    req.flush({ message: 'If the email exists, a reset link has been sent.' });
    fixture.detectChanges();

    expect(component.submitted()).toBe(true);
    expect(component.loading()).toBe(false);
    expect(component.error()).toBeNull();
    expect(component.resetUrl()).toBeNull();

    // Po sukcesie formularz zniknął, pojawia się komunikat
    const el: HTMLElement = fixture.nativeElement;
    expect(el.querySelector('form')).toBeFalsy();
    expect(el.textContent).toContain('common.auth.forgot_password_success_title');
  });

  it('powinien pokazać link resetujący gdy API zwróci reset_url (tryb debug)', () => {
    const { fixture, component } = create();
    component.email.set('user@pbs.local');
    component.onSubmit();
    fixture.detectChanges();

    const req = httpMock.expectOne(`${environment.apiUrl}/auth/forgot-password`);
    req.flush({
      message: 'ok',
      token: 'abc123',
      reset_url: 'http://localhost:4200/set-password?token=abc123',
    });
    fixture.detectChanges();

    expect(component.resetUrl()).toBe('http://localhost:4200/set-password?token=abc123');
    const el: HTMLElement = fixture.nativeElement;
    // Pojawia się amber box z linkiem i przyciskiem "Otwórz link"
    expect(el.querySelector('a[href="http://localhost:4200/set-password?token=abc123"]')).toBeTruthy();
    expect(el.textContent).toContain('abc123');
  });

  it('powinien ustawić loading podczas żądania', () => {
    const { fixture, component } = create();
    component.email.set('user@pbs.local');
    component.onSubmit();
    fixture.detectChanges();

    expect(component.loading()).toBe(true);
    expect(component.submitted()).toBe(false);

    const req = httpMock.expectOne(`${environment.apiUrl}/auth/forgot-password`);
    req.flush({ message: 'ok' });
    expect(component.loading()).toBe(false);
  });
});