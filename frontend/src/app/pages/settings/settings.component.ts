import { Component, inject, signal, computed, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

import { UsersService } from '../../services/users.service';
import { AuthService } from '../../services/auth.service';
import { ToastService } from '../../services/toast.service';
import { ConfirmService } from '../../services/confirm.service';
import { TranslateService } from '../../services/translate.service';
import { TranslatePipe } from '../../pipes/translate.pipe';
import { SvgIconComponent } from '../../components/svg-icon/svg-icon.component';
import { AddButtonComponent } from '../../components/add-button/add-button.component';
import { ButtonComponent } from '../../components/button/button.component';
import { IconButtonComponent } from '../../components/icon-button/icon-button.component';
import { SelectComponent } from '../../components/select/select.component';
import { StatusBadgeComponent } from '../../components/status-badge/status-badge.component';
import { FormInputComponent } from '../../components/form-input/form-input.component';
import { FilterBarComponent, FilterConfig } from '../../components/filter-bar/filter-bar.component';
import { DataTableComponent, DataTableColumn, DataTableSortEvent, SortDirection } from '../../components/data-table/data-table.component';

import { User, UserListParams, UserRole, Permissions, PERMISSION_SECTIONS, PermissionSection } from '../../models/user.model';
import { AlertConfigsService } from '../../services/alert-configs.service';
import { ALERT_TYPES, AlertType, AlertConfig, AlertConfigPayload } from '../../models/alert-config.model';

type ModalMode = 'create' | 'permissions' | null;

/**
 * Sekcja Settings → Użytkownicy (Etap 5).
 * Lista (DataTable + filtry), tworzenie (email → link), edycja uprawnień per sekcja,
 * blokowanie/odblokowanie, usuwanie. Tab „Alerty" — placeholder (Etap 14).
 */
@Component({
  selector: 'app-settings',
  standalone: true,
  imports: [CommonModule, FormsModule, TranslatePipe, SvgIconComponent, AddButtonComponent, ButtonComponent, IconButtonComponent, SelectComponent, StatusBadgeComponent, FormInputComponent, FilterBarComponent, DataTableComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './settings.component.html',
})
export class SettingsComponent {
  private readonly usersService = inject(UsersService);
  private readonly authService = inject(AuthService);
  private readonly toastService = inject(ToastService);
  private readonly confirmService = inject(ConfirmService);
  private readonly translate = inject(TranslateService);
  private readonly alertConfigsService = inject(AlertConfigsService);

  readonly activeTab = signal<'users' | 'alerts'>('users');
  readonly sections = PERMISSION_SECTIONS;
  // Role są predefiniowane: z Ustawień tworzone są wyłącznie konta Administratora.
  // Super Administratorzy nie są tworzeni z tego poziomu, a pracownicy (rola „użytkownik\")
  // otrzymują konta automatycznie w zakładce Pracownicy (dostęp: awaria + raportowanie).
  readonly roles: { value: UserRole; labelKey: string }[] = [
    { value: 'admin', labelKey: 'ustawienia.roles.admin' },
  ];

  // --- Stan tabu Alerty (Etap 14) ---
  readonly alertTypes = ALERT_TYPES;
  readonly alertTypeOptions: { value: AlertType; labelKey: string }[] = ALERT_TYPES.map((t) => ({
    value: t,
    labelKey: `ustawienia.alerts.types.${t}`,
  }));

  private readonly _alertConfigs = signal<AlertConfig[]>([]);
  private readonly _alertsLoading = signal<boolean>(false);
  readonly alertConfigs = this._alertConfigs.asReadonly();
  readonly alertsLoading = this._alertsLoading.asReadonly();

  readonly alertModalOpen = signal<boolean>(false);
  readonly editingAlertId = signal<number | null>(null);
  readonly alertEmail = signal<string>('');
  readonly alertType = signal<AlertType>('certyfikat_wygasa');
  readonly alertActive = signal<boolean>(true);
  readonly alertSendTime = signal<string>('');
  readonly alertSaving = signal<boolean>(false);

  private readonly _users = signal<User[]>([]);
  private readonly _total = signal<number>(0);
  private readonly _loading = signal<boolean>(false);
  readonly _page = signal<number>(1);
  readonly _perPage = signal<number>(25);
  readonly _sortKey = signal<string>('id');
  readonly _sortDirection = signal<SortDirection>('asc');
  private readonly _filters = signal<Record<string, string>>({});

  readonly users = this._users.asReadonly();
  readonly total = this._total.asReadonly();
  readonly loading = this._loading.asReadonly();
  readonly page = this._page.asReadonly();
  readonly perPage = this._perPage.asReadonly();
  readonly sortKey = this._sortKey.asReadonly();
  readonly sortDirection = this._sortDirection.asReadonly();

  readonly modalMode = signal<ModalMode>(null);
  readonly modalUser = signal<User | null>(null);
  readonly modalEmail = signal<string>('');
  readonly modalRole = signal<UserRole>('admin');
  readonly modalPermissions = signal<Permissions>(this.emptyPermissions());
  readonly modalSaving = signal<boolean>(false);

  readonly isSuperAdmin = computed(() => this.authService.hasRole('super_admin'));
  readonly currentUserId = computed(() => this.authService.currentUser?.id ?? 0);

  readonly filterConfigs = computed<FilterConfig[]>(() => [
    { key: 'email', label: this.t('ustawienia.users.email'), type: 'text', placeholder: this.t('ustawienia.users.search_placeholder') },
    { key: 'role', label: this.t('ustawienia.users.filter_role'), type: 'select', options: this.roles.map((r) => ({ value: r.value, label: this.t(r.labelKey) })) },
    { key: 'is_active', label: this.t('ustawienia.users.filter_status'), type: 'select', options: [{ value: '1', label: this.t('ustawienia.users.status_active') }, { value: '0', label: this.t('ustawienia.users.status_blocked') }] },
  ]);

  readonly columns = computed<DataTableColumn<User>[]>(() => [
    { key: 'id', label: 'ID', sortable: true, width: '60px' },
    { key: 'email', label: this.t('ustawienia.users.email'), sortable: true, isTitle: true },
    { key: 'role', label: this.t('ustawienia.users.role'), sortable: true, formatter: (row) => this.roleLabel(row.role) },
    { key: 'is_active', label: this.t('ustawienia.users.status'), sortable: true },
  ]);

  constructor() {
    this.load();
  }

  private t(key: string, params?: Record<string, string | number>): string {
    return this.translate.instant(key, params);
  }

  load(): void {
    this._loading.set(true);
    const params: UserListParams = {
      ...this._filters(),
      page: this._page(),
      per_page: this._perPage(),
      sort: this._sortKey(),
      direction: (this._sortDirection() ?? 'asc') as 'asc' | 'desc',
    };
    this.usersService.list(params).subscribe({
      next: (res) => {
        this._users.set(res.data);
        this._total.set(res.total);
        this._loading.set(false);
      },
      error: () => {
        this._loading.set(false);
        this.toastService.error(this.t('ustawienia.messages.load_error'));
      },
    });
  }

  onFilterApply(filters: Record<string, string>): void { this._filters.set(filters); this._page.set(1); this.load(); }
  onFilterClear(): void { this._filters.set({}); this._page.set(1); this.load(); }
  onSort(event: DataTableSortEvent): void { this._sortKey.set(event.key); this._sortDirection.set(event.direction); this.load(); }
  onPageChange(p: number): void { this._page.set(p); this.load(); }
  onPerPageChange(pp: number): void { this._perPage.set(pp); this._page.set(1); this.load(); }
  setTab(tab: 'users' | 'alerts'): void {
    this.activeTab.set(tab);
    if (tab === 'alerts') this.loadAlerts();
  }

  // --- Modal: create ---

  openCreate(): void {
    this.modalMode.set('create');
    this.modalUser.set(null);
    this.modalEmail.set('');
    // Rola predefiniowana: Administrator.
    this.modalRole.set('admin');
    // Uprawnienia do wyboru ręcznie — startujemy od pustych (wszystkie sekcje wyłączone).
    this.modalPermissions.set(this.emptyPermissions());
  }

  // --- Modal: permissions ---

  openPermissions(user: User): void {
    this.modalMode.set('permissions');
    this.modalUser.set(user);
    this.modalPermissions.set({ ...user.permissions });
  }

  closeModal(): void {
    if (this.modalSaving()) return;
    this.modalMode.set(null);
    this.modalUser.set(null);
  }

  togglePermission(section: PermissionSection, event: Event): void {
    const checked = (event.target as HTMLInputElement).checked;
    this.modalPermissions.update((p) => ({ ...p, [section]: checked }));
  }

  isPermissionChecked(section: PermissionSection): boolean {
    return this.modalPermissions()[section] === true;
  }

  saveCreate(): void {
    const email = this.modalEmail().trim();
    if (!email) { this.toastService.error(this.t('ustawienia.messages.email_required')); return; }
    if (!this.isValidEmail(email)) { this.toastService.error(this.t('ustawienia.messages.email_invalid')); return; }

    this.modalSaving.set(true);
    this.usersService.create({ email, role: this.modalRole(), permissions: this.modalPermissions() }).subscribe({
      next: () => {
        this.modalSaving.set(false);
        this.closeModal();
        this.toastService.success(this.t('ustawienia.messages.user_created'));
        this.load();
      },
      error: (err) => {
        this.modalSaving.set(false);
        this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  savePermissions(): void {
    const user = this.modalUser();
    if (!user) return;
    this.modalSaving.set(true);
    this.usersService.updatePermissions(user.id, { permissions: this.modalPermissions() }).subscribe({
      next: () => {
        this.modalSaving.set(false);
        this.closeModal();
        this.toastService.success(this.t('ustawienia.messages.permissions_updated'));
        this.load();
      },
      error: (err) => {
        this.modalSaving.set(false);
        this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  // --- Akcje wiersza: blokada/odblok (przez PUT z is_active) ---

  async toggleBlock(user: User): Promise<void> {
    if (user.is_active) {
      // Blokowanie — z potwierdzeniem
      const confirmed = await this.confirmService.confirm({
        message: this.t('ustawienia.messages.block_confirm_message', { email: user.email }),
        danger: true,
      });
      if (!confirmed) return;
    }

    this.usersService.update(user.id, { email: user.email, role: user.role, is_active: !user.is_active }).subscribe({
      next: () => {
        this.toastService.success(this.t(user.is_active ? 'ustawienia.messages.user_blocked' : 'ustawienia.messages.user_unblocked'));
        this.load();
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  async deleteUser(user: User): Promise<void> {
    const confirmed = await this.confirmService.confirm({
      title: this.t('ustawienia.messages.delete_confirm_title'),
      message: this.t('ustawienia.messages.delete_confirm_message', { email: user.email }),
      danger: true,
    });
    if (!confirmed) return;

    this.usersService.delete(user.id).subscribe({
      next: () => {
        this.toastService.success(this.t('ustawienia.messages.user_deleted'));
        this.load();
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  // --- Tab Alerty: konfiguracje (Etap 14) ---

  loadAlerts(): void {
    this._alertsLoading.set(true);
    this.alertConfigsService.list().subscribe({
      next: (res) => {
        this._alertConfigs.set(res.data);
        this._alertsLoading.set(false);
      },
      error: () => {
        this._alertsLoading.set(false);
        this.toastService.error(this.t('ustawienia.alerts.messages.load_error'));
      },
    });
  }

  openAlertCreate(): void {
    this.editingAlertId.set(null);
    this.alertEmail.set('');
    this.alertType.set('certyfikat_wygasa');
    this.alertActive.set(true);
    this.alertSendTime.set('');
    this.alertModalOpen.set(true);
  }

  openAlertEdit(config: AlertConfig): void {
    this.editingAlertId.set(config.id);
    this.alertEmail.set(config.email_odbiorcy);
    this.alertType.set(config.typ_alertu);
    this.alertActive.set(config.czy_aktywny);
    this.alertSendTime.set(config.czas_wysylki?.slice(0, 5) ?? '');
    this.alertModalOpen.set(true);
  }

  closeAlertModal(): void {
    if (this.alertSaving()) return;
    this.alertModalOpen.set(false);
    this.editingAlertId.set(null);
  }

  alertTypeLabel(type: string): string {
    return this.t(`ustawienia.alerts.types.${type}`);
  }

  formatSendTime(config: AlertConfig): string {
    if (!config.czas_wysylki) return '-';
    return config.czas_wysylki.slice(0, 5);
  }

  isMissingOc(type: AlertType): boolean {
    return type === 'brak_raportu_oc';
  }

  toggleAlertActive(event: Event): void {
    this.alertActive.set((event.target as HTMLInputElement).checked);
  }

  saveAlert(): void {
    const email = this.alertEmail().trim();
    const type = this.alertType();
    const time = this.alertSendTime().trim();

    if (!email) { this.toastService.error(this.t('ustawienia.alerts.messages.email_required')); return; }
    if (!this.isValidEmail(email)) { this.toastService.error(this.t('ustawienia.alerts.messages.email_invalid')); return; }
    if (this.isMissingOc(type) && !time) { this.toastService.error(this.t('ustawienia.alerts.messages.time_required')); return; }

    const payload: AlertConfigPayload = {
      email_odbiorcy: email,
      typ_alertu: type,
      czy_aktywny: this.alertActive(),
      czas_wysylki: time ? `${time}:00` : null,
    };

    const editingId = this.editingAlertId();
    this.alertSaving.set(true);

    const request$ = editingId === null
      ? this.alertConfigsService.create(payload)
      : this.alertConfigsService.update(editingId, payload);

    request$.subscribe({
      next: () => {
        this.alertSaving.set(false);
        this.closeAlertModal();
        this.toastService.success(this.t(editingId === null
          ? 'ustawienia.alerts.messages.config_created'
          : 'ustawienia.alerts.messages.config_updated'));
        this.loadAlerts();
      },
      error: (err) => {
        this.alertSaving.set(false);
        this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  async deleteAlert(config: AlertConfig): Promise<void> {
    const confirmed = await this.confirmService.confirm({
      title: this.t('ustawienia.alerts.messages.delete_confirm_title'),
      message: this.t('ustawienia.alerts.messages.delete_confirm_message', { email: config.email_odbiorcy }),
      danger: true,
    });
    if (!confirmed) return;

    this.alertConfigsService.delete(config.id).subscribe({
      next: () => {
        this.toastService.success(this.t('ustawienia.alerts.messages.config_deleted'));
        this.loadAlerts();
      },
      error: (err) => this.toastService.error(err?.error?.error || this.t('common.messages.error.generic')),
    });
  }

  // --- Pomocnicze ---

  isSelf(user: User): boolean {
    return user.id === this.currentUserId();
  }

  canManage(user: User): boolean {
    if (this.isSelf(user)) return false;
    if (user.role === 'super_admin' && !this.isSuperAdmin()) return false;
    return true;
  }

  roleLabel(role: string): string {
    return this.t(`ustawienia.roles.${role}`);
  }

  statusLabel(user: User): string {
    if (!user.is_active) return this.t('ustawienia.users.status_blocked');
    if (user.must_change_password) return this.t('ustawienia.users.status_invited');
    return this.t('ustawienia.users.status_active');
  }

  /** Kanoniczny status użytkownika (dla tonu badge'a): active | invited | blocked. */
  userStatus(user: User): 'active' | 'invited' | 'blocked' {
    if (!user.is_active) return 'blocked';
    if (user.must_change_password) return 'invited';
    return 'active';
  }

  isActive(user: User): boolean {
    return user.is_active && !user.must_change_password;
  }

  isInvited(user: User): boolean {
    return user.is_active && user.must_change_password;
  }

  isBlocked(user: User): boolean {
    return !user.is_active;
  }

  sectionLabel(section: string): string {
    return this.t(`common.menu.${section}`);
  }

  private emptyPermissions(): Permissions {
    const p = {} as Permissions;
    for (const s of PERMISSION_SECTIONS) {
      p[s] = false;
    }
    return p;
  }

  private isValidEmail(email: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }
}