import { Component, inject, signal, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

import { ExportsService } from '../../services/exports.service';
import { ToastService } from '../../services/toast.service';
import { TranslateService } from '../../services/translate.service';
import { TranslatePipe } from '../../pipes/translate.pipe';
import { SvgIconComponent } from '../../components/svg-icon/svg-icon.component';
import { ButtonComponent } from '../../components/button/button.component';
import { ExportDataset, ExportType } from '../../models/export.model';

/**
 * Sekcja „Eksport danych" — generowanie plików CSV na podstawie danych systemu.
 *
 * UI: zakres dat (od/do) + siatka kart zestawów (zlecenia/rozliczenia, pracownicy,
 * awarie, raporty dzienne). Po kliknięciu „Eksportuj CSV" plik pobierany jest
 * jako Blob i zapisywany lokalnie w przeglądarce.
 */
@Component({
  selector: 'app-exports',
  standalone: true,
  imports: [CommonModule, FormsModule, TranslatePipe, SvgIconComponent, ButtonComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './exports.component.html',
})
export class ExportsComponent {
  private readonly exportsService = inject(ExportsService);
  private readonly toastService = inject(ToastService);
  private readonly translate = inject(TranslateService);

  readonly from = signal('');
  readonly to = signal('');
  readonly loadingType = signal<ExportType | null>(null);

  readonly datasets: ExportDataset[] = [
    { type: 'orders', icon: 'harmonogram', titleKey: 'exports.datasets.orders.title', descKey: 'exports.datasets.orders.desc' },
    { type: 'employees', icon: 'pracownicy', titleKey: 'exports.datasets.employees.title', descKey: 'exports.datasets.employees.desc' },
    { type: 'equipment', icon: 'sprzet', titleKey: 'exports.datasets.equipment.title', descKey: 'exports.datasets.equipment.desc' },
    { type: 'incidents', icon: 'awaria', titleKey: 'exports.datasets.incidents.title', descKey: 'exports.datasets.incidents.desc' },
    { type: 'daily_reports', icon: 'reporting', titleKey: 'exports.datasets.daily_reports.title', descKey: 'exports.datasets.daily_reports.desc' },
  ];

  isBusy(type: ExportType): boolean {
    return this.loadingType() === type;
  }

  exportDataset(dataset: ExportDataset): void {
    if (this.loadingType() !== null) {
      return;
    }

    const from = this.from().trim();
    const to = this.to().trim();

    if (from && to && from > to) {
      this.toastService.error(this.t('exports.messages.date_range_invalid'));
      return;
    }

    this.loadingType.set(dataset.type);
    this.exportsService
      .exportCsv(dataset.type, {
        from: from || undefined,
        to: to || undefined,
      })
      .subscribe({
        next: (blob) => {
          this.loadingType.set(null);
          this.download(blob, dataset.type, from, to);
          this.toastService.success(this.t('exports.messages.downloaded'));
        },
        error: () => {
          this.loadingType.set(null);
          this.toastService.error(this.t('exports.messages.download_failed'));
        },
      });
  }

  private download(blob: Blob, type: ExportType, from: string, to: string): void {
    const range = `${from || 'wszystko'}_${to || 'wszystko'}`;
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `${type}_${range}.csv`;
    document.body.appendChild(anchor);
    anchor.click();
    document.body.removeChild(anchor);
    URL.revokeObjectURL(url);
  }

  private t(key: string): string {
    return this.translate.instant(key);
  }
}
