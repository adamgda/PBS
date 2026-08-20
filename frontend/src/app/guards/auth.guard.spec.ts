/// <reference types="jasmine" />
import { TestBed } from '@angular/core/testing';
import { Router, UrlTree, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';

import { AuthGuard } from './auth.guard';
import { AuthService } from '../services/auth.service';

class AuthServiceStub {
  isLoggedIn = jasmine.createSpy('isLoggedIn');
}

describe('AuthGuard', () => {
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

  const route = {} as ActivatedRouteSnapshot;
  const state = { url: '/employees' } as RouterStateSnapshot;

  it('zezwala, gdy użytkownik jest zalogowany', () => {
    auth.isLoggedIn.and.returnValue(true);
    const result = TestBed.runInInjectionContext(() => AuthGuard(route, state));
    expect(result).toBe(true);
  });

  it('przekierowuje na /login z returnUrl, gdy niezalogowany', () => {
    auth.isLoggedIn.and.returnValue(false);
    const result = TestBed.runInInjectionContext(() => AuthGuard(route, state));
    expect(result instanceof UrlTree).toBe(true);
    const tree = result as UrlTree;
    expect(tree.toString()).toContain('/login');
    expect(tree.queryParams['returnUrl']).toBe('/employees');
  });
});
