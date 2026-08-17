import { TestBed } from '@angular/core/testing';
import { Router, UrlTree, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';

import { DefaultRouteGuard } from './default-route.guard';
import { AuthService } from '../services/auth.service';

class AuthServiceStub {
  firstAvailableRoute = jasmine.createSpy('firstAvailableRoute');
}

describe('DefaultRouteGuard', () => {
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

  const state = { url: '/' } as RouterStateSnapshot;
  const route = {} as ActivatedRouteSnapshot;

  it('przekierowuje do pierwszej dostępnej sekcji', () => {
    auth.firstAvailableRoute.and.returnValue('/pracownicy');
    const result = TestBed.runInInjectionContext(() => DefaultRouteGuard(route, state));
    expect(result instanceof UrlTree).toBe(true);
    expect((result as UrlTree).toString()).toContain('/pracownicy');
  });

  it('przekierowuje na /login, gdy użytkownik nie ma uprawnień', () => {
    auth.firstAvailableRoute.and.returnValue('/login');
    const result = TestBed.runInInjectionContext(() => DefaultRouteGuard(route, state));
    expect((result as UrlTree).toString()).toContain('/login');
  });
});
