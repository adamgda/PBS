/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';

import { SettingsComponent } from './settings.component';
import { AuthService } from '../../services/auth.service';
import { ToastService } from '../../services/toast.service';
import { ConfirmService } from '../../services/confirm.service';
import { TranslateService } from '../../services/translate.service';
import { environment } from '../../../environments/environment';

/** Stub TranslateService — zwraca klucz (wystarczy do testów). */
class TranslateServiceStub {
  instant(key: string, _params?: Record<string, string | number>): string {
    return key;
  }
}

/** Stub AuthService — super_admin z pełnym dostępem. */
class AuthServiceStub {
  currentUser = { id: 1, email: 'admin@pbs.local', role: 'super_admin' as const, permissions: {}, is_active: true };
  hasRole(...roles: string[]): boolean {
    return roles.includes(this.currentUser!.role);
  }
  hasPermission(_section: string): boolean {
    return true;
  }
}

class ToastServiceStub {
  success = jasmine.createSpy('success');
  error = jasmine.createSpy('error');
  warning = jasmine.createSpy('warning');
  info = jasmine.createSpy('info');
}

class ConfirmServiceStub {
  confirm = jasmine.createSpy('confirm').and.resolveTo(true);
}

describe('SettingsComponent', () => {
  let httpMock: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [SettingsComponent],
      providers: [
        provideRouter([]),
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: AuthService, useClass: AuthServiceStub },
        { provide: TranslateService, useClass: TranslateServiceStub },
        { provide: ToastService, useClass: ToastServiceStub },
        { provide: ConfirmService, useClass: ConfirmServiceStub },
      ],
    }).compileComponents();
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpMock.verify());

  function create() {
    const fixture = TestBed.createComponent(SettingsComponent);
    fixture.detectChanges(); // wyzwala load() → GET /users
    return { fixture, component: fixture.componentInstance };
  }

  function flushList() {
    const req = httpMock.expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/users`));
    req.flush({ data: [], total: 0, page: 1, per_page: 25 });
  }

  it('powinien utworzyć komponent i pobrać listę użytkowników', () => {
    const { component } = create();
    expect(component).toBeTruthy();
    flushList();
    expect(component.users()).toEqual([]);
    expect(component.total()).toBe(0);
  });

  it('powinien otworzyć modal tworzenia z domyślnymi uprawnieniami (false)', () => {
    const { component } = create();
    flushList();

    component.openCreate();
    expect(component.modalMode()).toBe('create');
    expect(component.modalEmail()).toBe('');
    expect(component.modalRole()).toBe('user');
    for (const s of component.sections) {
      expect(component.modalPermissions()[s]).toBe(false);
    }
  });

  it('powinien zablokować tworzenie dla pustego e-maila', () => {
    const { component } = create();
    flushList();

    component.openCreate();
    component.saveCreate();
    expect(TestBed.inject(ToastService).error).toHaveBeenCalled();
    // Nie wysłano POST
    const postReqs = httpMock.match((r) => r.method === 'POST');
    expect(postReqs.length).toBe(0);
  });

  it('powinien utworzyć użytkownika (POST /users)', () => {
    const { fixture, component } = create();
    flushList();

    component.openCreate();
    component.modalEmail.set('new@pbs.local');
    component.modalRole.set('user');
    component.saveCreate();
    fixture.detectChanges();

    const req = httpMock.expectOne(`${environment.apiUrl}/users`);
    expect(req.request.method).toBe('POST');
    expect(req.request.body.email).toBe('new@pbs.local');
    expect(req.request.body.role).toBe('user');

    req.flush({ id: 5, email: 'new@pbs.local', role: 'user', permissions: {}, is_active: true, must_change_password: true, created_at: null, updated_at: null });

    // Po sukcesie lista przeładowana (GET /users)
    const reload = httpMock.expectOne((r) => r.method === 'GET' && r.url.startsWith(`${environment.apiUrl}/users`));
    reload.flush({ data: [], total: 0, page: 1, per_page: 25 });

    expect(component.modalMode()).toBeNull();
    expect(TestBed.inject(ToastService).success).toHaveBeenCalled();
  });

  it('canManage blokuje zarządzanie własnym kontem', () => {
    const { component } = create();
    flushList();

    const self = { id: 1, email: 'admin@pbs.local', role: 'admin' as const, permissions: {} as never, is_active: true, must_change_password: false, created_at: null, updated_at: null };
    expect(component.isSelf(self)).toBe(true);
    expect(component.canManage(self)).toBe(false);

    const other = { id: 2, email: 'x@pbs.local', role: 'user' as const, permissions: {} as never, is_active: true, must_change_password: false, created_at: null, updated_at: null };
    expect(component.canManage(other)).toBe(true);
  });

  it('statusLabel rozróżnia aktywnego/zaproszonego/zablokowanego', () => {
    const { component } = create();
    flushList();

    const active = { id: 1, email: 'a', role: 'user' as const, permissions: {} as never, is_active: true, must_change_password: false, created_at: null, updated_at: null };
    const invited = { id: 2, email: 'b', role: 'user' as const, permissions: {} as never, is_active: true, must_change_password: true, created_at: null, updated_at: null };
    const blocked = { id: 3, email: 'c', role: 'user' as const, permissions: {} as never, is_active: false, must_change_password: false, created_at: null, updated_at: null };

    expect(component.isActive(active)).toBe(true);
    expect(component.isInvited(invited)).toBe(true);
    expect(component.isBlocked(blocked)).toBe(true);
  });
});