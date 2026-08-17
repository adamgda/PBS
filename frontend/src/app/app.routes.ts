import { Routes } from '@angular/router';

import { AuthGuard } from './guards/auth.guard';
import { PermissionGuard } from './guards/permission.guard';
import { DefaultRouteGuard } from './guards/default-route.guard';
import { RedirectComponent } from './components/redirect/redirect.component';

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
    path: 'forgot-password',
    loadComponent: () =>
      import('./pages/forgot-password/forgot-password.component').then((m) => m.ForgotPasswordComponent),
  },
  {
    path: 'set-password',
    loadComponent: () =>
      import('./pages/set-password/set-password.component').then((m) => m.SetPasswordComponent),
  },
  {
    path: 'dashboard',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'dashboard' },
    loadComponent: () =>
      import('./pages/dashboard/dashboard.component').then((m) => m.DashboardComponent),
  },
  {
    path: 'logi-audytowe',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'super_admin' },
    loadComponent: () =>
      import('./pages/audit-logs/audit-logs.component').then((m) => m.AuditLogsComponent),
  },
  // Placeholdery dla sekcji — zaimplementowane w kolejnych etapach
  {
    path: 'pracownicy',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'pracownicy' },
    loadComponent: () =>
      import('./pages/employees/employees.component').then((m) => m.EmployeesComponent),
  },
  {
    path: 'sprzet',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'sprzet' },
    loadComponent: () =>
      import('./pages/equipment/equipment.component').then((m) => m.EquipmentComponent),
  },
  {
    path: 'terminale',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'terminale' },
    loadComponent: () =>
      import('./pages/terminals/terminals.component').then((m) => m.TerminalsComponent),
  },
  {
    path: 'harmonogram/nowe',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'harmonogram' },
    loadComponent: () =>
      import('./pages/orders/order-new/order-new.component').then((m) => m.OrderNewComponent),
  },
  {
    path: 'harmonogram/edytuj/:id',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'harmonogram' },
    loadComponent: () =>
      import('./pages/orders/order-new/order-new.component').then((m) => m.OrderNewComponent),
  },
  {
    path: 'harmonogram',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'harmonogram' },
    loadComponent: () =>
      import('./pages/orders/orders.component').then((m) => m.OrdersComponent),
  },
  {
    path: 'analytics',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'analityka' },
    loadComponent: () =>
      import('./pages/analytics/analytics.component').then((m) => m.AnalyticsComponent),
  },
  {
    path: 'reporting',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'raportowanie' },
    loadComponent: () =>
      import('./pages/reporting/reporting.component').then((m) => m.ReportingComponent),
  },
  {
    path: 'awaria',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'awaria' },
    loadComponent: () =>
      import('./pages/incidents/incidents.component').then((m) => m.IncidentsComponent),
  },
  {
    path: 'settings',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'ustawienia' },
    loadComponent: () =>
      import('./pages/settings/settings.component').then((m) => m.SettingsComponent),
  },
  {
    // Publiczna podstrona kodów QR (Etap 20) — bez AuthGuard, dostępna z naklejki QR
    path: 'qr/:token',
    loadComponent: () => import('./pages/qr/qr.component').then((m) => m.QrComponent),
  },
  {
    path: '',
    canActivate: [DefaultRouteGuard],
    component: RedirectComponent,
    pathMatch: 'full',
  },
  {
    path: '**',
    canActivate: [DefaultRouteGuard],
    component: RedirectComponent,
  },
];