import { Component, inject, signal, ChangeDetectionStrategy, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';

import { QrService } from '../../services/qr.service';
import { TranslateService } from '../../services/translate.service';
import { TranslatePipe } from '../../pipes/translate.pipe';
import { SvgIconComponent } from '../../components/svg-icon/svg-icon.component';
import { ButtonComponent } from '../../components/button/button.component';
import { FormInputComponent } from '../../components/form-input/form-input.component';
import { QrMachine } from '../../models/qr.model';

type QrAction = 'incident' | 'daily_report' | null;

/**
 * Publiczna podstrona kodów QR (Etap 20) — dostępna bez logowania z naklejki QR.
 *
 * Strona wyboru akcji (Zgłoś awarię / Raport obsługi codziennej) oraz
 * uproszczone formularze mobilne. Brak danych osobowych maszyny.
 */
@Component({
  selector: 'app-qr',
  standalone: true,
  imports: [CommonModule, FormsModule, TranslatePipe, SvgIconComponent, ButtonComponent, FormInputComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './qr.component.html',
})
export class QrComponent implements OnInit {
  private readonly qrService = inject(QrService);
  private readonly translate = inject(TranslateService);
  private readonly route = inject(ActivatedRoute);

  readonly machine = signal<QrMachine | null>(null);
  readonly loading = signal(true);
  readonly notFound = signal(false);
  readonly error = signal<string | null>(null);
  readonly action = signal<QrAction>(null);

  // Formularz awarii
  readonly incidentOpis = signal('');
  readonly incidentKontakt = signal('');
  // Formularz OC
  readonly ocPrzebieg = signal('');
  readonly ocOpis = signal('');
  readonly ocUwagi = signal('');

  readonly submitting = signal(false);
  readonly incidentDone = signal<{ number: string } | null>(null);
  readonly reportDone = signal(false);

  ngOnInit(): void {
    const token = this.route.snapshot.paramMap.get('token') ?? '';
    if (!token) {
      this.loading.set(false);
      this.notFound.set(true);
      return;
    }

    this.qrService.getMachine(token).subscribe({
      next: (m) => {
        this.machine.set(m);
        this.loading.set(false);
      },
      error: (err) => {
        this.loading.set(false);
        if (err?.status === 404) {
          this.notFound.set(true);
        } else {
          this.error.set(this.t('qr.errors.generic'));
        }
      },
    });
  }

  selectAction(action: 'incident' | 'daily_report'): void {
    this.action.set(action);
    this.error.set(null);
    this.incidentDone.set(null);
    this.reportDone.set(false);
  }

  back(): void {
    this.action.set(null);
    this.error.set(null);
  }

  submitIncident(): void {
    const token = this.token();
    if (!token) return;

    const opis = this.incidentOpis().trim();
    if (!opis) {
      this.error.set(this.t('qr.errors.description_required'));
      return;
    }

    this.submitting.set(true);
    this.error.set(null);

    this.qrService.createIncident(token, {
      opis,
      kontakt: this.incidentKontakt().trim() || null,
    }).subscribe({
      next: (res) => {
        this.submitting.set(false);
        this.incidentDone.set({ number: res.numer_zgloszenia });
      },
      error: () => {
        this.submitting.set(false);
        this.error.set(this.t('qr.errors.generic'));
      },
    });
  }

  submitDailyReport(): void {
    const token = this.token();
    if (!token) return;

    const przebieg = Number(this.ocPrzebieg());
    if (this.ocPrzebieg().trim() === '' || Number.isNaN(przebieg) || przebieg < 0) {
      this.error.set(this.t('qr.errors.mileage_invalid'));
      return;
    }
    const opis = this.ocOpis().trim();
    if (!opis) {
      this.error.set(this.t('qr.errors.description_required'));
      return;
    }

    this.submitting.set(true);
    this.error.set(null);

    this.qrService.createDailyReport(token, {
      aktualny_przebieg: przebieg,
      przebieg_oc: opis,
      uwagi: this.ocUwagi().trim() || null,
    }).subscribe({
      next: () => {
        this.submitting.set(false);
        this.reportDone.set(true);
      },
      error: () => {
        this.submitting.set(false);
        this.error.set(this.t('qr.errors.generic'));
      },
    });
  }

  resetIncidentForm(): void {
    this.incidentOpis.set('');
    this.incidentKontakt.set('');
    this.incidentDone.set(null);
  }

  categoryLabel(): string {
    return this.machine()?.kategoria === 'pojazd'
      ? this.t('qr.machine.category_vehicle')
      : this.t('qr.machine.category_other');
  }

  private token(): string {
    return this.route.snapshot.paramMap.get('token') ?? '';
  }

  private t(key: string, params?: Record<string, string | number>): string {
    return this.translate.instant(key, params);
  }
}
