import { Routes } from '@angular/router';

import { AuthGuard } from './guards/auth.guard';
import { PermissionGuard } from './guards/permission.guard';

/**
 * Routing PBS — wszystkie sekcje lazy-loaded (loadComponent).
 * Dokumentacja: docs/technical-documentation.md — sekcja 14.7 Optymalizacja bundle frontendu.
 */
export const routes: Routes = [
  {
    path: 'login',
    loadComponent: () => import('./pages/login/login.component').then((m) => m.LoginComponent),
  },
  {
    path: 'dashboard',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'dashboard' },
    loadComponent: () =>
      import('./pages/dashboard/dashboard.component').then((m) => m.DashboardComponent),
  },
  // Placeholdery dla sekcji — zaimplementowane w kolejnych etapach
  {
    path: 'pracownicy',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'pracownicy' },
    loadComponent: () =>
      import('./pages/placeholder/placeholder.component').then((m) => m.PlaceholderComponent),
  },
  {
    path: 'sprzet',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'sprzet' },
    loadComponent: () =>
      import('./pages/placeholder/placeholder.component').then((m) => m.PlaceholderComponent),
  },
  {
    path: 'terminale',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'terminale' },
    loadComponent: () =>
      import('./pages/placeholder/placeholder.component').then((m) => m.PlaceholderComponent),
  },
  {
    path: 'harmonogram',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'harmonogram' },
    loadComponent: () =>
      import('./pages/placeholder/placeholder.component').then((m) => m.PlaceholderComponent),
  },
  {
    path: 'analityka',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'analityka' },
    loadComponent: () =>
      import('./pages/placeholder/placeholder.component').then((m) => m.PlaceholderComponent),
  },
  {
    path: 'raportowanie',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'raportowanie' },
    loadComponent: () =>
      import('./pages/placeholder/placeholder.component').then((m) => m.PlaceholderComponent),
  },
  {
    path: 'awaria',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'awaria' },
    loadComponent: () =>
      import('./pages/placeholder/placeholder.component').then((m) => m.PlaceholderComponent),
  },
  {
    path: 'ustawienia',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'ustawienia' },
    loadComponent: () =>
      import('./pages/placeholder/placeholder.component').then((m) => m.PlaceholderComponent),
  },
  {
    path: '',
    redirectTo: 'dashboard',
    pathMatch: 'full',
  },
  {
    path: '**',
    redirectTo: 'dashboard',
  },
];