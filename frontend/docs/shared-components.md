# Komponenty współdzielone — konwencje frontend PBS

> **Reguła nadrzędna:** zanim napiszesz nowy element UI (przycisk, ikonę, pole, tabelę, dialog…),
> sprawdź, czy w `src/app/components/` nie ma już komponentu współdzielonego. **Nie duplikuj**
> klas Tailwind w wielu miejscach — to prowadzi do rozjazdu stylów. Jeśli brakuje wariantu —
> **rozbuduj istniejący komponent** zamiast tworzyć prawie identyczny obok.

## 1. Katalog komponentów (`src/app/components/`)

| Komponent | Selektor | Przeznaczenie |
|---|---|---|
| `ButtonComponent` | `app-button` | Tekstowe przyciski akcji (warianty) |
| `IconButtonComponent` | `app-icon-button` | Kwadratowe przyciski ikonowe |
| `AddButtonComponent` | `app-add-button` | Przycisk „Dodaj …" (ikona plusa) |
| `SvgIconComponent` | `app-svg-icon` | Ikony SVG (liniowe, `currentColor`) |
| `FormInputComponent` | `app-form-input` | Pole formularza z etykietą/walidacją |
| `SelectComponent` | `app-select` | Select (`<select>` + opcje, ngModel/CVA) |
| `FilterBarComponent` | `app-filter-bar` | Panel filtrów list |
| `DataTableComponent` | `app-data-table` | Tabela z sortowaniem i paginacją |
| `ConfirmDialogComponent` | `app-confirm-dialog` | Dialog potwierdzenia (`ConfirmService`) |
| `ToastNotificationComponent` | `app-toast-notification` | Powiadomienia (`ToastService`) |
| `OfflineBannerComponent` | `app-offline-banner` | Baner statusu offline |

Wszystkie są **standalone** (Angular) z `ChangeDetectionStrategy.OnPush` tam, gdzie to sensowne.

## 2. Przyciski — ZAWSZE używaj komponentu przycisku

Nie pisz surowego `<button class="…">`. Wybór zależy od typu:

### `app-button` — przycisk tekstowy
Warianty (`variant`): `primary` | `secondary` | `outline` | `danger` | `block`.

- `primary` — akcja główna (Zapisz, Wyślij, Filtruj) — wypełniony `pbs-primary`
- `secondary` — akcja poboczna wypełniona (Wyczyść) — szary
- `outline` — akcja poboczna z obrysem (Anuluj w modalach)
- `danger` — akcja destruktywna (Usuń, Wyloguj) — `pbs-danger`
- `block` — pełnoszerokościowy CTA (submit w formularzach auth)

Etykieta przez `label` (klucz tłumaczeniowy) **lub** content projection (dla spinnera/ikony).

```html
<app-button [label]="'common.buttons.save'" variant="primary" [disabled]="saving()" (clicked)="save()" />
<app-button [label]="'common.buttons.cancel'" variant="outline" (clicked)="close()" />
<app-button type="submit" variant="block" [disabled]="loading()">
  @if (loading()) { <app-svg-icon class="h-5 w-5 animate-spin text-white" name="spinner" /> {{ '...loading' | translate }} }
  @else { {{ '...button' | translate }} }
</app-button>
```

API: `label`, `variant`, `type`, `disabled`, `extraClass` (marginesy np. `mt-8`), wyjście `(clicked)`.

### `app-icon-button` — przycisk ikonowy
`tone`: `default` | `primary` | `warning` | `danger`; `size`: `md` | `sm`.

```html
<app-icon-button icon="close" [ariaLabel]="'common.buttons.close' | translate" (clicked)="close()" />
<app-icon-button icon="settings" tone="primary" [disabled]="!canManage(u)" (clicked)="openPermissions(u)" />
```

### `app-add-button` — „Dodaj …"
```html
<app-add-button [label]="'ustawienia.users.add'" (add)="openCreate()" />
```

### `app-select` — select (`<select>` + opcje)
Współdzielony select implementujący `ControlValueAccessor` — współpracuje z `[(ngModel)]`,
`[ngModel]` + `(ngModelChange)` oraz `[value]` + `(valueChange)` (drop-in zamiast natywnego `<select>`).
Rozmiar przez `size`: `sm` | `md` | `lg`. Opcje: `options` (`{ value, label }` lub `{ value, labelKey }`).
Opcjonalny placeholder (pusta opcja) przez `placeholder` / `placeholderKey`. Nie pisz surowego `<select>`.

```html
<app-select [options]="roles" size="lg" [ngModel]="modalRole()" (ngModelChange)="modalRole.set($event)" />
<app-select [options]="filter.options || []" placeholder="—" [ngModel]="values()[k] || ''" (ngModelChange)="change(k, $event)" />
<app-select [options]="perPageOptions" size="sm" extraClass="ml-2" [ngModel]="perPage()" (ngModelChange)="onPerPageChange($event)" />
```

### Anti-pattern
```html
<!-- ŹLE: duplikacja klas, brak współdzielenia -->
<button class="rounded-md bg-pbs-primary px-4 py-2 text-sm text-white hover:bg-blue-700" (click)="save()">Zapisz</button>
<!-- DOBRZE -->
<app-button [label]="'common.buttons.save'" variant="primary" (clicked)="save()" />
```

## 3. Ikony — `app-svg-icon`
Nie wstawiaj inline `<svg>`. Dodaj `@case('nazwa')` w `SvgIconComponent` i użyj
`<app-svg-icon name="nazwa" />`. Kolory przez `currentColor` (Tailwind `text-*`).

## 4. Zasady tworzenia / rozbudowy
1. **Najpierw szukaj, potem pisz** — może wystarczy dodać input/wariant.
2. **Konfigurowalność przez `@Input()`** — style przez warianty/tony, nie kopiowanie klas. `extraClass` tylko na marginesy.
3. **Standalone + `OnPush`**; importuj tylko to, co potrzebne.
4. **Tłumaczenia przez klucz** (pipe `translate`), nie hardcoded stringi.
5. **Kolory z tokenów `pbs-*`** (`tailwind.config.js`), nie surowe `bg-blue-700` poza wariantami.
6. **JSDoc** z przeznaczeniem i przykładem; **spójne wyjścia** `(clicked)`/`(add)`.

## 5. Świadome wyjątki (inline)
Sub-taby/segmented, nawigacja kalendarza, strzałki sortowania/paginacji, toggle hasła,
czyszczenie autouzupełniania, zamykanie toastu, przyciski app-baru — to kontrolki specyficzne
lub wewnętrzne. Gdy wzorzec zacznie się powtarzać — wyodrębnij komponent (pkt. 4).

## 6. Checklist PR
- [ ] Przycisk przez `app-button`/`app-icon-button`/`app-add-button`?
- [ ] Ikona to `@case` w `SvgIconComponent`, nie inline `<svg>`?
- [ ] Etykiety to klucze tłumaczeniowe?
- [ ] Brak powielonych klas istniejącego wariantu?
- [ ] Kolory z tokenów `pbs-*`?