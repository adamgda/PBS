import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute } from '@angular/router';

import { TranslatePipe } from '../../pipes/translate.pipe';

/**
 * Placeholder dla sekcji — zaimplementowane w kolejnych etapach.
 */
@Component({
  selector: 'app-placeholder',
  standalone: true,
  imports: [CommonModule, TranslatePipe],
  template: `
    <div class="p-6">
      <h1 class="text-2xl font-bold text-gray-900 mb-2">
        {{ 'common.menu.' + section() | translate }}
      </h1>
      <p class="text-gray-600">Sekcja w przygotowaniu — implementacja w kolejnym etapie.</p>
    </div>
  `,
})
export class PlaceholderComponent {
  private readonly route = inject(ActivatedRoute);

  section(): string {
    return this.route.snapshot.url[0]?.path ?? 'dashboard';
  }
}