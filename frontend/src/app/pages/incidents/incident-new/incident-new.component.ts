import { Component, inject, signal, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

import { IncidentsService } from '../../../services/incidents.service';
import { EquipmentService } from '../../../services/equipment.service';
import { ToastService } from '../../../services/toast.service';
import { TranslateService } from '../../../services/translate.service';
import { TranslatePipe } from '../../../pipes/translate.pipe';
import { ButtonComponent } from '../../../components/button/button.component';
import { AutocompleteSelectComponent, AutocompleteOption } from '../../../components/autocomplete-select/autocomplete-select.component';

import { IncidentType, CreateIncidentRequest } from '../../../models/incidents.model';

/**
 * Podstrona zgłoszenia awarii (poza modalem) — /incidents/new.
 * Formularz: typ (sprzęt/inne), wybór sprzętu (autocomplete), opis.
 * Po zapisie wraca do /incidents.
 */
@Component({
  selector: 'app-incident-new',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    TranslatePipe,
    ButtonComponent,
    AutocompleteSelectComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './incident-new.component.html',
})
export class IncidentNewComponent {
  private readonly incidentsService = inject(IncidentsService);
  private readonly equipmentService = inject(EquipmentService);
  private readonly toastService = inject(ToastService);
  private readonly translate = inject(TranslateService);
  private readonly router = inject(Router);

  readonly saving = signal<boolean>(false);
  readonly formTyp = signal<IncidentType>('sprzet');
  readonly formEquipmentId = signal<number | null>(null);
  readonly formOpis = signal<string>('');

  // Opcje autocomplete
  private readonly _equipmentOptions = signal<AutocompleteOption[]>([]);
  readonly equipmentOptions = this._equipmentOptions.asReadonly();

  constructor() {
    this.loadOptions();
  }

  onTypeChange(typ: IncidentType): void {
    this.formTyp.set(typ);
    if (typ !== 'sprzet') {
      this.formEquipmentId.set(null);
    }
  }

  onEquipmentSelected(opt: AutocompleteOption | null): void {
    this.formEquipmentId.set(opt ? Number(opt.value) : null);
  }

  saveCreate(): void {
    const typ = this.formTyp();
    const opis = this.formOpis().trim();
    if (!typ) {
      this.toastService.error(this.t('awaria.messages.type_required'));
      return;
    }
    if (!opis) {
      this.toastService.error(this.t('awaria.messages.description_required'));
      return;
    }
    if (typ === 'sprzet' && this.formEquipmentId() === null) {
      this.toastService.error(this.t('awaria.messages.equipment_required'));
      return;
    }

    const payload: CreateIncidentRequest = {
      typ,
      equipment_id: typ === 'sprzet' ? this.formEquipmentId() : null,
      opis,
    };

    this.saving.set(true);
    this.incidentsService.create(payload).subscribe({
      next: () => {
        this.saving.set(false);
        this.toastService.success(this.t('awaria.messages.created.success'));
        this.router.navigate(['/incidents']);
      },
      error: (err) => {
        this.saving.set(false);
        this.toastService.error(err?.error?.error || this.t('common.messages.error.generic'));
      },
    });
  }

  cancel(): void {
    this.router.navigate(['/incidents']);
  }

  private loadOptions(): void {
    this.equipmentService.list({ per_page: 100 }).subscribe({
      next: (res) => {
        this._equipmentOptions.set(
          res.data.map((e) => ({ value: e.id, label: e.nazwa, sublabel: e.numer_seryjny ?? undefined })),
        );
      },
      error: () => this._equipmentOptions.set([]),
    });
  }

  private t(key: string, params?: Record<string, string | number>): string {
    return this.translate.instant(key, params);
  }
}
