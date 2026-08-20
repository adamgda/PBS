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
    path: 'audit-logs',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'super_admin' },
    loadComponent: () =>
      import('./pages/audit-logs/audit-logs.component').then((m) => m.AuditLogsComponent),
  },
  // Placeholdery dla sekcji — zaimplementowane w kolejnych etapach
  {
    path: 'employees',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'pracownicy' },
    loadComponent: () =>
      import('./pages/employees/employees.component').then((m) => m.EmployeesComponent),
  },
  {
    path: 'equipment',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'sprzet' },
    loadComponent: () =>
      import('./pages/equipment/equipment.component').then((m) => m.EquipmentComponent),
  },
  {
    path: 'terminals',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'terminale' },
    loadComponent: () =>
      import('./pages/terminals/terminals.component').then((m) => m.TerminalsComponent),
  },
  {
    path: 'schedule/new',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'harmonogram' },
    loadComponent: () =>
      import('./pages/orders/order-new/order-new.component').then((m) => m.OrderNewComponent),
  },
  {
    path: 'schedule/edit/:id',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'harmonogram' },
    loadComponent: () =>
      import('./pages/orders/order-new/order-new.component').then((m) => m.OrderNewComponent),
  },
  {
    path: 'schedule',
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
    path: 'incidents/new',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'awaria' },
    loadComponent: () =>
      import('./pages/incidents/incident-new/incident-new.component').then((m) => m.IncidentNewComponent),
  },
  {
    path: 'incidents/:id',
    canActivate: [AuthGuard, PermissionGuard],
    data: { permission: 'awaria' },
    loadComponent: () =>
      import('./pages/incidents/incident-details/incident-details.component').then((m) => m.IncidentDetailsComponent),
  },
  {
    path: 'incidents',
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