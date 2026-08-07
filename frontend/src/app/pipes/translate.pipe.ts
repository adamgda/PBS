import { Pipe, PipeTransform, inject, DestroyRef } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { TranslateService } from '../services/translate.service';

/**
 * Pipe tłumaczący klucze na tekst.
 * Użycie w szablonie: {{ 'common.buttons.save' | translate }}
 * Użycie z parametrami: {{ 'pracownicy.deleted' | translate: { name: 'Jan' } }}
 *
 * Uwaga: pipe jest nieczysty (impure), aby reagować na zmiany słownika,
 * ale dzięki sygnałom w TranslateService jest wydajny.
 */
@Pipe({
  name: 'translate',
  standalone: true,
  pure: false,
})
export class TranslatePipe implements PipeTransform {
  private readonly translateService = inject(TranslateService);

  transform(key: string, params?: Record<string, string | number>): string {
    return this.translateService.instant(key, params);
  }
}