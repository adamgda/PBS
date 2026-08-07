import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { ActivatedRoute } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';

import { SetPasswordComponent } from './set-password.component';
import { AuthService } from '../../services/auth.service';
import { TranslateService } from '../../services/translate.service';
import { environment } from '../../../environments/environment';

class TranslateServiceStub {
  instant(key: string): string {
    return key;
  }
}

function makeRouteStub(token: string | null) {
  return {
    snapshot: {
      queryParamMap: {
        get: (name: string) => (name === 'token' ? token : null),
      },
    },
  };
}

describe('SetPasswordComponent', () => {
  let httpMock: HttpTestingController;

  function configure(token: string | null) {
    TestBed.configureTestingModule({
      imports: [SetPasswordComponent],
      providers: [
        provideRouter([]),
        provideHttpClient(),
        provideHttpClientTesting(),
        AuthService,
        { provide: TranslateService, useClass: TranslateServiceStub },
        { provide: ActivatedRoute, useValue: makeRouteStub(token) },
      ],
    });
    httpMock = TestBed.inject(HttpTestingController);
  }

  function create() {
    const fixture = TestBed.createComponent(SetPasswordComponent);
    fixture.detectChanges();
    return { fixture, component: fixture.componentInstance };
  }

  afterEach(() => {
    if (httpMock) httpMock.verify();
  });

  it('powinien utworzyć komponent', () => {
    configure('valid-token');
    const { component } = create();
    expect(component).toBeTruthy();
  });

  it('powinien pokazać stan braku tokenu gdy token=null', () => {
    configure(null);
    const { fixture, component } = create();
    expect(component.token()).toBeNull();
    const el: HTMLElement = fixture.nativeElement;
    expect(el.querySelector('form')).toBeFalsy();
    expect(el.textContent).toContain('common.auth.set_password_no_token_title');
  });

  it('powinien pokazać formularz gdy token jest obecny', () => {
    configure('valid-token');
    const { fixture, component } = create();
    expect(component.token()).toBe('valid-token');
    const el: HTMLElement = fixture.nativeElement;
    expect(el.querySelector('form')).toBeTruthy();
  });

  it('powinien pokazać błąd niezgodności haseł', () => {
    configure('valid-token');
    const { component } = create();
    // silne hasło spełniające politykę (12+ znaków, 3+ klasy)
    component.password.set('StrongPass123!');
    component.confirmPassword.set('InneHaslo123!');
    component.onSubmit();
    expect(component.error()).toContain('mismatch');
  });

  it('powinien pokazać błąd polityki gdy hasło za słabe', () => {
    configure('valid-token');
    const { component } = create();
    component.password.set('slabehaslo'); // brak cyfr, duzych, specialnych
    component.confirmPassword.set('slabehaslo');
    component.onSubmit();
    expect(component.error()).toBeTruthy();
  });

  it('powinien poprawnie wyliczyć siłę hasła', () => {
    configure('valid-token');
    const { component } = create();
    component.password.set('StrongPass123!');
    expect(component.hasMinLength()).toBeTrue();
    expect(component.classesCount()).toBe(4);
    expect(component.passwordValid()).toBeTrue();
    expect(component.strengthLevel()).toBe(3); // silne
  });

  it('powinien wysłać żądanie i przejść w stan sukcesu', () => {
    configure('valid-token');
    const { fixture, component } = create();
    component.password.set('StrongPass123!');
    component.confirmPassword.set('StrongPass123!');
    component.onSubmit();
    fixture.detectChanges();

    const req = httpMock.expectOne(`${environment.apiUrl}/auth/set-password`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({ token: 'valid-token', password: 'StrongPass123!' });

    req.flush({ success: true });
    fixture.detectChanges();

    expect(component.success()).toBeTrue();
    expect(component.loading()).toBeFalse();
    const el: HTMLElement = fixture.nativeElement;
    expect(el.querySelector('form')).toBeFalsy();
    expect(el.textContent).toContain('common.auth.set_password_success_title');
  });

  it('powinien obsłużyć błąd wygasłego tokenu z backendu', () => {
    configure('expired-token');
    const { fixture, component } = create();
    component.password.set('StrongPass123!');
    component.confirmPassword.set('StrongPass123!');
    component.onSubmit();
    fixture.detectChanges();

    const req = httpMock.expectOne(`${environment.apiUrl}/auth/set-password`);
    req.flush({ error: 'Token has expired' }, { status: 400, statusText: 'Bad Request' });
    fixture.detectChanges();

    expect(component.success()).toBeFalse();
    expect(component.error()).toBe('Token has expired');
  });
});