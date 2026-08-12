/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';
import { Router, UrlTree, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';

import { PermissionGuard } from './permission.guard';
import { AuthService } from '../services/auth.service';

class AuthServiceStub {
  isLoggedIn = jasmine.createSpy('isLoggedIn');
  hasPermission = jasmine.createSpy('hasPermission');
}

describe('PermissionGuard', () => {
  let auth: AuthServiceStub;
  let router: Router;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        { provide: AuthService, useClass: AuthServiceStub },
        Router,
      ],
    });
    auth = TestBed.inject(AuthService) as unknown as AuthServiceStub;
    router = TestBed.inject(Router);
  });

  const state = { url: '/awarie' } as RouterStateSnapshot;

  function routeWithPermission(permission?: string): ActivatedRouteSnapshot {
    return { data: permission ? { permission } : {} } as ActivatedRouteSnapshot;
  }

  it('zezwala, gdy brak wymaganego uprawnienia', () => {
    const result = TestBed.runInInjectionContext(() => PermissionGuard(routeWithPermission(), state));
    expect(result).toBe(true);
  });

  it('przekierowuje na /login, gdy niezalogowany', () => {
    auth.isLoggedIn.and.returnValue(false);
    const result = TestBed.runInInjectionContext(() =>
      PermissionGuard(routeWithPermission('awarie'), state),
    );
    expect(result instanceof UrlTree).toBe(true);
    expect((result as UrlTree).toString()).toContain('/login');
  });

  it('zezwala, gdy użytkownik ma wymagane uprawnienie', () => {
    auth.isLoggedIn.and.returnValue(true);
    auth.hasPermission.and.returnValue(true);
    const result = TestBed.runInInjectionContext(() =>
      PermissionGuard(routeWithPermission('awarie'), state),
    );
    expect(result).toBe(true);
  });

  it('przekierowuje na /dashboard z error=no_permission, gdy brak uprawnień', () => {
    auth.isLoggedIn.and.returnValue(true);
    auth.hasPermission.and.returnValue(false);
    const result = TestBed.runInInjectionContext(() =>
      PermissionGuard(routeWithPermission('awarie'), state),
    );
    expect(result instanceof UrlTree).toBe(true);
    const tree = result as UrlTree;
    expect(tree.toString()).toContain('/dashboard');
    expect(tree.queryParams['error']).toBe('no_permission');
  });
});
