# Port Baltic Shipping (PBS) — Dokumentacja Techniczna

> **Wersja dokumentu:** 1.8  
> **Data:** 2026-08-20  
> **Projekt:** Port Baltic Shipping (PBS)

---

## Spis treści

1. [Przegląd projektu](#1-przegląd-projektu)
2. [Założenia techniczne](#2-założenia-techniczne)
3. [Architektura aplikacji](#3-architektura-aplikacji)
4. [Struktura katalogów](#4-struktura-katalogów)
5. [Stack technologiczny](#5-stack-technologiczny)
6. [Frontend — Angular](#6-frontend--angular)
7. [Backend — PHP](#7-backend--php)
8. [Baza danych](#8-baza-danych)
9. [Bezpieczeństwo](#9-bezpieczeństwo)
10. [Sekcje aplikacji — szczegółowy opis](#10-sekcje-aplikacji--szczegółowy-opis)
11. [API endpoints](#11-api-endpoints)
12. [Modele danych](#12-modele-danych)
13. [Alerty i powiadomienia](#13-alerty-i-powiadomienia)
14. [Wymagania niefunkcjonalne](#14-wymagania-niefunkcjonalne)
15. [Środowiska](#15-środowiska)
16. [Skills (umiejętności AI)](#16-skills-umiejętności-ai)

---

## 1. Przegląd projektu

**Port Baltic Shipping (PBS)** to aplikacja webowa do kompleksowego zarządzania operacjami portowymi. System umożliwia:

- Zarządzanie pracownikami, sprzętem i terminalami portowymi
- Tworzenie harmonogramów i zleceń
- Raportowanie dzienne i awaryjne
- Analitykę i statystyki
- Zarządzanie użytkownikami i uprawnieniami
- Szybkie notatki to-do przypisane do zalogowanego konta (widget globalny, dostępny z każdej podstrony i wersji mobilnej)

Aplikacja jest podzielona na sekcje funkcyjne z granulowanym systemem uprawnień.

---

## 2. Założenia techniczne

| Założenie | Opis |
|---|---|
| Mobile First | Aplikacja projektowana i rozwijana z priorytetem dla urządzeń mobilnych |
| Frontend | Angular + Tailwind CSS |
| Backend | PHP (nowoczesne PHP 8.3+, z wykorzystaniem PSR standardów) |
| Baza danych | Relacyjna baza na serwerze (MySQL) |
| Autocomplete | Wszystkie listy rozwijane (select) muszą mieć autocomplete |
| Filtrowanie | Każda lista w aplikacji musi mieć opcję filtrowania |
| Ochrona danych | Minimalizacja dostępu do danych osobowych pracowników |
| Bezpieczeństwo | Wszelkie dobre praktyki security i szyfrowanie |
| Praca w terenie | Aplikacja musi działać stabilnie na telefonach w warunkach słabego zasięgu — offline-first, cache'owanie danych, minimalizacja transferu, gracefully degradation przy utracie połączenia |
| Optymalizacja mobilna | Wszystkie optymalizacje (frontend i backend) skupione na wydajności w warunkach polowych: małe payloady, lazy loading, kompresja, minimalne zużycie baterii i danych |

---

## 3. Architektura aplikacji

```
┌──────────────────────────────────────────────────┐
│                   Klient (przeglądarka)           │
│           Angular + Tailwind CSS                 │
│           PWA / Responsywny design               │
└──────────────────────┬───────────────────────────┘
                       │
                       │ HTTPS / REST API
                       │
┌──────────────────────▼───────────────────────────┐
│              Backend (PHP)                       │
│   REST API + autoryzacja + walidacja             │
│   Warstwa serwisowa + ORM + repozytoria          │
└──────────────────────┬───────────────────────────┘
                       │
                       │ PDO / Query Builder
                       │
┌──────────────────────▼───────────────────────────┐
│              Baza danych (MySQL)                 │
│   Relacyjna, znormalizowana                      │
│   Migracje + seedery                             │
└──────────────────────────────────────────────────┘
```

### Warstwy aplikacji

1. **Warstwa prezentacji** — Angular SPA, Tailwind CSS, komponenty, routing, guards
2. **Warstwa API** — PHP REST API, routowanie, middleware (CORS, auth, rate limiting)
3. **Warstwa logiki biznesowej** — Serwisy PHP, walidacja, kalkulacje
4. **Warstwa dostępu do danych** — Repozytoria / ORM, migracje, modele

---

## 4. Struktura katalogów

```
PBS/
├── .gitignore
├── README.md
├── skills-lock.json              # Konfiguracja skills AI
├── scripts/                      # Skrypty build/deploy (build-all.sh, build-backend.sh, deploy-backend.sh)
├── dist/                         # Wynik builda — gotowe do uploadu (backend/, frontend/, *.tar.gz)
├── docs/
│   ├── technical-documentation.md
│   └── implementation-roadmap.md  # Plan wdrożenia z etapami do odznaczania
├── backend/
│   ├── .env                      # Zmienne środowiskowe (czytane przez aplikację)
│   ├── .env.production           # Wzorzec konfiguracji produkcyjnej (kopiowany do .env przez build)
│   ├── public/                   # Document root (index.php + .htaccess — front controller)
│   ├── deploy/                   # Konfiguracja serwera (nginx-https.conf)
│   ├── src/
│   │   ├── Controllers/          # Kontrolery REST API
│   │   ├── Models/               # Modele danych / Entity
│   │   ├── Services/             # Serwisy biznesowe
│   │   ├── Repository/           # Repozytoria dostępu do danych
│   │   ├── Middleware/           # Middleware (auth, CORS, itp.)
│   │   ├── Helpers/              # Funkcje pomocnicze
│   │   └── Config/               # Konfiguracja aplikacji
│   ├── migrations/               # Migracje bazy danych
│   ├── seeds/                    # Seedery danych
│   └── tests/                    # Testy PHP (PHPUnit / Pest)
├── frontend/
│   ├── public/                   # Statyczne assety + .htaccess (SPA fallback)
│   ├── deploy/                   # Konfiguracja serwera (nginx-https.conf)
│   ├── src/
│   │   ├── app/
│   │   │   ├── components/       # Współdzielone komponenty
│   │   │   ├── pages/            # Strony / widoki sekcji
│   │   │   ├── services/         # Serwisy Angular (HTTP, auth)
│   │   │   ├── guards/           # Route guards (AuthGuard)
│   │   │   ├── models/           # Interfejsy / modele TypeScript
│   │   │   ├── directives/       # Dyrektywy Angular
│   │   │   └── pipes/            # Pipe'y transformujące
│   │   ├── assets/               # Grafiki, ikony, style globalne
│   │   └── environments/         # Konfiguracja środowisk (environment.ts, environment.prod.ts)
│   ├── angular.json
│   ├── package.json
│   └── tailwind.config.js
├── backups/                      # Kopie zapasowe bazy danych
└── mockup/                       # Prototypy HTML (mockupy)
    ├── index.html                # Dashboard
    ├── pracownicy.html           # Pracownicy
    ├── sprzet.html               # Sprzęt
    ├── terminale.html            # Terminale
    ├── harmonogram.html          # Harmonogram
    ├── analityka.html            # Analityka
    ├── raportowanie.html         # Raportowanie
    ├── ustawienia.html           # Ustawienia
    └── awaria.html               # Awaria
```

---

## 5. Stack technologiczny

### Frontend

| Technologia | Wersja (docelowa) | Zastosowanie |
|---|---|---|
| Angular | ≥ 18 | Framework SPA |
| TypeScript | ≥ 5.4 | Język programowania |
| Tailwind CSS | ≥ 3.4 | Stylowanie utility-first |
| RxJS | ≥ 7.8 | Reactive state management |
| Angular Signals | Angular 18+ | Nowoczesny reactivity model |

### Backend

| Technologia | Wersja (docelowa) | Zastosowanie |
|---|---|---|
| PHP | ≥ 8.3 | Język programowania |
| Composer | ≥ 2.7 | Dependency manager |
| PHPUnit / Pest | ≥ 11 / ≥ 3 | Testowanie |
| PHPStan | ≥ 1 (level 9) | Statyczna analiza kodu |

### Baza danych

| Technologia | Zastosowanie |
|---|---|
| MySQL | Relacyjna baza danych |
| Migracje | Wersjonowanie schematu bazy |
| Seedery | Dane początkowe / testowe |

### DevOps / Narzędzia

| Narzędzie | Zastosowanie |
|---|---|
| Git + GitHub | Kontrola wersji |
| Docker | Konteneryzacja środowiska |
| kubectl | Orkiestracja (opcjonalnie Kubernetes) |
| npm | Zarządzanie pakietami frontendu |

---

## 6. Frontend — Angular

### 6.1 Architektura modułów

Aplikacja Angular zbudowana na standalone components (Angular 18+). Każda sekcja biznesowa to osobny katalog w `pages/` z własnymi komponentami, serwisami i modelami.

### 6.1.1 Internacjonalizacja i teksty UI

**Wymóg:** Wszystkie teksty interfejsu użytkownika (etykiety, przyciski, nagłówki, komunikaty walidacji, placeholder'y, teksty toast/alertów, nazwy kolumn w tabelach, tytuły sekcji, menu nawigacji itp.) **muszą być przechowywane w obiekcie tłumaczeń** w plikach lokalizacyjnych, a nie bezpośrednio w szablonach komponentów.

Struktura katalogu lokalizacji:

```
frontend/
├── src/
│   └── locales/
│       └── pl/
│           ├── common.json      # Współdzielone teksty (przyciski, akcje, statusy, komunikaty)
│           ├── dashboard.json   # Teksty sekcji Dashboard
│           ├── pracownicy.json  # Teksty sekcji Pracownicy
│           ├── sprzet.json      # Teksty sekcji Sprzęt
│           ├── terminale.json   # Teksty sekcji Terminale
│           ├── harmonogram.json # Teksty sekcji Harmonogram
│           ├── analityka.json   # Teksty sekcji Analityka
│           ├── raportowanie.json# Teksty sekcji Raportowanie
│           ├── ustawienia.json  # Teksty sekcji Ustawienia
│           ├── awaria.json      # Teksty sekcji Awaria
│           └── notatki.json     # Teksty widgetu szybkich notatek to-do
```

Zasady:

- Język domyślny aplikacji: **pl (polski)** — wszystkie teksty UI muszą mieć wpis w `locales/pl/`.
- Każdy tekst odwoływany jest przez klucz (np. `common.buttons.save`, `pracownicy.form.firstName`).
- Hardcodowane stringi w szablonach (.html) i komponentach (.ts) są **niedozwolone** — wszystkie teksty użytkownika przechodzą przez serwis tłumaczeń.
- Serwis `TranslateService` udostępnia metodę `translate(key: string): string` oraz pipe `translate` do użycia w szablonach: `{{ 'pracownicy.title' | translate }}`.
- Struktura obiektu w `locales/pl/` jest jedynym źródłem prawdy dla tekstów UI — ułatwia to przyszłą internacjonalizację i utrzymanie spójności.
- Komunikaty błędów i powodzenia (toast, alerty) również pochodzą z plików lokalizacji, z podziałem na `success`, `error`, `warning`, `info`.
- Zmienne dynamiczne w tłumaczeniach obsługiwane są przez interpolację (np. `pracownicy.deleted.success: "Usunięto pracownika {{name}}"`).

### 6.1.2 Szablony komponentów — pliki vs inline

Konwencja rozmieszczania szablonów (zgodna ze wzorcem `AppComponent`):

- **Szablony w osobnych plikach `.html`** (`templateUrl: './nazwa.component.html'`) — **obowiązkowe** dla komponentów stron (`pages/*`, np. `LoginComponent`) oraz shella aplikacji (`AppComponent`). Decyduje o tym rozmiar i czytelność: widoki stron są rozbudowane i mieszanie ich z logiką `.ts` pogarsza nawigację po kodzie.
- **Inline `template`** — dopuszczalne **tylko** dla małych komponentów prezentacyjnych / współdzielonych (`components/*`), których szablon ma do kilkudziesięciu linii (np. `KpiCardComponent`, `OfflineBannerComponent`, `ToastNotificationComponent`, `SvgIconComponent`). Dla takich komponentów inline template + ewentualny `styles: []` utrzymują wszystko w jednym miejscu i poprawiają lokalną czytelność.
- **Zasada progowa:** jeżeli szablon komponentu przekracza ~50 linii lub zawiera rozbudowaną strukturę (panel, formularz, grid), przenieś go do osobnego pliku `.html` i użyj `templateUrl` zamiast inline `template`.
- **Style komponentu:** analogicznie — `styleUrl: './nazwa.component.css'` dla pliku zewnętrznego lub `styles: []` dla drobnych reguł. Pamiętaj o budżecie stylu komponentu (warning 4 kB / error 8 kB — patrz 14.7).
- **Nazewnictwo plików:** `nazwa.component.ts` + `nazwa.component.html` (+ opcjonalnie `nazwa.component.css`), trzymane razem w tym samym katalogu komponentu.

### 6.2 Routing

```
/dashboard              → DashboardComponent
/pracownicy             → PracownicyListComponent
/pracownicy/:id         → PracownicyEditComponent
/sprzet                 → SprzetListComponent
/sprzet/:id            → SprzetDetailComponent
/terminale              → TerminaleListComponent
/harmonogram            → HarmonogramComponent (kalendarz)
/analityka              → AnalitykaComponent
/raportowanie           → RaportowanieComponent
/raportowanie/terminal  → RaportTerminalComponent
/raportowanie/pojazd    → RaportPojazdComponent
/ustawienia             → UstawieniaComponent
/ustawienia/uzytkownicy → UzytkownicyComponent
/ustawienia/alerty      → AlertyConfigComponent
/awaria                 → AwariaListComponent
/awaria/zglos           → AwariaZglosComponent
/awaria/:id             → AwariaDetailComponent
```

### 6.3 Auth & Guards

- `AuthGuard` — chroni wszystkie ścieżki wymagające zalogowania
- `PermissionGuard` — sprawdza uprawnienia użytkownika do danej sekcji; przy braku uprawnień przekierowuje do pierwszej dostępnej sekcji (`AuthService.firstAvailableRoute()`), a nie sztywno do `/dashboard` (brak pętli)
- `DefaultRouteGuard` — trasa domyślna (`''`) i wildcard (`**`) kierują do pierwszej dostępnej sekcji na bazie uprawnień (lub `/login` dla niezalogowanych); trasy te używają `RedirectComponent` (komponent-fallback, nigdy się nie renderuje, bo guard zawsze zwraca `UrlTree` — wymagany przez walidację routingu NG04014)
- `AuthService.refreshCurrentUser()` — wywoływane przy starcie aplikacji (`AppComponent`) i po zalogowaniu; pobiera `GET /api/v1/auth/me` i synchronizuje rolę/uprawnienia w `_currentUser` + `localStorage` (backend jako jedyne źródło prawdy)
- Token JWT przechowywany w `HttpOnly` cookie lub `localStorage` + refresh token

### 6.4 Współdzielone komponenty

| Komponent | Opis |
|---|---|
| `DataTableComponent` | Uniwersalna tabela z sortowaniem, filtrowaniem, paginacją |
| `AutocompleteSelectComponent` | Select z autocomplete (wyszukiwanie przez API) |
| `FilterBarComponent` | Panel filtrów dla list |
| `KpiCardComponent` | Karta KPI dla dashboardu |
| `AlertWidgetComponent` | Widget alertów |
| `TimelineComponent` | Komponent osi czasu (historia sprzętu) |
| `CalendarComponent` | Komponent kalendarza (harmonogram) |
| `ConfirmDialogComponent` | Dialog potwierdzenia akcji |
| `ToastNotificationComponent` | Powiadomienia toast |
| `FormInputComponent` | Współdzielone pole formularza (input z opcjonalną ikoną wiodącą, etykietą/placeholderem przez klucze tłumaczeń oraz opcjonalnym przełącznikiem widoczności hasła). Implementuje `ControlValueAccessor` — baza biblioteki elementów UI formularzy (patrz 6.4.1) |
| `QuickNotesWidgetComponent` | Wysuwany z boku ekranu widget szybkich notatek to-do (dodawanie, odznaczanie jako wykonane, usuwanie, czyszczenie całej listy) — dostępny globalnie na każdej podstronie i w wersji mobilnej, dane przypisane do zalogowanego użytkownika |

### 6.4.1 Biblioteka elementów UI formularzy

Zamiast definiować pola formularzy (email, hasło, tekst, numer itp.) od nowa w każdym widoku, projekt udostępnia bazę reużywalnych elementów UI w `src/app/components/`. Podstawą jest `FormInputComponent` (`app-form-input`), który pokrywa najczęstszy przypadek: pole `<input>` z ikoną wiodącą, etykietą i placeholderem.

**`FormInputComponent`** (`src/app/components/form-input/form-input.component.ts`):

- Standalone, `OnPush`, implementuje `ControlValueAccessor` → współpracuje z formami szablonowymi (`[ngModel]` + `(ngModelChange)` lub `[(ngModel)]`) i reaktywnymi (`formControlName`).
- `@Input()`:
  - `labelKey` / `label` — etykieta przez klucz tłumaczeń (zalecane, 6.1.1) lub bezpośrednia,
  - `placeholderKey` / `placeholder` — placeholder przez klucz tłumaczeń lub bezpośredni,
  - `type` — `text` | `email` | `password` | `number` | `tel` | `url` | `search`,
  - `icon` — nazwa ikony z `SvgIconComponent` (np. `mail`, `lock`); pusty = brak ikony,
  - `name`, `autocomplete`, `required`, `inputId` (auto-generowane gdy puste),
  - `passwordToggle` — włącza przycisk pokazywania/ukrywania hasła (ikony `eye`/`eye-off`), z `aria-label` tłumaczonym przez `showPasswordLabelKey` / `hidePasswordLabelKey`.
- Stylizacja wg 6.8.5 (tokeny `pbs-*`, skala `gray-*`, `focus:ring-pbs-primary`), w pełni Tailwind utility-first — bez własnego CSS.

**Przykład użycia (pola logowania):**

```html
<app-form-input
  type="email"
  name="email"
  icon="mail"
  autocomplete="email"
  [required]="true"
  labelKey="common.auth.email"
  placeholderKey="common.auth.email"
  [ngModel]="email()"
  (ngModelChange)="email.set($event)"
/>
<app-form-input
  type="password"
  name="password"
  icon="lock"
  autocomplete="current-password"
  [required]="true"
  [passwordToggle]="true"
  labelKey="common.auth.password"
  placeholderKey="common.auth.password"
  [ngModel]="password()"
  (ngModelChange)="password.set($event)"
/>
```

**Zasady rozszerzania biblioteki:**

1. Każde pole formularza używane więcej niż raz **musi** być zrealizowane jako współdzielony komponent w `components/`, nie powielane w szablonach stron.
2. Nowe elementy (textarea, select, checkbox, radio) budujemy jako `ControlValueAccessor` i dodajemy wpis w tabeli 6.4 oraz (jeśli potrzeba) w 6.4.1.
3. Etykiety, placeholdery, komunikaty błędów i `aria-label` zawsze przez klucze tłumaczeń (`*Key`) — zgodnie z 6.1.1.
4. Ikony wyłącznie z `SvgIconComponent` — brak zewnętrznych bibliotek (6.8.7 pkt 4).
5. Komponenty pól formularza nie zawierają logiki biznesowej — są czysto prezentacyjne/wiązanie wartości.

### 6.5 State Management

- Angular Signals dla lokalnego stanu komponentów
- Serwisy z `BehaviorSubject` lub `signal()` dla stanu współdzielonego
- Cache HTTP z interceptorem

### 6.6 Stylowanie

- Tailwind CSS — utility-first, brak osobnych plików CSS (poza globalnymi)
- Design system: zmienne Tailwind dla kolorów firmowych, spacing, typografii
- Responsywność: breakpointy Tailwind (`sm`, `md`, `lg`, `xl`)

### 6.6.1 Design tokens (paleta i typografia)

Źródłem prawdy dla kolorów jest `frontend/tailwind.config.js` — kategoryzacja `pbs.*`. **Wszystkie nowe komponenty używają wyłącznie tokenów `pbs.*`** (oraz skali neutralnej Tailwind), nie surowych wartości hex. Zapewnia to spójny wygląd i pozwala w przyszłości przejść na zmienne CSS / ciemny motyw bez refaktoryzacji szablonów.

| Token Tailwind | Wartość | Zastosowanie |
|---|---|---|
| `bg-pbs-primary` | `#1e3a5f` (granat) | Tła nawigacji (sidebar, drawer, app-bar), przyciski pierwszorzędne, nagłówki |
| `bg-pbs-secondary` | `#3b82f6` (niebieski) | Akcenty, awatary, linki/elementy aktywne |
| `bg-pbs-accent` / `pbs-warning` | `#f59e0b` (bursztyn) | Oznaczenia ostrzegawcze, akcenty |
| `bg-pbs-danger` | `#ef4444` (czerwony) | Błędy, akcje destrukcyjne, awarie |
| `bg-pbs-success` | `#22c55e` (zielony) | Komunikaty sukcesu, statusy pozytywne, trendy rosnące |
| `bg-pbs-info` | `#3b82f6` | Komunikaty informacyjne |

Konwencje typografii (Tailwind):

| Element | Klasy |
|---|---|
| Tytuł sekcji / strony | `text-2xl font-bold text-gray-900` |
| Podtytuł / opis | `text-gray-600` |
| Etykieta karty KPI | `text-sm text-gray-500` |
| Wartość KPI | `text-3xl font-bold text-gray-900` |
| Treść w nawigacji | `text-sm font-medium text-blue-100/80` (na tle `pbs-primary`) |
### 6.7 Layout aplikacji (app shell)

Shell aplikacji (główny szkielet nawigacji i obszaru treści) jest zdefiniowany w `app.component.html` / `app.component.ts` i **musi pozostać wspólny dla wszystkich sekcji**. Sekcje (pages) renderują się w `<router-outlet />` wewnątrz obszaru treści — **nie definiują własnej nawigacji ani własnego app-baru**.

#### 6.7.1 Struktura responsywna (mobile-first)

| Breakpoint | Nawigacja | Brand | Profil / wyloguj |
|---|---|---|---|
| Mobile (`< md`) | Górny app-bar + wysuwany **drawer** (hamburger) | W nagłówku draweru i app-baru | W stopce draweru (blok profilu) |
| Desktop (`≥ md`) | Stały lewy **sidebar** (`w-64`) | Na górze sidebaru | W stopce sidebaru (blok profilu) |

Elementy:

- **App-bar** (`<header>`, `sticky top-0 z-20`, `h-14`, `bg-pbs-primary/95` z `backdrop-blur`): po lewej hamburger (mobile) + tytuł aktywnej sekcji (`activeTitle`); po prawej awatar (mobile). Tytuł sekcji jest wyliczany w `AppComponent` z `NavigationEnd` (dopasowanie `currentUrl.startsWith(item.path)`).
- **Drawer** (mobile, `fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw]`, animacja `transform` + `-translate-x-full`): zawiera brand, listę nawigacji (`navList`) i blok profilu (`userBlock`). Zamykany automatycznie po nawigacji (subskrypcja `router.events`) oraz kliknięciu w overlay.
- **Overlay** (`fixed inset-0 z-40 bg-black/50 backdrop-blur-sm`, tylko mobile, tylko gdy drawer otwarty).
- **Sidebar** (desktop, `fixed inset-y-0 left-0 z-30 w-64`): brand, lista nawigacji, blok profilu.
- **Obszar treści** (`<main class="md:pl-64">` → `<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6">`): `<router-outlet />`.

Lista nawigacji i blok profilu są współdzielone między drawerem a sidebarzem przez `<ng-template #navList>` / `<ng-template #userBlock>` i `NgTemplateOutlet` (zasada DRY — **nigdy nie duplikować** listy elementów menu).

Aktywne stany linków: `routerLinkActive="bg-white/15 text-white"` z `routerLinkActiveOptions: { exact: false }`. Każdy element menu posiada ikonę (pole `icon` w `menuItems`).

#### 6.7.2 Komponent ikon `SvgIconComponent`

`src/app/components/svg-icon/svg-icon.component.ts` — standalone, `OnPush`, brak zewnętrznych zależności. Ikony liniowe SVG `24×24`, `fill="none"`, `stroke="currentColor"`, `stroke-width="1.8"`, przez co dziedziczą kolor tekstu (np. `text-white`, `text-blue-100/80`).

Dostępne nazwy (`@Input() name`): `dashboard`, `pracownicy`, `sprzet`, `terminale`, `harmonogram`, `analityka`, `raportowanie`, `ustawienia`, `awaria`, `menu`, `close`, `logout`.

```html
<span class="text-blue-100/80"><app-svg-icon name="sprzet" /></span>
```

**Zasada:** nowe ikony dodajemy do `SvgIconComponent` (kolejny `@case`), nie wprowadzamy zewnętrznej biblioteki ikon. Rozmiar kontrolujemy klasami na `:host` / wrapperze, nie atrybutami SVG.

#### 6.7.3 Internacjonalizacja layoutu

Klucze layoutu w `locales/pl/common.json`, sekcja `nav`:

```json
"nav": { "menu": "Menu", "close": "Zamknij menu", "account": "Konto" }
```

Etykiety elementów menu pochodzą z `common.menu.*` (zgodnie z 6.1.1).

#### 6.7.4 Dostępność i motion

- Wszystkie przyciski ikonowe mają `aria-label` (tłumaczone).
- Drawer ma `aria-hidden` zależne od stanu; przycisk hamburgera `aria-expanded`.
- `env(safe-area-inset-*)` w paddingach (notch / domowy pasek) dla app-baru, draweru i obszaru treści.
- Animacje draweru wyłączane przy `prefers-reduced-motion: reduce` (`app.component.css`).
- Minimalny cel dotykowy: 40–44 px (`h-10`/`h-11` dla przycisków w nawigacji).

### 6.8 Zasady budowy widoków sekcji (spójność dashboard i stron)

Aby zachować stały wygląd wszystkich przyszłych komponentów (dashboard, listy, formularze), twórcy sekcji **muszą** trzymać się poniższych wzorców. Wzorce są zgodne z istniejącymi komponentami (`KpiCardComponent`, `AlertWidgetComponent`, strony `login`/`dashboard`).

#### 6.8.1 Kontener strony

Każda strona sekcji renderuje się już we wspólnym kontenerze shellu (`max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6`). **Strona nie powtarza tego kontenera ani nie dodaje własnego app-baru.** Strona zaczyna się od nagłówka sekcji:

```html
<div>
  <h1 class="text-2xl font-bold text-gray-900">{{ 'sekcja.title' | translate }}</h1>
  <p class="mt-1 text-gray-600">{{ 'sekcja.subtitle' | translate }}</p>
  <!-- treść -->
</div>
```

#### 6.8.2 Karta (card) — podstawowy blok

Standardowa karta: `bg-white rounded-lg shadow p-5` (wzór z `KpiCardComponent`). Warianty:

| Wariant | Klasy |
|---|---|
| Karta KPI | `bg-white rounded-lg shadow p-5 flex flex-col gap-2` |
| Karta sekcji / panel | `bg-white rounded-lg shadow p-5` (lub `p-6`) |
| Karta interaktywna | dodać `hover:shadow-md transition-shadow cursor-pointer` |

#### 6.8.3 Siatki responsywne (mobile-first)

Siatki zawsze zaczynają się od 1 kolumny na mobile i rosną na większych ekranach:

```html
<!-- KPI dashboard -->
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
  <app-kpi-card ... />
</div>

<!-- Lista kart -->
<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
  ...
</div>
```

#### 6.8.4 Przyciski

| Typ | Klasy bazowe |
|---|---|
| Pierwszorzędny | `px-4 py-2 text-sm font-medium text-white bg-pbs-primary rounded-md hover:bg-blue-700 transition-colors` |
| Destrukcyjny | `... bg-pbs-danger hover:bg-red-600 ...` |
| Zabarwiony (secondary) | `... bg-pbs-secondary hover:bg-blue-600 ...` |
| Ghost / w nawigacji | `text-blue-100/80 hover:bg-white/10 hover:text-white rounded-lg` (na tle `pbs-primary`) |
| Ikona | `grid h-10 w-10 place-items-center rounded-lg` |

Wszystkie przyciski mają `type="button"` (lub `submit` w formularzu) i `transition-colors`. Stany `disabled` przez `disabled:opacity-50`.

#### 6.8.5 Pola formularzowe

Wzór z `LoginComponent`:

```html
<input
  class="block w-full px-3 py-2 border border-gray-200 rounded-md shadow-sm
         focus:ring-2 focus:ring-pbs-primary focus:border-transparent"
/>
```

Etykiety: `block text-sm font-medium text-gray-700`. Błędy walidacji: `text-sm text-red-600 bg-red-50 p-3 rounded-md`.

**Preferowane podejście:** zamiast pisać pola ręcznie, używaj współdzielonych komponentów z biblioteki (6.4.1), w szczególności `FormInputComponent` — pokrywa pole z ikoną, etykietą, placeholderem i przełącznikiem hasła, utrzymując spójne style i tokeny. Ręczne `<input>` z wiodącą ikoną / przełącznikiem hasła w widokach stron jest uznawane za duplikację i powinno być zastępowane `app-form-input`.

#### 6.8.6 Statusy i kolory semantyczne

Mapowanie statusów biznesowych na tokeny (stosowane w `DataTableComponent`, `KpiCardComponent`, listach awarii):

| Status / znaczenie | Klasa |
|---|---|
| Sukces / aktywny / trend ↑ | `text-pbs-success` (lub `text-green-600`) |
| Błąd / awaria / trend ↓ | `text-pbs-danger` (lub `text-red-600`) |
| Ostrzeżenie / wygasające | `text-pbs-warning` (lub `text-amber-600`) |
| Informacja | `text-pbs-info` (lub `text-blue-600`) |

W `KpiCardComponent` trend >= 0 → `text-green-600`, < 0 → `text-red-600` (z górą/dół `▲`/`▼`).

#### 6.8.7 Reguły wymuszane

1. **Brak własnego CSS** — wyłącznie klasy Tailwind; wyjątki tylko dla globalnych animacji/motion w `app.component.css` (maksymalnie kilka reguł). Budżet stylu komponentu: **warning 4 kB / error 8 kB** (patrz 14.7) — wymusza to utility-first.
2. **Brak surowych kolorów** — używać `pbs-*` i skali `gray-*`/`red-*`/`green-*` z Tailwind.
3. **Brak duplikacji shella** — sekcje nie renderują nawigacji, app-baru, overlay; korzystają z `<router-outlet />`.
4. **Brak zewnętrznych bibliotek UI/ikon** — jedynym źródłem ikon jest `SvgIconComponent`. Wprowadzenie nowej biblioteki komponentów UI wymaga aktualizacji tej dokumentacji i akceptacji.
5. **Teksty przez `translate`** — zgodnie z 6.1.1, żadnych hardcodowanych stringów w widokach.
6. **Standalone + Signals** — nowe komponenty: `standalone: true`, `ChangeDetectionStrategy.OnPush`, stan przez `signal()`/`computed()`; wstrzykiwanie przez `inject()`.
7. **Touch target ≥ 40 px** — wszystkie elementy klikalne na mobile.
8. **Safe-area** — dolny padding obszaru treści uwzględnia `env(safe-area-inset-bottom)`; elementy przy krawędziach górnych `env(safe-area-inset-top)`.

### 6.9 Strony publiczne (autoryzacja)

Strony publiczne (logowanie, ustawienie hasła) **nie korzystają z app-shellu** (6.7) — renderują się bezpośrednio w `<router-outlet />` pustego shella (`@else` w `AppComponent`). Wzorzec referencyjny to `LoginComponent`:

- **Split-screen (desktop ≥ lg):** lewy panel brandowy w gradiencie `bg-gradient-to-br from-pbs-primary to-[#102640]` z logo „P" (badge `bg-white/15`), tagline, listą wyróżników (ikony z `SvgIconComponent` na `bg-white/10`) i stopką copyright; prawy panel formularza na `bg-gray-50`.
- **Mobile:** tylko prawy panel z brandem (`lg:hidden`, badge `bg-pbs-primary`), nagłówkiem i formularzem; paddingi `env(safe-area-inset-*)`.
- **Pola z ikoną:** ikona wewnątrz `relative` kontenera (`absolute left-3 top-1/2 -translate-y-1/2 text-gray-400`), input z `pl-11`. Pole hasła ma przełącznik widoczności (`showPassword` signal → `eye`/`eye-off`, `aria-label` tłumaczone).
- **Spinner** ładowania: `<app-svg-icon class="animate-spin" name="spinner" />` zamiast tekstu „…".
- **Alert błędu:** `bg-red-50 text-red-700` z ikoną `alert`, `role="alert"`.
- Wszystkie teksty pochodzą z `common.auth.*` oraz `common.menu.*`. Panel brandowy wyświetla listę opcji zarządzania (na bazie nawigacji dashboard) — każdy wiersz: ikona z `SvgIconComponent` + etykieta z `common.menu.*` (Dashboard, Pracownicy, Sprzęt, Terminale, Harmonogram, Analityka, Raportowanie, Awaria, Ustawienia), z nagłówkiem `common.auth.options` („Zakres zarządzania"). Pozostałe klucze: `login_subtitle`, `login_loading`, `show_password`, `hide_password`, `tagline`, `copyright`.

---

## 7. Backend — PHP

### 7.1 Architektura

Backend oparty na architekturze REST API z warstwami:

1. **Routing** — Prosty router PHP (np. Nikic FastRoute lub autorski)
2. **Middleware** — pipeline: CORS → Auth → Rate Limiting → Router → Response
3. **Kontrolery** — Cienkie kontrolery, delegują logikę do serwisów
4. **Serwisy** — Logika biznesowa
5. **Repozytoria** — Dostęp do danych (PDO/Query Builder)
6. **DTO / Value Objects** — Typowane obiekty transferowe

### 7.2 REST API — standardy

- Format odpowiedzi: JSON
- Wersjonowanie: `/api/v1/...`
- HTTP methods: GET, POST, PUT, PATCH, DELETE
- Statusy HTTP: 200, 201, 204, 400, 401, 403, 404, 422, 500
- Paginacja: `?page=1&per_page=25` + nagłówki `X-Total-Count`
- Filtrowanie: `?filter[status]=active&filter[terminal_id]=5`
- Sortowanie: `?sort=name&order=asc`

### 7.3 Autoryzacja

- JWT (JSON Web Token) — token dostępowy + refresh token
- JWT (JSON Web Token) — token dostępowy + refresh token
- Role: wyłącznie `super_admin` (konto główne, seedowane) oraz `admin` (konta tworzone w Ustawienia → Użytkownicy). **Pracownicy nie posiadają kont ani dostępu do aplikacji** — są zasobem zarządzanym w sekcji Pracownicy.
- Uprawnienia per sekcja: `dashboard`, `pracownicy`, `sprzet`, `terminale`, `harmonogram`, `analityka`, `raportowanie`, `ustawienia`, `awaria`

**Jedno źródło prawdy (back-end):** `PermissionMiddleware` weryfikuje uprawnienia **z bazy danych** (odczyt `users.permissions` po `user_id`), a nie z claima JWT. Dzięki temu backend zawsze egzekwuje *aktualne* uprawnienia — zmiana uprawnień w adminie obowiązuje natychmiast (eliminacja problemu „stale token", czyli rozjazdu między uprawnieniami we froncie a backendem).

**Synchronizacja frontendu:** `GET /api/v1/auth/me` zwraca świeży stan (rola, uprawnienia, `is_active`). Frontend pobiera go przy starcie aplikacji (`AuthService.refreshCurrentUser()`) i po zalogowaniu, przez co menu i guardy zawsze odpowiadają stanowi na backendzie.

**Dane referencyjne:** endpointy zwracające słowniki/dane do filtrów — `GET /api/v1/terminals` i `GET /api/v1/equipment` — są dostępne dla **każdego zalogowanego użytkownika** (tylko odczyt). Mutacje (POST/PUT/DELETE) pozostają pod uprawnieniami sekcji `terminale`/`sprzet`. Dzięki temu strona Pracownicy (która potrzebuje terminali i sprzętu do filtrów) działa dla użytkownika z samym uprawnieniem `pracownicy`.

### 7.4 Middleware pipeline

```
Request → CORS Middleware → Auth Middleware → Permission Middleware 
       → Rate Limiter → Router → Kontroler → Serwis → Repozytorium
       → JSON Response (z transformacją)
```

### 7.5 Walidacja

- Walidacja danych wejściowych na poziomie kontrolera (lub dedykowanych Request classes)
- Walidacja biznesowa na poziomie serwisu
- Strict typing (PHP 8.3+)

---

## 8. Baza danych

### 8.1 Encje / Modele

#### 8.1.1 Użytkownicy (`users`)

| Kolumna | Typ | Opis |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| email | VARCHAR(255) UNIQUE | Email użytkownika |
| password_hash | VARCHAR(255) | Hash hasła (bcrypt/argon2) |
| role | ENUM('super_admin', 'admin', 'user') | Rola (aplikacyjnie: wyłącznie `super_admin`/`admin`) |
| permissions | JSON | Uprawnienia per sekcja |
| is_active | BOOLEAN | Czy konto aktywne |
| created_at | DATETIME | |
| updated_at | DATETIME | |

> **Uwaga:** Pracownicy (tabela `employees`) **nie posiadają kont** w `users` i nie mają dostępu do aplikacji. Dostęp mają wyłącznie konta `super_admin` i `admin` tworzone w Ustawienia → Użytkownicy. Rola `user` nie jest już tworzona przez żaden przepływ.

#### 8.1.2 Pracownicy (`employees`)

| Kolumna | Typ | Opis |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| imie | VARCHAR(100) | |
| nazwisko | VARCHAR(100) | |
| telefon | VARCHAR(20) NULLABLE | Opcjonalne (dane kontaktowe) |
| email | VARCHAR(255) NULLABLE | Opcjonalne (dane kontaktowe) — nie generuje konta ani hasła |
| current_terminal_id | INT UNSIGNED FK → terminals.id | NULLABLE |
| current_sprzet_id | INT UNSIGNED FK → equipment.id | NULLABLE |
| is_active | BOOLEAN | |
| created_at | DATETIME | |
| updated_at | DATETIME | |

#### 8.1.3 Dokumenty pracownika (`employee_documents`)

| Kolumna | Typ | Opis |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| employee_id | INT UNSIGNED FK → employees.id | |
| nazwa | VARCHAR(255) | Nazwa dokumentu |
| numer_dokumentu | VARCHAR(100) | |
| data_wydania | DATE | |
| data_waznosci | DATE | |
| plik | VARCHAR(255) | NULLABLE (ścieżka do pliku) |
| created_at | DATETIME | |
| updated_at | DATETIME | |

#### 8.1.4 Sprzęt (`equipment`)

| Kolumna | Typ | Opis |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| kategoria | ENUM('pojazd', 'inne') | |
| nazwa | VARCHAR(255) | |
| numer_seryjny | VARCHAR(100) | NULLABLE |
| qr_token | CHAR(64) UNIQUE | NULLABLE — publiczny token maszyny dla kodu QR (podstrona `/qr/{token}`) |
| current_employee_id | INT UNSIGNED FK → employees.id | NULLABLE |
| current_terminal_id | INT UNSIGNED FK → terminals.id | NULLABLE |
| is_active | BOOLEAN | |
| created_at | DATETIME | |
| updated_at | DATETIME | |

#### 8.1.5 Pojazdy — dodatkowe pola (`vehicle_details`)

| Kolumna | Typ | Opis |
|---|---|---|
| equipment_id | INT UNSIGNED PK FK → equipment.id | |
| ostatni_przebieg | INT UNSIGNED | km |
| ostatni_serwis_olejowy | DATE | NULLABLE |
| ostatnia_awaria | DATETIME | NULLABLE |
| data_ostatniej_oc | DATE | NULLABLE |
| wynik_ostatniej_oc | TEXT | NULLABLE |

#### 8.1.6 Planowanie przeglądów pojazdu (`vehicle_service_plans`)

| Kolumna | Typ | Opis |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| equipment_id | INT UNSIGNED FK → equipment.id | |
| typ_przegladu | VARCHAR(255) | Np. "serwis olejowy", "przegląd UDT" |
| interwał_km | INT UNSIGNED | NULLABLE |
| interwał_dni | INT UNSIGNED | NULLABLE |
| data_ostatniego_wykonania | DATE | NULLABLE |
| data_nastepnego_planowanego | DATE | NULLABLE |
| is_active | BOOLEAN | |

#### 8.1.7 Historia sprzętu (`equipment_history`)

| Kolumna | Typ | Opis |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| equipment_id | INT UNSIGNED FK → equipment.id | |
| typ | ENUM('przebieg', 'serwis', 'awaria', 'przypisanie', 'inne') | |
| opis | TEXT | |
| data | DATETIME | |
| created_by | INT UNSIGNED FK → users.id | |

#### 8.1.8 Terminale (`terminals`)

| Kolumna | Typ | Opis |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| nazwa | VARCHAR(255) | |
| adres | TEXT | |
| operator | VARCHAR(255) | |
| telefon_operatora | VARCHAR(20) | |
| email_operatora | VARCHAR(255) | |
| is_active | BOOLEAN | |
| created_at | DATETIME | |
| updated_at | DATETIME | |

#### 8.1.9 Zlecenia / Harmonogram (`orders`)

| Kolumna | Typ | Opis |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| numer_zlecenia | VARCHAR(50) UNIQUE | |
| klient_nazwa | VARCHAR(255) | |
| terminal_id | INT UNSIGNED FK → terminals.id | |
| data_rozpoczecia | DATETIME | |
| data_zakonczenia | DATETIME | |
| zakres_prac | TEXT | |
| wartosc_pln | DECIMAL(12,2) | |
| status | ENUM('nowe', 'w_realizacji', 'zakonczone') | |
| created_at | DATETIME | |
| updated_at | DATETIME | |

#### 8.1.10 Przypisania pracowników do zlecenia (`order_employees`)

| Kolumna | Typ | Opis |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| order_id | INT UNSIGNED FK → orders.id | |
| employee_id | INT UNSIGNED FK → employees.id | NULLABLE (ON DELETE SET NULL — zachowanie historii) |
| rola | ENUM('operator','brygadzista','sztauer','lukowy','operator_zurawia') | NULLABLE — stanowisko pełnione w tym zleceniu (rola dnia) |
| godziny | DECIMAL(5,2) | NULLABLE — przepracowane godziny w tym zleceniu (do rozliczenia × stawka) |

#### 8.1.11 Przypisania sprzętu do zlecenia (`order_equipment`)

| Kolumna | Typ | Opis |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| order_id | INT UNSIGNED FK → orders.id | |
| equipment_id | INT UNSIGNED FK → equipment.id | |

#### 8.1.12 Awarie (`incidents`)

| Kolumna | Typ | Opis |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| typ | ENUM('sprzet', 'inne') | |
| equipment_id | INT UNSIGNED FK → equipment.id | NULLABLE |
| opis | TEXT | |
| status | ENUM('zgloszona', 'w_trakcie_naprawy', 'naprawiona', 'zamknieta') | |
| data_zgloszenia | DATETIME | |
| data_zakonczenia | DATETIME | NULLABLE |
| zgloszona_przez | INT UNSIGNED FK → users.id | |
| created_at | DATETIME | |
| updated_at | DATETIME | |

#### 8.1.13 Komentarze do awarii (`incident_comments`)

| Kolumna | Typ | Opis |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| incident_id | INT UNSIGNED FK → incidents.id | |
| tresc | TEXT | |
| user_id | INT UNSIGNED FK → users.id | |
| created_at | DATETIME | |

#### 8.1.14 Historia statusów awarii (`incident_status_history`)

| Kolumna | Typ | Opis |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| incident_id | INT UNSIGNED FK → incidents.id | |
| status_od | ENUM(...) | |
| status_do | ENUM(...) | |
| zmieniony_przez | INT UNSIGNED FK → users.id | |
| created_at | DATETIME | |

#### 8.1.15 Raporty dzienne — terminal (`daily_terminal_reports`)

| Kolumna | Typ | Opis |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| terminal_id | INT UNSIGNED FK → terminals.id | |
| data_raportu | DATE | |
| opis | TEXT | |
| uwagi | TEXT | NULLABLE |
| utworzony_przez | INT UNSIGNED FK → users.id | |
| created_at | DATETIME | |
| updated_at | DATETIME | |

#### 8.1.16 Raporty dzienne — pojazd (`daily_vehicle_reports`)

| Kolumna | Typ | Opis |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| equipment_id | INT UNSIGNED FK → equipment.id | |
| data_raportu | DATE | |
| aktualny_przebieg | INT UNSIGNED | |
| przebieg_oc | TEXT | Opis obsługi codziennej |
| uwagi | TEXT | NULLABLE |
| utworzony_przez | INT UNSIGNED FK → users.id | |
| created_at | DATETIME | |
| updated_at | DATETIME | |

#### 8.1.17 Ustawienia alertów (`alert_settings`)

| Kolumna | Typ | Opis |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| email_odbiorcy | VARCHAR(255) | E-mail docelowy |
| typ_alertu | ENUM(...) | |
| czy_aktywny | BOOLEAN | |
| czas_wysylki | TIME | NULLABLE (np. dla braku raportu OC) |
| created_at | DATETIME | |
| updated_at | DATETIME | |

#### 8.1.18 Szybkie notatki to-do (`user_notes`)

| Kolumna | Typ | Opis |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| user_id | INT UNSIGNED FK → users.id | Właściciel notatki (zalogowane konto) |
| tresc | VARCHAR(500) | Treść notatki to-do |
| is_done | BOOLEAN | Czy odznaczona jako wykonana (domyślnie `false`) |
| kolejnosc | INT | Opcjonalna kolejność wyświetlania (domyślnie `0`) |
| created_at | DATETIME | Data utworzenia |
| updated_at | DATETIME | Data ostatniej aktualizacji |

Zasady:
- Notatki są prywatne i przypisane wyłącznie do konta (`user_id`) — brak współdzielenia między użytkownikami
- IDOR protection obowiązkowe: każda operacja musi weryfikować, że `user_notes.user_id` odpowiada ID zalogowanego użytkownika (z JWT)
- Usunięcie użytkownika (`DELETE /api/v1/users/{id}`) kaskadowo usuwa jego notatki (lub anonimizuje — zależnie od polityki retencji)

#### 8.1.19 Stawki godzinowe pracownika (`employee_rates`)

Historia zmian stawki godzinowej per pracownik. Aktualna stawka = rekord z `data_do IS NULL` (lub najnowszy). Pozwala rozliczać godziny po stawce obowiązującej w dacie zlecenia.

| Kolumna | Typ | Opis |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| employee_id | INT UNSIGNED FK → employees.id | ON DELETE CASCADE |
| stawka_godzinowa | DECIMAL(10,2) | Stawka PLN/h |
| data_od | DATE | Data wejścia w życie stawki |
| data_do | DATE | NULLABLE — data zakończenia obowiązywania (NULL = aktualna) |
| created_at | DATETIME | |
| updated_at | DATETIME | |

#### 8.1.20 Urlopy pracowników (`employee_vacations`)

| Kolumna | Typ | Opis |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| employee_id | INT UNSIGNED FK → employees.id | ON DELETE CASCADE |
| data_od | DATE | Pierwszy dzień urlopu |
| data_do | DATE | Ostatni dzień urlopu |
| typ | ENUM('wypoczynkowy','na_zadanie','L4') | Typ urlopu |
| status | ENUM('oczekujacy','zatwierdzony','odrzucony','zrealizowany') | Status wniosku |
| created_at | DATETIME | |
| updated_at | DATETIME | |

#### 8.1.21 Faktury wystawione (`invoices`)

Faktury powiązane ze zleceniami, z kategoryzacją terminu wystawienia (po zleceniu / po tygodniu / koniec miesiąca) — pozwala zweryfikować, czy żadna faktura nie została pominięta.

| Kolumna | Typ | Opis |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| order_id | INT UNSIGNED FK → orders.id | NULLABLE (ON DELETE SET NULL) — powiązane zlecenie |
| numer_faktury | VARCHAR(50) UNIQUE | Numer faktury |
| klient_nazwa | VARCHAR(255) | Nazwa klienta |
| kwota_pln | DECIMAL(12,2) | Kwota brutto |
| data_wystawienia | DATE | Data wystawienia |
| termin_platnosci | DATE | NULLABLE — termin płatności |
| status | ENUM('wystawiona','zaplacona','przeterminowana') | Status płatności |
| typ_wystawienia | ENUM('po_zleceniu','po_tygodniu','koniec_miesiaca') | Termin wystawienia (wg którego cyklu) |
| created_at | DATETIME | |
| updated_at | DATETIME | |

### 8.2 Diagram relacji (uproszczony)

```
users ──┬── incident_comments
        └── incidents (zgloszona_przez)
        └── daily_*_reports (utworzony_przez)
        └── user_notes (user_id — prywatne notatki to-do)

employees ──┬── employee_documents
            └── order_employees
            └── equipment (current_employee_id)

equipment ──┬── vehicle_details (1:1 dla pojazdów)
            ├── vehicle_service_plans
            ├── equipment_history
            ├── order_equipment
            ├── incidents (equipment_id)
            └── daily_vehicle_reports

terminals ──┬── orders
            ├── employees (current_terminal_id)
            ├── equipment (current_terminal_id)
            └── daily_terminal_reports

orders ──┬── order_employees
         └── order_equipment

incidents ──┬── incident_comments
            └── incident_status_history
```

---

## 9. Bezpieczeństwo

### 9.1 Szyfrowanie i hasła

- Hasła: **Argon2id** (preferowany, `PASSWORD_ARGON2ID`) lub bcrypt (`PASSWORD_BCRYPT`) z kosztem ≥ bcrypt cost 12
- Klucze API / tokeni: `random_bytes(32)` + base64url encode
- Szyfrowanie danych wrażliwych w spoczynku (at-rest): **AES-256-GCM** (`openssl_encrypt`) z kluczem z zmiennej `APP_KEY` (32 bajty, base64) przechowywanym w `.env`
- Szyfrowanie w tranzycie: TLS 1.3 (wymuszone), TLS 1.2 jako fallback z restrykcyjnymi cipher suites (ECDHE, AEAD)
- Hashe haseł przechowywane wyłącznie jako `password_hash` — nigdy plaintext ani odwracalnie

### 9.1.1 Polityka haseł

- Minimalna długość: **12 znaków**
- Wymagane klasy znaków: małe litery, wielkie litery, cyfry, znaki specjalne (min. 3 z 4 klas)
- Blokada 10 najczęściej używanych haseł (lista `haveibeenpwned` lub statyczna lista)
- Maksymalna długość: 128 znaków (ochrona przed DoS hash)
- Hasło nie może zawierać adresu e-mail użytkownika
- Wymuszona zmiana hasła przy pierwszym logowaniu (po utworzeniu konta przez `super_admin`)
- Historia haseł: blokada ponownego użycia 5 ostatnich haseł

### 9.2 Ochrona danych osobowych (RODO/GDPR)

- **Minimalizacja**: tylko niezbędne dane pracowników przechowywane
- **Ekspozycja przez API**: odpowiednie mapowanie DTO, bez przesyłania niepotrzebnych pól (np. `password_hash` nigdy nie wychodzi z API)
- **Pseudonimizacja** tam gdzie możliwe (np. identyfikatory zamiast imion w logach)
- **Prawo do bycia zapomnianym**: endpoint `DELETE /api/v1/employees/{id}` realizuje **fizyczne usunięcie** pracownika z bazy. Powiązania historyczne (np. `order_employees`) zostają zachowane dzięki FK `ON DELETE SET NULL` (kolumna `employee_id` NULLABLE) — frontend wyświetla dla nich etykietę „Pracownik usunięty". Dokumenty pracownika (`employee_documents`) usuwają się kaskadowo (`ON DELETE CASCADE`), a bieżące przypisanie sprzętu (`equipment.current_employee_id`) zostaje wyczyszczone (`ON DELETE SET NULL`).
- **Retencja danych**: dane pracowników nieaktywnych archiwizowane po 2 latach, usuwane po 5 latach (konfigurowalne w ustawieniach)
- **Dostęp do danych osobowych**: logowany w audit log (kto, kiedy, jakie dane obejrzał)
- **Zgoda**: aplikacja przetwarza dane na podstawie zgody/wiążącego polecenia — dokumentacja prawna prowadzona osobno

### 9.3 REST API Security

- **HTTPS** (TLS 1.3 wymuszone, przekierowanie HTTP → HTTPS)
- **CORS**: whitelist dozwolonych originów z `.env` (`CORS_ALLOWED_ORIGINS`), brak wildcard `*` na produkcji
- **Rate limiting**:
  - Globalnie: 100 req/min na IP, 1000 req/min na użytkownika
  - **Logowanie** (`/auth/login`): 5 prób/min na IP + 10 prób/h na konto (ochrona przed brute-force)
  - **Set-password**: 3 próby/h na token (ochrona przed przejęciem linku)
  - Po przekroczeniu limitu logowania → blokada konta na 15 min + powiadomienie e-mail
- **Input validation i sanitization**: walidacja na poziomie kontrolera (typ, długość, format) + sanityzacja na poziomie serwisu
- **SQL Injection**: prepared statements (PDO) — **zabronione** łączenie stringów w zapytaniach SQL
- **XSS**: Content-Security-Policy headers, output encoding w szablonach Angular (`{{ }}` domyślnie escapuje), brak `innerHTML` bez sanityzacji (`DomSanitizer`)
- **CSRF**: tokeny w nagłówkach (`X-CSRF-Token`) dla mutate endpoints, walidacja `Origin` header
- **Mass assignment**: DTO z jawnym whitelistem pól — nigdy nie bindować `$_POST` bezpośrednio do modelu
- **IDOR**: autoryzacja per zasób — sprawdzanie czy użytkownik ma dostęp do konkretnego `{id}` (nie tylko do sekcji)
- **File upload** (dokumenty pracowników):
  - Walidacja typu MIME przez `finfo_file()` + whitelist rozszerzeń (`.pdf`, `.jpg`, `.png`)
  - Limit rozmiaru: 5 MB
  - Skanowanie antywirusowe (ClamAV) w tle
  - Pliki przechowywane poza document root, dostęp przez signed URL z krótkim TTL
  - Generowanie nowej nazwy pliku (UUID) — nie ufać nazwie od klienta

### 9.3.1 Nagłówki bezpieczeństwa (HTTP Security Headers)

Wszystkie odpowiedzi API muszą zawierać:

| Nagłówek | Wartość | Cel |
|---|---|---|
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains; preload` | HSTS — wymuszenie HTTPS |
| `X-Content-Type-Options` | `nosniff` | Blokada MIME sniffing |
| `X-Frame-Options` | `DENY` | Clickjacking (dla API) |
| `Content-Security-Policy` | `default-src 'none'; frame-ancestors 'none'` | CSP dla API |
| `Referrer-Policy` | `no-referrer` | Ograniczenie wycieku URL |
| `Permissions-Policy` | `geolocation=(), camera=(), microphone=()` | Blokada niepotrzebnych API |
| `Cache-Control` | `no-store` dla odpowiedzi z danymi osobowymi | Ochrona przed cache'owaniem |
| `X-Robots-Tag` | `noindex, nofollow` | Brak indeksowania API |

Frontend (Angular) dodatkowo:

| Nagłówek | Wartość | Cel |
|---|---|---|
| `Content-Security-Policy` | `default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self' <API_URL>;` | Ograniczenie źródeł zasobów |
| `X-Frame-Options` | `SAMEORIGIN` | Zezwolenie na embedding w tej samej domenie |

### 9.4 JWT Security

- **Algorytm podpisywania**: dokumentacja określa **RS256** (asymetryczne); implementacja referencyjna używa HS256. Docelowo **RS256** z dedykowaną parą kluczy RSA (2048-bit), klucz prywatny w `.env`, publiczny dystrybuowany. Jeśli HS256 używany w dev, musi być zastąpiony RS256 na produkcji.
- **Krótki czas wygaśnięcia access tokena**: 15 minut (`JWT_ACCESS_TTL=900`)
- **Refresh token**: jednorazowy (single-use) z rotacją — każde użycie generuje nowy token i unieważnia stary
- **Denylist refresh tokenów**: unieważnione tokeny przechowywane w tabeli `revoked_refresh_tokens` (z TTL równym `JWT_REFRESH_TTL`), sprawdzane przy każdym `/auth/refresh`
- **Wylogowanie**: `POST /auth/logout` dodaje refresh token do denylist + czyści cookie
- **Przechowywanie tokena**:
  - Preferowane: **HttpOnly + Secure + SameSite=Strict cookie** — ochrona przed XSS kradzieżą
  - Alternatywa (jeśli cookie niemożliwe): `localStorage` + ochrona przed XSS (CSP, sanityzacja)
  - **Zabronione**: `sessionStorage` (krótszy TTL, podatny na ataki)
- **Claims**: `sub` (user ID), `role`, `permissions`, `iat`, `exp`, `jti` (unikalne ID do denylist)
- **Egzekwowanie uprawnień**: `PermissionMiddleware` weryfikuje uprawnienia **z bazy danych** (po `user_id`), a nie z claima JWT — backend zawsze egzekwuje aktualne uprawnienia (eliminacja „stale token")
- **Odświeżanie uprawnień**: `GET /api/v1/auth/me` zwraca świeży stan (rola, uprawnienia, status) — frontend synchronizuje UI bez re-logowania
- **Rotacja kluczy**: co 90 dni, z okresem overlap 7 dni (stare i nowe klucze akceptowane)
- **Audyt**: każde wystawienie/odświeżenie/wylogowanie logowane w `audit_log`

### 9.5 Audyt i logowanie bezpieczeństwa

- **Audit log**: tabela `audit_log` rejestruje wszystkie istotne akcje:
  - Logowanie (sukces + niepowodzenie), wylogowanie
  - Zmiana uprawnień, utworzenie/usunięcie użytkownika
  - Dostęp do danych osobowych pracowników
  - Zmiana statusu awarii, usunięcie zasobu
- Struktura: `id`, `user_id`, `action`, `resource_type`, `resource_id`, `ip_address`, `user_agent`, `details` (JSON), `created_at`
- Retencja logów: 12 miesięcy
- Logi chronione przed modyfikacją (append-only, brak UPDATE/DELETE)

### 9.6 Zarządzanie sekretami

- Pliki `.env` **nigdy** commitowane do Git (weryfikacja w `.gitignore`)
- Na produkcji: sekrety z Docker Secrets, Vault lub cloud-specific secret manager (AWS Secrets Manager, GCP Secret Manager)
- Rotacja sekretów: `JWT_SECRET`, `APP_KEY` rotowane co 90 dni, procedura rotacji udokumentowana
- Brak sekretów w kodzie źródłowym, logach, komunikatach błędów
- Weryfikacja braku sekretów w repozytorium (pre-commit hook + skanowanie np. `gitleaks`)

### 9.7 Blokada konta i odzyskiwanie dostępu

- Po **5 nieudanych** próbach logowania konto blokowane na **15 minut**
- Po **20 nieudanych** próbach w 24h konto blokowane do ręcznego odblokowania przez `super_admin`
- Powiadomienie e-mail przy blokadzie konta
- Odzyskiwanie hasła: `POST /api/v1/auth/forgot-password` → e-mail z jednorazowym tokenem (TTL 1h)
- Token resetujący: `random_bytes(32)`, przechowywany hash w `password_reset_tokens`, jednorazowy


## 10. Sekcje aplikacji — szczegółowy opis

### 10.1 Dashboard

- Widok: siatka kart KPI + widgety alertów + skróty
- KPI: liczba aktywnych pracowników, obsługiwane terminale, pojazdy w użyciu, aktywne awarie
- Alerty: certyfikaty bliskie wygaśnięcia, zbliżające się przeglądy, nierozwiązane awarie
- Skróty: zgłoś awarię, utwórz raport, dodaj zlecenie
- Motyw: przełącznik pomiędzy ciemnym a jasnym motywem (dark/light theme toggle) — wybór zapamiętywany w `localStorage` i aplikowany globalnie; domyślnie nastawiony na preferencje systemu (`prefers-color-scheme`)
- Responsywność: mobile-friendly, priorytet szybkiego podglądu

### 10.2 Pracownicy

- **Koncepcja:** pracownicy są zasobem (jak terminale czy sprzęt) — **nie posiadają kont ani dostępu do aplikacji**. Pole **e-mail jest opcjonalne** (wyłącznie dane kontaktowe) i nie generuje konta użytkownika ani wysyłki linku do ustawienia hasła. Konta dostępu tworzy się wyłącznie w Ustawienia → Użytkownicy.
- Widok: tabela z filtrowaniem (imię, nazwisko, terminal, sprzęt, rola dnia, status urlopu) + wersja mobilna jako karty
- Akcje: dodaj, edytuj, usuń, szybkie przypisanie terminala/sprzętu, zmiana stawki, zarządzanie urlopami
- Podstrona edycji: dane podstawowe + zakładka "Certyfikaty i uprawnienia"
- Certyfikaty: lista dokumentów z detekcją wygaśnięcia (30 dni przed = alert)

**Stawka godzinowa (per pracownik, z możliwością zmiany):**

- Każdy pracownik posiada aktualną stawkę godzinową (PLN/h) edytowalną w formularzu pracownika oraz dedykowanym oknie „Zmień stawkę" (ikona monety).
- Zmiana stawki wymaga podania **daty wejścia w życie** — system przechowuje pełną historię zmian w tabeli `employee_rates`. Godziny przepracowane przed tą datą rozliczane są po starej stawce, po dacie — po nowej (historyczność rozliczeń).
- W tabeli i kartach wyświetlana jest aktualna stawka, suma godzin w miesiącu oraz wyliczone wynagrodzenie (godziny × stawka).

**Rola dnia (przypisanie stanowiska na dany dzień):**

- Do każdego pracownika można przypisać stanowisko pełnione danego dnia (kolumna „Rola (dziś)"):
  - `operator`
  - `brygadzista`
  - `sztauer`
  - `lukowy`
  - `operator żurawia`
- Przypisanie roli realizowane jest na poziomie zlecenia (`order_employees.rola`) — pracownik może pełnić różne role w różnych zleceniach/portach.
- W widoku listy rola „dziś" jest agregowana z bieżących/najświeższych przypisań zleceniowych.

**Rozliczenie godzin i wynagrodzeń:**

- **Per pracownik:** suma godzin przepracowanych w miesiącu (z `order_employees.godziny`) × aktualna stawka = wynagrodzenie. Wynagrodzenie uwzględnia historyczne stawki (rozliczenie po dacie wejścia w życie).
- **Per port (terminal):** osobna sekcja „Rozliczenie godzin per port" sumuje godziny i wynagrodzenia pracowników zgrupowane wg terminala (port = `orders.terminal_id`). Tabela: Port · Liczba pracowników · Suma godzin · Suma wynagrodzeń, z wierszem „Razem (wszystkie porty)".
- **Suma wszystkich przepracowanych godzin we wszystkich portach** — prezentowana w pasku podsumowania (KPI) oraz jako wiersz totalny w rozliczeniu per port.
- Pasek podsumowania (KPI): suma godzin (mc, wszystkie porty), suma wynagrodzeń (mc) z podziałem na okresy **1–15** i **15–23**, liczba pracowników na urlopie.

**Urlopy pracowników:**

- Dedykowane okno „Urlopy" (ikona zegara) per pracownik oraz globalny przycisk „Urlopy" w toolbarze.
- Rejestr urlopów: data od/do, typ (`wypoczynkowy`, `na żądanie`, `L4`), status (`oczekujacy`, `zatwierdzony`, `odrzucony`, `zrealizowany`) — tabela `employee_vacations`.
- Pracownik na urlopie jest automatycznie wykluczony z listy dostępnych do przypisania w harmonogramie/zleceniach oraz oznaczony w liście pracowników (pill „Urlop").
- Licznik pracowników na urlopie w filtrach (chip „Na urlopie") i w pasku podsumowania.

**Wynagrodzenia miesięczne (z podziałem 1–15 i 15–23):**

- Miesięczne zestawienie wynagrodzeń wszystkich pracowników z podziałem na dwa okresy rozliczeniowe: **1–15** oraz **15–23** dnia miesiąca (zgodnie z wymogami wypłat).
- Każdy okres sumuje godziny × stawka dla wszystkich pracowników; wartości wyświetlane w pasku podsumowania i w dedykowanym widoku rozliczenia per port (z przełącznikiem okresu).
- Dane bazują na datach zleceń (`orders.data_rozpoczecia`) — godziny pracownika ze zleceń przypadających w danym okresie.

**Faktury wystawione (osobna sekcja / ikona):**

- Osobna sekcja „Faktury" (ikona dokumentu/faktury) prezentująca wystawione faktury powiązane ze zleceniami (`invoices`), z odróżnieniem terminu wystawienia:
  - `po_zleceniu` — wystawiona zaraz po zakończonym zleceniu
  - `po_tygodniu` — wystawiana po zakończonym tygodniu
  - `koniec_miesiaca` — wystawiana na koniec miesiąca
- Status faktury: `wystawiona`, `zaplacona`, `przeterminowana`. Kolumna „termin wystawienia" pozwala zweryfikować, czy żadna faktura nie została pominięta (zleconia zakończone bez wystawionej faktury = oznaczone do obsługi).
- Filtrowanie po statusie, terminie wystawienia, kliencie i dacie; alerty o przeterminowanych/niewystawionych fakturach.

### 10.3 Sprzęt

- Widok: tabela z kategoriami pojazdy/inne, filtrowanie
- Pojazdy: dodatkowe kolumny (przebieg, serwis, awaria, OC)
- Akcje: dodaj, edytuj, usuń, szybkie przypisanie
- Timeline: oś czasu dla każdego sprzętu
- Planowanie przeglądów: konfiguracja interwałów, automatyczne oznaczanie wymagających serwisu

**Kody QR dla maszyn z grupy pojazdów (generator QR):**

- Dla każdego sprzętu z kategorii `pojazd` (oraz opcjonalnie `inne`) dostępny jest **generator kodu QR** prowadzącego do publicznej podstrony zgłaszania awarii danej maszyny albo raportowania jej obsługi codziennej.
- Kod QR koduje publiczny URL z unikalnym tokenem maszyny (np. `https://app.pbs.example.com/qr/{qr_token}`), gdzie `qr_token` to kolumna `equipment.qr_token` (CHAR(64), `UNIQUE`, generowany losowo — nie jest to `id`, aby nie ujawniać identyfikatorów wewnętrznych).
- Podstrona `/qr/{token}` jest **publiczna** (bez logowania) i pozwala wykonać jedną z dwóch akcji dla danej maszyny:
  1. **Zgłoszenie awarii** — tworzy rekord `incidents` (`typ='sprzet'`, `equipment_id` z tokena) z opisem podanym przez zgłaszającego.
  2. **Raport obsługi codziennej (OC)** — tworzy rekord `daily_vehicle_reports` (przebieg, opis obsługi, uwagi) powiązany z maszyną.
- Generator udostępnia **wersję do wydruku** (kod QR + nazwa/numer maszyny + krótka instrukcja) — naklejana w maszynie, aby operator mógł zeskanować telefonem i zgłosić awarię lub raport bez logowania do aplikacji.
- Publiczny endpoint zgłoszeniowy jest objęty **rate limitingiem** (ochrona przed spamem) oraz walidacją tokena (404 dla nieistniejącego/wygasłego). Zgłoszenia z QR tworzą `incidents`/`daily_vehicle_reports` z oznaczeniem źródła `qr` i `zgloszona_przez = NULL` (anonimowe) — do weryfikacji w panelu awarii/raportów.

### 10.4 Terminale

- Widok: lista terminali (adres, operator, dane kontaktowe)
- Akcje: dodaj, edytuj, usuń

### 10.5 Harmonogram (zintegrowany ze Zleceniami)

- Widok: **siatka tygodniowa** (kolumna „Godz." + 7 dni Pon–Nd, rzędy zmian **06–14 / 14–22 / 22–06**)
  z przełączaniem na dzień i miesiąc (generyczny kalendarz)
- Każde zlecenie: numer, klient, terminal, datetime, zakres prac, wartość, status, przypisani pracownicy/sprzęt
- **Pasek nawigacji widoku**: przyciski poprzedni/następny okres + etykieta „Tydzień N · DD–DD miesiąca rok"
  (numer tygodnia ISO) + przycisk „Dziś" + przełącznik widoku (Dzień/Tydzień/Miesiąc)
- Karty zleceń w siatce kolorowane wg statusu (nowe=cyan, w realizacji=amber, zakończone=emerald),
  z podtytułem „terminal · zakres prac" i etykietą statusu dla zleceń w realizacji; bieżący dzień
  podświetlony („dziś"). Zlecenie trafia do komórki (dzień × zmiana) na podstawie godziny rozpoczęcia.
- Akcje: utwórz, edytuj, usuń zlecenie, kopiuj tydzień jako szablon
- **Panel detalu zlecenia** (widok główny, po kliknięciu zlecenia w kalendarzu): karty podsumowujące
  (klient, realizacja, wartość, status), pille przypisanych pracowników (z rolą) i sprzętu,
  oraz tabela **„Rozliczenie godzin i wynagrodzeń”** (pracownik · rola · godz. · stawka/h · wynagrodzenie)
  z wierszem sumy. Stawka godzinowa pobierana jest z `employee_rates` obowiązującego w dacie
  rozpoczęcia zlecenia; wynagrodzenie = godziny × stawka.
- **Box „Przekazanie zmiany”** w detalu: gdy zlecenie obejmuje więcej niż jedną zmianę (wg godzin
  start/end), wyświetlane jest przejście między sąsiednimi zmianami (np. „06–14 → 14–22").
- **Panel „Dostępni pracownicy”** (widok główny, obok detalu zlecenia): lista pracowników
  z wyszukiwarką (autocomplete), stawką godzinową i rolą „dziś"; przycisk **„Przypisz”**
  przypisuje pracownika jednym kliknięciem do wybranego zlecenia (rola dzisiejsza jako domyślna).
  Pracownicy na urlopie są widoczni, ale wyłączeni (pill „urlop”); już przypisani oznaczeni
  pill „przypisany”.
- **Przypisywanie podczas tworzenia zlecenia**: w formularzu „Nowe zlecenie” dostępne są pille
  przypisanych pracowników (z rolą) i sprzętu; wybór przez autocomplete, usunięcie przez ✕.
  Przypisania są aplikowane po pomyślnym utworzeniu zlecenia (POST /orders) sekwencją wywołań
  `assign-employee` / `assign-equipment`.
- **Modal przypisania pracownika** zawiera pole **„Liczba godzin”** (zapisywane w `order_employees.godziny`).
- Integracja z raportowaniem: dane pobierane z harmonogramu

### 10.6 Analityka

- Wykresy: integracja danych z Excela + cross-sekcje (terminale, pracownicy, sprzęt)
- Filtry czasowe: zakres dat (domyślnie 30 dni)
- Statystyki: najczęściej obsługiwane terminale, pracownicy, sprzęt + relacje

### 10.7 Raportowanie

- Raporty terminalowe: automatyczne dołączanie danych z harmonogramu (pracownicy, sprzęt)
- Raporty pojazdowe: wybór pojazdu, aktualny przebieg, przebieg OC
- Uprawnienia: osoby zarządzające

### 10.8 Ustawienia

- Zarządzanie użytkownikami: tworzenie **wyłącznie kont Administratora** (email z linkiem do ustawienia hasła), edycja uprawnień, blokowanie. Super Administrator jest seedowany i nie jest tworzony z tego poziomu
- Zarządzanie alertami: konfiguracja e-maili odbiorców, typów alertów, harmonogramów wysyłki
- Dostęp: tylko `super_admin`
- Pracownicy nie występują tutaj — nie posiadają kont; ich dane (w tym opcjonalny e-mail) zarządzane są w sekcji Pracownicy

### 10.9 Awaria!

- Zgłaszanie awarii: uproszczony formularz (czego dotyczy → sprzęt/inne → wybór sprzętu → opis)
- Lifecycle: zgłoszona → w trakcie naprawy → naprawiona / zamknięta
- Widok szczegółowy: zmiana statusu (+ data zakończenia), komentarze, historia statusów
- Czas przestoju: dostępny w analityce

### 10.10 Szybkie notatki to-do (widget globalny)

- Widget w formie wysuwającego się z boku ekranu panelu (przycisk-uchwyt zawsze widoczny na krawędzi), dostępny z poziomu **każdej podstrony** aplikacji oraz w wersji mobilnej (od ≥ 320px)
- Notatki to-do są **prywatne** — przypisane do zalogowanego konta użytkownika (`user_id` z JWT)
- Funkcje widgetu:
  - Dodawanie nowej notatki (krótki tekst, max 500 znaków)
  - Odznaczanie notatki jako wykonana / cofnięcie oznaczenia (`is_done`)
  - Usuwanie pojedynczej notatki z listy
  - Czyszczenie całej listy notatek (z `ConfirmDialogComponent`)
  - Opcjonalnie: licznik nieodznaczonych notatek na przycisku-uchwycie
- Dostępność: widget widoczny dla wszystkich zalogowanych użytkowników (nie wymaga osobnego uprawnienia per sekcja)
- Tryb offline-first: notatki kolejkowane przez background sync i synchronizowane po odzyskaniu połączenia; lokalny store w `IndexedDB`
- Stylowanie: Tailwind CSS, wsuwany panel (`translate-x`), dostępność klawiatury (Esc zamyka, focus trap wewnątrz panelu)

---

## 11. API Endpoints

### 11.1 Autentykacja

| Metoda | Endpoint | Opis |
|---|---|---|
| POST | `/api/v1/auth/login` | Logowanie (zwraca JWT) |
| POST | `/api/v1/auth/refresh` | Odświeżenie tokena |
| POST | `/api/v1/auth/logout` | Wylogowanie |
| POST | `/api/v1/auth/set-password` | Ustawienie hasła (link z e-maila) |
| GET | `/api/v1/auth/me` | Aktualny użytkownik (rola, uprawnienia) — świeże dane z bazy |

### 11.2 Użytkownicy

| Metoda | Endpoint | Opis |
|---|---|---|
| GET | `/api/v1/users` | Lista użytkowników |
| POST | `/api/v1/users` | Utworzenie nowego użytkownika (email → link) |
| GET | `/api/v1/users/{id}` | Szczegóły użytkownika |
| PUT | `/api/v1/users/{id}` | Edycja użytkownika |
| PATCH | `/api/v1/users/{id}/permissions` | Aktualizacja uprawnień |
| DELETE | `/api/v1/users/{id}` | Usunięcie użytkownika |

### 11.3 Pracownicy

> Pracownicy są zasobem — nie posiadają kont ani dostępu do aplikacji. Pole `email` jest **opcjonalne** i służy wyłącznie jako dane kontaktowe (nie tworzy konta ani nie wysyła linku do hasła).

| Metoda | Endpoint | Opis |
|---|---|---|
| GET | `/api/v1/employees` | Lista pracowników (z filtrami: imię, nazwisko, terminal, sprzęt, rola, status urlopu) |
| POST | `/api/v1/employees` | Dodanie pracownika |
| GET | `/api/v1/employees/{id}` | Szczegóły pracownika (z dokumentami, aktualną stawką, urlopami) |
| PUT | `/api/v1/employees/{id}` | Edycja pracownika |
| DELETE | `/api/v1/employees/{id}` | Usunięcie pracownika |
| PATCH | `/api/v1/employees/{id}/assignment` | Szybkie przypisanie terminala/sprzętu |
| GET | `/api/v1/employees/{id}/rates` | Historia stawek godzinowych (chronologicznie) |
| POST | `/api/v1/employees/{id}/rates` | Nowa stawka godzinowa (z `data_od` — wejście w życie, zamyka poprzednią) |
| GET | `/api/v1/employees/{id}/vacations` | Lista urlopów pracownika |
| POST | `/api/v1/employees/{id}/vacations` | Dodanie urlopu (od/do, typ, status) |
| PATCH | `/api/v1/vacations/{id}/status` | Zmiana statusu urlopu (zatwierdź/odrzuć) |
| DELETE | `/api/v1/vacations/{id}` | Usunięcie urlopu |

### 11.4 Dokumenty pracownika

| Metoda | Endpoint | Opis |
|---|---|---|
| GET | `/api/v1/employees/{id}/documents` | Lista dokumentów pracownika |
| POST | `/api/v1/employees/{id}/documents` | Dodanie dokumentu |
| PUT | `/api/v1/documents/{id}` | Edycja dokumentu |
| DELETE | `/api/v1/documents/{id}` | Usunięcie dokumentu |

### 11.5 Sprzęt

| Metoda | Endpoint | Opis |
|---|---|---|
| GET | `/api/v1/equipment` | Lista sprzętu (z filtrami) — dane referencyjne, dostępne dla każdego zalogowanego |
| POST | `/api/v1/equipment` | Dodanie sprzętu |
| GET | `/api/v1/equipment/{id}` | Szczegóły sprzętu (z historią) |
| PUT | `/api/v1/equipment/{id}` | Edycja sprzętu |
| DELETE | `/api/v1/equipment/{id}` | Usunięcie sprzętu |
| PATCH | `/api/v1/equipment/{id}/assignment` | Szybkie przypisanie |
| GET | `/api/v1/equipment/{id}/timeline` | Oś czasu sprzętu |
| POST | `/api/v1/equipment/{id}/qr-token` | (Re)generacja publicznego tokena QR maszyny |
| GET | `/api/v1/equipment/{id}/qr` | Kod QR maszyny (PNG/SVG + URL do wydruku naklejki) |

### 11.6 Pojazdy — przeglądy

| Metoda | Endpoint | Opis |
|---|---|---|
| GET | `/api/v1/equipment/{id}/service-plans` | Lista planów przeglądów |
| POST | `/api/v1/equipment/{id}/service-plans` | Dodanie planu przeglądu |
| PUT | `/api/v1/service-plans/{id}` | Edycja planu |
| DELETE | `/api/v1/service-plans/{id}` | Usunięcie planu |

### 11.7 Terminale

| Metoda | Endpoint | Opis |
|---|---|---|
| GET | `/api/v1/terminals` | Lista terminali — dane referencyjne, dostępne dla każdego zalogowanego |
| POST | `/api/v1/terminals` | Dodanie terminala |
| GET | `/api/v1/terminals/{id}` | Szczegóły terminala |
| PUT | `/api/v1/terminals/{id}` | Edycja terminala |
| DELETE | `/api/v1/terminals/{id}` | Usunięcie terminala |

### 11.8 Zlecenia / Harmonogram

| Metoda | Endpoint | Opis |
|---|---|---|
| GET | `/api/v1/orders` | Lista zleceń (z filtrami dat, statusu) |
| POST | `/api/v1/orders` | Dodanie zlecenia |
| GET | `/api/v1/orders/{id}` | Szczegóły zlecenia (z przypisaniami) |
| PUT | `/api/v1/orders/{id}` | Edycja zlecenia |
| DELETE | `/api/v1/orders/{id}` | Usunięcie zlecenia |
| POST | `/api/v1/orders/{id}/copy-week` | Kopiowanie tygodnia jako szablon |
| POST | `/api/v1/orders/{id}/assign-employee` | Przypisanie pracownika (payload: `employee_id`, opcjonalnie `rola`, `godziny`) |
| DELETE | `/api/v1/orders/{id}/assign-employee/{empId}` | Usunięcie przypisania pracownika |
| POST | `/api/v1/orders/{id}/assign-equipment` | Przypisanie sprzętu |
| DELETE | `/api/v1/orders/{id}/assign-equipment/{eqId}` | Usunięcie przypisania sprzętu |

**DTO `GET /api/v1/orders/{id}` — pole `employees[]`** zwraca dla każdego przypisanego pracownika:
`id`, `order_id`, `employee_id`, `employee_name`, `employee_email`, `rola`, `godziny`,
`stawka_godzinowa` (z `employee_rates` obowiązującej w dacie rozpoczęcia zlecenia) oraz
`wynagrodzenie` (= `godziny × stawka_godzinowa`, `0` gdy brak danych). Pozwala to frontendowi
zbudować tabelę „Rozliczenie godzin i wynagrodzeń” bez dodatkowych zapytań (anti-N+1).

### 11.9 Raporty

| Metoda | Endpoint | Opis |
|---|---|---|
| GET | `/api/v1/reports/terminal` | Lista raportów terminalowych |
| POST | `/api/v1/reports/terminal` | Utworzenie raportu terminalowego |
| GET | `/api/v1/reports/terminal/{id}` | Szczegóły raportu terminalowego |
| PUT | `/api/v1/reports/terminal/{id}` | Edycja raportu |
| GET | `/api/v1/reports/vehicle` | Lista raportów pojazdów |
| POST | `/api/v1/reports/vehicle` | Utworzenie raportu pojazdu |
| GET | `/api/v1/reports/vehicle/{id}` | Szczegóły raportu pojazdu |
| PUT | `/api/v1/reports/vehicle/{id}` | Edycja raportu |

### 11.10 Awarie

| Metoda | Endpoint | Opis |
|---|---|---|
| GET | `/api/v1/incidents` | Lista awarii (z filtrami) |
| POST | `/api/v1/incidents` | Zgłoszenie awarii |
| GET | `/api/v1/incidents/{id}` | Szczegóły awarii (z komentarzami i historią) |
| PATCH | `/api/v1/incidents/{id}/status` | Zmiana statusu (+ data zakończenia) |
| POST | `/api/v1/incidents/{id}/comments` | Dodanie komentarza |

### 11.11 Analityka

| Metoda | Endpoint | Opis |
|---|---|---|
| GET | `/api/v1/analytics/overview` | Główne statystyki (zakres dat) |
| GET | `/api/v1/analytics/terminals` | Statystyki terminali |
| GET | `/api/v1/analytics/employees` | Statystyki pracowników |
| GET | `/api/v1/analytics/equipment` | Statystyki sprzętu |
| GET | `/api/v1/analytics/relations` | Relacje między zasobami |

### 11.12 Ustawienia

| Metoda | Endpoint | Opis |
|---|---|---|
| GET | `/api/v1/settings/alert-configs` | Lista konfiguracji alertów |
| POST | `/api/v1/settings/alert-configs` | Dodanie konfiguracji alertu |
| PUT | `/api/v1/settings/alert-configs/{id}` | Edycja konfiguracji |
| DELETE | `/api/v1/settings/alert-configs/{id}` | Usunięcie konfiguracji |

### 11.13 Dashboard

| Metoda | Endpoint | Opis |
|---|---|---|
| GET | `/api/v1/dashboard/summary` | Podsumowanie KPI |
| GET | `/api/v1/dashboard/alerts` | Lista alertów |

### 11.14 Szybkie notatki to-do

Endpointy prywatne — każda operacja ograniczona do notatek właściciela (`user_id` z JWT). IDOR protection obowiązkowe.

| Metoda | Endpoint | Opis |
|---|---|---|
| GET | `/api/v1/notes` | Lista notatek zalogowanego użytkownika (z filtrem `?is_done=0/1`, sortowaniem) |
| POST | `/api/v1/notes` | Utworzenie notatki (walidacja `tresc`, max 500 znaków) |
| PATCH | `/api/v1/notes/{id}` | Aktualizacja treści notatki |
| PATCH | `/api/v1/notes/{id}/done` | Odznaczenie / cofnięcie oznaczenia jako wykonana (`is_done`) |
| DELETE | `/api/v1/notes/{id}` | Usunięcie pojedynczej notatki |
| DELETE | `/api/v1/notes` | Wyczyszczenie całej listy notatek (z filtrem `?is_done=1` — tylko wykonane, lub bez filtra — wszystkie) |

### 11.15 Rozliczenia pracowników (godziny i wynagrodzenia)

Zestawienia rozliczeniowe bazujące na `order_employees.godziny` × stawka z `employee_rates` (po dacie zlecenia). Parametr `?period=` przyjmuje `all`, `1-15`, `15-23`.

| Metoda | Endpoint | Opis |
|---|---|---|
| GET | `/api/v1/employees/settlement?month=YYYY-MM&period=` | Rozliczenie per pracownik (godziny, stawka, wynagrodzenie) za miesiąc/okres |
| GET | `/api/v1/employees/settlement/by-port?month=YYYY-MM&period=` | Rozliczenie per port (terminal): pracownicy, suma godzin, suma wynagrodzeń |
| GET | `/api/v1/employees/summary?month=YYYY-MM` | Pasek podsumowania: suma godzin (wszystkie porty), suma wynagrodzeń z podziałem 1–15 / 15–23, liczba na urlopie |

### 11.16 Faktury wystawione

Faktury powiązane ze zleceniami (`invoices`), z kategoryzacją terminu wystawienia (`po_zleceniu`, `po_tygodniu`, `koniec_miesiaca`).

| Metoda | Endpoint | Opis |
|---|---|---|
| GET | `/api/v1/invoices` | Lista faktur (filtry: status, typ_wystawienia, klient, data) |
| POST | `/api/v1/invoices` | Wystawienie faktury (powiązanie z `order_id` opcjonalne) |
| GET | `/api/v1/invoices/{id}` | Szczegóły faktury |
| PUT | `/api/v1/invoices/{id}` | Edycja faktury |
| DELETE | `/api/v1/invoices/{id}` | Usunięcie faktury |
| PATCH | `/api/v1/invoices/{id}/status` | Zmiana statusu (`zaplacona`/`przeterminowana`) |
| GET | `/api/v1/invoices/missing` | Zlecenia zakończone bez wystawionej faktury (kontrola pominięć) |

### 11.17 Publiczne kody QR (maszyny)

Endpointy **publiczne** (bez `AuthMiddleware`) — obsługa zgłoszeń z naklejki QR naklejonej w maszynie. Objęte osobnym rate limitingiem (ochrona przed spamem) oraz walidacją `qr_token` (404 dla nieistniejącego).

| Metoda | Endpoint | Opis |
|---|---|---|
| GET | `/api/v1/qr/{token}` | Publiczne info o maszynie (nazwa, numer, kategoria) — bez danych osobowych |
| POST | `/api/v1/qr/{token}/incident` | Zgłoszenie awarii maszyny z QR (`incidents`, źródło `qr`, anonimowe) |
| POST | `/api/v1/qr/{token}/daily-report` | Raport obsługi codziennej (OC) z QR (`daily_vehicle_reports`, źródło `qr`) |

---

## 12. Modele danych

### 12.1 Frontend (TypeScript interfaces)

Przykładowe interfejsy TypeScript dla kluczowych encji:

```typescript
export interface User {
  id: number;
  email: string;
  role: 'super_admin' | 'admin';
  permissions: Record<string, boolean>;
  is_active: boolean;
}

export interface Employee {
  id: number;
  imie: string;
  nazwisko: string;
  telefon: string;
  email: string;
  current_terminal?: Terminal;
  current_sprzet?: Equipment;
  is_active: boolean;
  documents?: EmployeeDocument[];
  current_rate?: number;        // aktualna stawka godzinowa (PLN/h) z employee_rates
  hours_month?: number;         // suma godzin w bieżącym miesiącu (wszystkie porty)
  wage_month?: number;          // wynagrodzenie w bieżącym miesiącu (godziny × stawka)
  today_role?: EmployeeRole;    // rola dnia (agregowana z bieżących zleceń)
  on_vacation?: boolean;        // czy pracownik jest obecnie na urlopie
  rates?: EmployeeRate[];
  vacations?: EmployeeVacation[];
}

export type EmployeeRole = 'operator' | 'brygadzista' | 'sztauer' | 'lukowy' | 'operator_zurawia';

export interface EmployeeRate {
  id: number;
  employee_id: number;
  stawka_godzinowa: number;
  data_od: string;
  data_do?: string;   // NULL = aktualna stawka
}

export interface EmployeeVacation {
  id: number;
  employee_id: number;
  data_od: string;
  data_do: string;
  typ: 'wypoczynkowy' | 'na_zadanie' | 'L4';
  status: 'oczekujacy' | 'zatwierdzony' | 'odrzucony' | 'zrealizowany';
}

export interface EmployeeDocument {
  id: number;
  employee_id: number;
  nazwa: string;
  numer_dokumentu: string;
  data_wydania: string;
  data_waznosci: string;
  is_expiring_soon?: boolean;
  is_expired?: boolean;
}

export interface Equipment {
  id: number;
  kategoria: 'pojazd' | 'inne';
  nazwa: string;
  numer_seryjny?: string;
  qr_token?: string;            // publiczny token dla kodu QR (podstrona /qr/{token})
  current_employee?: Employee;
  current_terminal?: Terminal;
  vehicle_details?: VehicleDetails;
  is_active: boolean;
}

export interface VehicleDetails {
  equipment_id: number;
  ostatni_przebieg: number;
  ostatni_serwis_olejowy?: string;
  ostatnia_awaria?: string;
  data_ostatniej_oc?: string;
  wynik_ostatniej_oc?: string;
}

export interface Terminal {
  id: number;
  nazwa: string;
  adres: string;
  operator: string;
  telefon_operatora: string;
  email_operatora: string;
  is_active: boolean;
}

export interface Order {
  id: number;
  numer_zlecenia: string;
  klient_nazwa: string;
  terminal: Terminal;
  data_rozpoczecia: string;
  data_zakonczenia: string;
  zakres_prac: string;
  wartosc_pln: number;
  status: 'nowe' | 'w_realizacji' | 'zakonczone';
  employees?: Employee[];
  order_employees?: OrderEmployee[];
  equipment?: Equipment[];
  invoice?: Invoice;            // wystawiona faktura (jeśli istnieje)
}

export interface OrderEmployee {
  id: number;
  order_id: number;
  employee_id: number;
  employee?: Employee;
  rola?: EmployeeRole;          // stanowisko pełnione w tym zleceniu (rola dnia)
  godziny?: number;             // przepracowane godziny (do rozliczenia × stawka)
}

export interface Invoice {
  id: number;
  order_id?: number;
  numer_faktury: string;
  klient_nazwa: string;
  kwota_pln: number;
  data_wystawienia: string;
  termin_platnosci?: string;
  status: 'wystawiona' | 'zaplacona' | 'przeterminowana';
  typ_wystawienia: 'po_zleceniu' | 'po_tygodniu' | 'koniec_miesiaca';
}

export interface Incident {
  id: number;
  typ: 'sprzet' | 'inne';
  equipment?: Equipment;
  opis: string;
  status: 'zgloszona' | 'w_trakcie_naprawy' | 'naprawiona' | 'zamknieta';
  data_zgloszenia: string;
  data_zakonczenia?: string;
  downtime_hours?: number;
  comments?: IncidentComment[];
  status_history?: IncidentStatusHistory[];
}

export interface DailyTerminalReport {
  id: number;
  terminal: Terminal;
  data_raportu: string;
  opis: string;
  uwagi?: string;
  employees?: Employee[];
  equipment?: Equipment[];
  orders?: Order[];
}

export interface DailyVehicleReport {
  id: number;
  equipment: Equipment;
  data_raportu: string;
  aktualny_przebieg: number;
  przebieg_oc: string;
  uwagi?: string;
}

export interface UserNote {
  id: number;
  user_id: number;
  tresc: string;
  is_done: boolean;
  kolejnosc?: number;
  created_at: string;
  updated_at: string;
}

// Rozliczenia pracowników (godziny i wynagrodzenia)
export interface SettlementRow {
  employee: Employee;
  hours: number;                // suma godzin w okresie
  rate: number;                 // stawka zastosowana (z uwzględnieniem historii)
  wage: number;                 // hours × rate (z podziałem wg dat stawek)
  port_breakdown?: PortSettlement[];  // opcjonalnie: rozbitie per port dla pracownika
}

export interface PortSettlement {
  terminal: Terminal;
  employees_count: number;
  hours: number;                // suma godzin w porcie
  wage: number;                 // suma wynagrodzeń w porcie
}

export interface EmployeeSummary {
  month: string;                // YYYY-MM
  total_hours: number;          // wszystkie porty
  total_wage: number;
  wage_period_1_15: number;     // wynagrodzenia za okres 1–15
  wage_period_15_23: number;    // wynagrodzenia za okres 15–23
  on_vacation_count: number;
}
```

### 12.2 Backend (DTO / Value Objects)

```php
<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class EmployeeDTO
{
    public function __construct(
        public int $id,
        public string $imie,
        public string $nazwisko,
        public string $telefon,
        public string $email,
        public ?TerminalDTO $currentTerminal,
        public ?EquipmentDTO $currentSprzet,
        public bool $isActive,
        /** @var EmployeeDocumentDTO[] */
        public array $documents = [],
        public ?float $currentRate = null,        // aktualna stawka godzinowa (PLN/h)
        public ?float $hoursMonth = null,         // suma godzin w bieżącym miesiącu
        public ?float $wageMonth = null,          // wynagrodzenie w bieżącym miesiącu
        public ?string $todayRole = null,         // rola dnia: operator|brygadzista|sztauer|lukowy|operator_zurawia
        public bool $onVacation = false,
        /** @var EmployeeRateDTO[] */
        public array $rates = [],
        /** @var EmployeeVacationDTO[] */
        public array $vacations = [],
    ) {}
}
```

---

## 13. Alerty i powiadomienia

### 13.1 Typy alertów

| Typ alertu | Warunek | Kanał |
|---|---|---|
| Certyfikat wygasa | data_waznosci ≤ 30 dni | E-mail |
| Przegląd pojazdu wymagany | data_nastepnego_planowanego ≤ X dni | E-mail |
| Brak raportu OC | brak raportu do określonej godziny | E-mail |
| Awaria zgłoszona | nowy incident | E-mail |

### 13.2 Konfiguracja alertów (sekcja Ustawienia)

- Lista odbiorców e-mail
- Włączanie/wyłączanie typów alertów
- Określanie czasu wysyłki (np. dla raportu OC: jeśli do 10:00 nie ma raportu → alert)

### 13.3 Mechanizm wysyłki

- Cron job (np. co godzinę) lub serwis排队 (queue)
- Sprawdzanie warunków → generowanie powiadomień → wysyłka e-mail (SMTP)

---

## 14. Wymagania niefunkcjonalne

| Wymaganie | Opis |
|---|---|
| Wydajność | Czas odpowiedzi API < 500ms dla 95% zapytań, < 200ms dla 99% zapytań odczytu z cache |
| Responsywność | Mobile first, wszystkie widoki działają na ekranach ≥ 320px |
| Dostępność | Aplikacja dostępna 24/7 (SLA 99.5%) |
| Bezpieczeństwo | OWASP Top 10, HTTPS, Content Security Policy |
| Skalowalność | Architektura pozwalająca na horyzontalne skalowanie backendu |
| Testowalność | Frontend: Jasmine/Karma; Backend: PHPUnit/Pest |
| Monitorowanie | Logowanie błędów, metryki API |

### 14.1 Indeksowanie bazy danych

Każda tabela musi posiadać indeksy na kolumnach używanych w klauzulach `WHERE`, `JOIN`, `ORDER BY` i `GROUP BY`. Poniżej minimalny zestaw:

| Tabela | Indeksy |
|---|---|
| `users` | `UNIQUE(email)`, `INDEX(is_active)` |
| `employees` | `INDEX(is_active)`, `INDEX(current_terminal_id)`, `INDEX(current_sprzet_id)`, `INDEX(nazwisko, imie)` |
| `equipment` | `INDEX(is_active)`, `INDEX(kategoria)`, `INDEX(current_employee_id)`, `INDEX(current_terminal_id)`, `UNIQUE(qr_token)` |
| `orders` | `INDEX(terminal_id)`, `INDEX(status)`, `INDEX(data_rozpoczecia)`, `INDEX(data_zakonczenia)`, `INDEX(numer_zlecenia UNIQUE)` |
| `incidents` | `INDEX(status)`, `INDEX(equipment_id)`, `INDEX(data_zgloszenia)`, `INDEX(zgloszona_przez)` |
| `equipment_history` | `INDEX(equipment_id, data)` |
| `daily_terminal_reports` | `INDEX(terminal_id, data_raportu)` |
| `daily_vehicle_reports` | `INDEX(equipment_id, data_raportu)` |
| `audit_log` | `INDEX(user_id, created_at)`, `INDEX(action)`, `INDEX(resource_type, resource_id)` |
| `order_employees` | `INDEX(order_id)`, `INDEX(employee_id)`, `INDEX(rola)`, `INDEX(employee_id, rola)` |
| `order_equipment` | `INDEX(order_id)`, `INDEX(equipment_id)` |
| `user_notes` | `INDEX(user_id)`, `INDEX(user_id, is_done)`, `INDEX(user_id, kolejnosc)` |
| `employee_rates` | `INDEX(employee_id)`, `INDEX(employee_id, data_od)`, `INDEX(data_do)` |
| `employee_vacations` | `INDEX(employee_id)`, `INDEX(employee_id, status)`, `INDEX(data_od, data_do)` |
| `invoices` | `UNIQUE(numer_faktury)`, `INDEX(order_id)`, `INDEX(status)`, `INDEX(typ_wystawienia)`, `INDEX(data_wystawienia)`, `INDEX(klient_nazwa)` |

Zasady:
- Klucze obce **muszą** mieć indeks (`FOREIGN KEY` automatycznie tworzy w MySQL)
- Złożone indeksy dla częstych kombinacji filtrów (np. `(status, data_rozpoczecia)` w `orders`)
- Indeksy dodawane w migracjach — nie ręcznie w bazie
- Monitorowanie wolnych zapytań (`slow_query_log` z `long_query_time = 0.1`)

### 14.2 Strategia cache

| Warstwa | Technologia | TTL | Cel |
|---|---|---|---|
| HTTP cache (browser) | `Cache-Control`, `ETag` | 5 min dla GET, `no-store` dla mutacji | Redukcja żądań |
| CDN / reverse proxy | Nginx FastCGI cache / Redis | 1–60 min | Cache odpowiedzi API |
| Backend cache | Redis lub APCu | 60 s–5 min | Cache zapytań DB, KPI dashboard |
| Frontend cache | Angular `HttpInterceptor` + Signals | 60 s | Redukcja zapytań do API |

Zasady:
- **Cache invalidation**: tag-based cache (np. `employees:all`, `employee:{id}`) — zapis mutuje odpowiedni tag
- **Stale-while-revalidate**: dla danych rzadko się zmieniających (np. lista terminali)
- Dashboard KPI: cache 60 s (dane mogą być nieświeże o minutę — akceptowalne)
- **Nigdy** nie cache'ować danych osobowych bez `Cache-Control: private`
- Backend cache konfigurowalny przez `.env` (`CACHE_DRIVER=redis|apcu|file`)

### 14.3 Kompresja i optymalizacja transferu

- **Backend**: gzip/brotli compression dla odpowiedzi > 1 KB (pozwala zaoszczędzić do 70% transferu)
- **Frontend**: build z `outputHashing: all` (obecnie w konfiguracji), lazy loading wszystkich route'ów (`loadComponent`)
- **Obrazy**: WebP/AVIF dla zdjęć, SVG dla ikon, `srcset` dla responsywnych rozmiarów
- **JSON**: minimalizacja — brak zbędnych pól, użycie `sparse fieldsets` (`?fields=id,nazwa,status`)
- **Paginacja**: domyślnie 25 rekordów na stronę, max 100

### 14.4 Optymalizacja zapytań DB (anti-N+1)

- **Eager loading**: relacje ładowane w jednym zapytaniu z `JOIN` — nie jedno na rekord
  - Przykład: `GET /api/v1/orders` → 1 zapytanie + JOIN na `order_employees`, `employees`, `order_equipment`, `equipment`
- **Lazy loading** na frontend (rozwijanie wiersza), ale na backendzie zawsze eager
- **Selektywność**: `SELECT` tylko potrzebnych kolumn, nie `SELECT *`
- **Bounded queries**: każdy `GET /list` **musi** mieć paginację — zabronione zwracanie całej tabeli
- **Transakcje**: krótkie, bez wywołań HTTP wewnątrz, izolacja `READ COMMITTED`
- **Connection pooling**: PDO persistent connections (`PDO::ATTR_PERSISTENT`) opcjonalnie, timeout 5 s

### 14.5 Timeout'y i resilience

| Zasób | Timeout | Retry |
|---|---|---|
| HTTP żądanie frontend → API | 10 s | 1 retry z exponential backoff |
| Backend → MySQL | 5 s (`PDO::ATTR_TIMEOUT`) | brak — błąd natychmiast |
| Backend → Redis | 1 s | fallback na brak cache |
| Backend → SMTP | 10 s | 3 retry w queue |
| Cron job (alerty) | 60 s | brak |

Zasady:
- Frontend: `AbortController` / `HttpClient` timeout dla każdego żądania
- Backend: circuit breaker dla zależności zewnętrznych (SMTP), graceful degradation
- Błędy 5xx: nie ujawniać stack trace w produkcji (`display_errors=Off`), logować do pliku

### 14.6 PWA i offline-first

Aplikacja deklaruje "offline-first" w założeniach — musi być zrealizowana przez:

- **Service Worker**: `@angular/service-worker` dodany do zależności i skonfigurowany w `angular.json` (`"ngswConfigPath": "ngsw-config.json"`)
- **Web App Manifest**: `manifest.webmanifest` w `assets/`, zdefiniowane ikony, `display: standalone`, `theme_color`, `background_color`
- **Strategia cache offline**:
  - **App shell**: precache HTML, CSS, JS (stale-while-revalidate)
  - **API GET**: cache-first z network timeout (jeśli offline → zwróć z cache)
  - **API POST/PUT/DELETE**: background sync queue — żądania kolejkowane i wysyłane po odzyskaniu połączenia
  - **Dane krytyczne** (awaria): `IndexedDB` jako lokalny store, synchronizacja gdy online
- **UX offline**: wskaźnik statusu połączenia (online/offline banner), feedback dla akcji w kolejce
- **Minimalizacja transferu**: delta updates gdzie możliwe, `If-None-Match` / `ETag`

### 14.7 Optymalizacja bundle frontendu

- **Lazy loading**: każdy route w `app.routes.ts` używa `loadComponent` (brak eager importów stron)
  ```typescript
  { path: 'pracownicy', loadComponent: () => import('./pages/pracownicy/list').then(m => m.PracownicyListComponent) }
  ```
- **Bundle budgets** (w `angular.json`):
  - `initial`: warning 500 kB, error 1 MB (obecnie) — docelowo warning 300 kB, error 700 kB
  - `anyComponentStyle`: warning 4 kB, error 8 kB
- **Tree shaking**: brak importów całych bibliotek (np. `import _ from 'lodash'` → `import debounce from 'lodash/debounce'`)
- **Prefetching**: strategia `prefetch` dla prawdopodobnych nawigacji (Angular `preloadStrategy`)
- **Web Vitals targets**: LCP < 2.5 s, FID < 100 ms, CLS < 0.1

### 14.8 Monitorowanie wydajności

- **Backend**: metryki Prometheus lub strukturalne logi JSON (monolog) — czas żądania, status, endpoint, user_id
- **APM**: opcjonalnie Sentry / New Relic dla śledzenia transakcji i błędów
- **Frontend**: Angular error handler → Sentry / logi; web vitals reporting
- **Alerty**: auto-alert jeśli p95 > 1000 ms lub error rate > 1%
- **Dashboardy**: Grafana z metrykami API, DB, cache hit rate

### 14.9 Przegląd końcowy i handover (Etap 18)

> Wyniki weryfikacji końcowej przed przekazaniem systemu. Szczegółowy stan pozycji w `docs/implementation-roadmap.md` (Etap 18).

#### 14.9.1 Stan zweryfikowanych obszarów

| # | Obszar | Status | Uwagi |
|---|---|---|---|
| 1 | Lokalizacje UI (`locales/pl/`) | ✅ | Rejestracja scentralizowana w `app.config.ts` (`registerMany`); skan szablonów nie wykrył twardych tekstów UI |
| 2 | Autocomplete w selectach | ✅ | `AutocompleteSelectComponent` dla selektorów encji; `SelectComponent` (natywny) dla stałych, małych zbiorów |
| 3 | Filtrowanie w listach | ✅ | `FilterBarComponent` na stronach list; sortowanie/paginacja w `DataTableComponent` |
| 4 | Responsywność ≥ 320 px | ✅ | meta viewport `viewport-fit=cover`; klasy responsywne Tailwind |
| 5 | Uprawnienia + IDOR | ✅ | `PermissionGuard`/`PermissionMiddleware`; własność zasobu w `NoteService` |
| 6 | Wydajność API | ✅ (mechanizmy) | Paginator 25/100, sparse fieldsets, cache, kompresja; SLO mierzone na środowisku docelowym |
| 7 | PWA (SW, offline, bg sync) | ✅ | `ngsw-config.json`, `OfflineService`, `IndexedDbService`; 189 testów frontendu |
| 8 | Web Vitals | ✅ (mechanizmy) | `WebVitalsService` raportuje LCP/FID/CLS/INP; finalny pomiar na prod |
| 9 | Nagłówki bezpieczeństwa | ✅ | `SecurityHeadersMiddleware` + testy |
| 10 | Polityka haseł | ✅ | `PasswordPolicyService` (min. 12, 3/4 klasy, blokada popularnych) |
| 11 | Blokada konta | ✅ | `AuthService` (5 → 15 min, 20/24 h → ręczne) |
| 12 | Audit log + retencja | ✅ (logowanie) | Logowanie działa; retencja 12 mies. jako zadanie cron |
| 13 | RODO | ✅ | `DELETE /employees/{id}` (fizyczne usunięcie, FK `SET NULL`); retencja 2/5 lat |
| 14 | Indeksy DB | ✅ | Indeksy w migracjach; `slow_query_log`/`EXPLAIN` na środowisku z bazą |
| 15 | Cache | ✅ | `CacheManager` (inwalidacja tagowa); hit rate na środowisku docelowym |
| 16 | Sekrety | ✅ (skan ręczny) | Brak sekretów; `.env` nie śledzone; pre-commit + `.gitleaks.toml`; `gitleaks detect` w CI |
| 17 | Penetration test | ⏳ zewnętrzny | Wykonać przed Go-Live (OWASP Top 10) |

#### 14.9.2 Poprawki wprowadzone w ramach przeglądu (2026-08-12)

- **Backend / PHPStan**: `backend/phpstan.neon` — usunięto `memoryLimit` z `parameters` (składnia PHPStan 2.x); limit pamięci przekazywany przez skrypt `composer analyse` (`--memory-limit=1G`). `composer analyse` przechodzi bez błędów (level 9).
- **Frontend / testy**: `frontend/src/app/services/indexed-db.service.spec.ts` — dodano czyszczenie wszystkich store'ów w `beforeEach` (izolacja stanu współdzielonej bazy `fake-indexeddb`). Wszystkie 189 testów frontendu przechodzi.

#### 14.9.3 Wymagania infrastrukturalne / zewnętrzne (do wdrożenia przed Go-Live)

- Retencja danych: cron czyszczenia `audit_log` (12 mies.) oraz `revoked_refresh_tokens`, archiwizacja/anonimizacja nieaktywnych pracowników (2/5 lat).
- CI/CD: uruchomienie `gitleaks detect` oraz testów (backend Pest, frontend Karma) w pipeline.
- Penetration test OWASP Top 10 (zewnętrzny audyt bezpieczeństwa).
- Pomiar SLO wydajności (p95 < 500 ms, p99 < 200 ms dla cache) oraz Web Vitals na środowisku produkcyjnym.



| Środowisko | Cel | URL |
|---|---|---|
| Development | Lokalny development | `http://localhost:4200` (frontend) + `http://localhost:8080` (backend) |
| Production | Produkcja | `https://pbs.adammz.pl` (frontend) + `https://pbs-api.adammz.pl` (backend) |

### 15.1 Konfiguracja domen

Domeny są konfigurowane **osobno** dla backendu i frontendu — nie są hardcodowane w kodzie (poza wartościami domyślnymi w plikach środowisk).

#### Backend — `backend/.env`

Backend czyta konfigurację z pliku **`backend/.env`** (wczytywanego przez `Config::fromEnvFile(__DIR__ . '/../.env')`). Plik **`.env.production` jest wzorcem** konfiguracji produkcyjnej — nie jest czytany bezpośrednio przez aplikację, lecz kopiowany do `.env` przez skrypt build (patrz 15.3).

```env
# === Baza danych ===
DATABASE_HOST=...
DATABASE_NAME=...
DATABASE_LOGIN=...
DATABASE_PASSWORD=...

# === Domeny ===
API_BASE_URL=https://pbs-api.adammz.pl   # Domena backend API (z protokołem, bez końcowego /)
FRONTEND_BASE_URL=https://pbs.adammz.pl   # Domena frontend (do linków w e-mailach, CORS)
CORS_ALLOWED_ORIGINS=https://pbs.adammz.pl # Dozwolone originy dla CORS (oddzielone przecinkiem)

# === JWT ===
JWT_SECRET=...                            # Klucz tajny do podpisywania tokenów
JWT_ACCESS_TTL=900                        # Czas życia access tokena (sekundy) — 15 min
JWT_REFRESH_TTL=604800                     # Czas życia refresh tokena (sekundy) — 7 dni
```

#### Frontend — `frontend/src/environments/environment.prod.ts`

Frontend **nie używa pliku `.env`** — konfiguracja środowisk jest w plikach TypeScript (`src/environments/`). Dla builda produkcyjnego `angular.json` podmienia `environment.ts` → `environment.prod.ts` (mechanizm `fileReplacements`). Kluczowe pole to **`apiUrl` — pełny, absolutny adres API** (względna ścieżka `/api/v1` działa tylko, gdy frontend i backend są na tej samej domenie).

```ts
export const environment = {
  production: true,
  apiUrl: 'https://pbs-api.adammz.pl/api/v1',  // pełny adres API
  frontendUrl: 'https://pbs.adammz.pl',        // domena frontendu
  httpTimeout: 20000,
  httpRetryAttempts: 1,
  cacheTtl: 60000,
  refreshBeforeExpirySeconds: 60,
};
```

> **Uwaga:** `API_BASE_URL` / `FRONTEND_BASE_URL` z `backend/.env` konfigurują **backend** (CORS, linki w e-mailach) i **nie wpływają** na URL API we frontendzie. URL API frontendu pochodzi wyłącznie z `environment.prod.ts` (`apiUrl`).

### 15.2 Pliki `.env.example`

W repozytorium znajdują się pliki `.env.example` (`backend/.env.example`) z przykładowymi wartościami. Pliki `.env` są ignorowane przez Git (`.gitignore`) i zawierają rzeczywiste, wrażliwe dane. Przy wdrożeniu na nowe środowisko należy skopiować `.env.example` → `.env` i podmienić wartości na właściwe dla danego środowiska.

### 15.3 Build i wdrożenie

Projekt zawiera skrypty build/deploy w `scripts/`:

| Skrypt | Opis |
|---|---|
| `scripts/build-all.sh` | Buduje backend + frontend do `dist/` (gotowe do uploadu). Opcje: `--tar-only` (dodatkowo archiwa `.tar.gz`), `--no-env` (nie kopiuj `.env.production`), `--help` |
| `scripts/build-backend.sh` | Buduje sam backend do `dist/backend/` (opcje jak wyżej) |
| `scripts/deploy-backend.sh` | Wgrywa zbudowany backend na FTP przez `curl` (zmienne `FTP_HOST`, `FTP_USER`, `FTP_PASS`, opcjonalnie `FTP_REMOTE_PATH`, `FTP_USE_TLS`) |

**Obsługa środowisk przy buildzie:**

- **Backend:** skrypt build domyślnie kopiuje `backend/.env.production` → `dist/backend/.env`. Dzięki temu pakiet wgrywany na serwer ma konfigurację produkcyjną, a lokalny `backend/.env` (dev) pozostaje nietknięty. Użyj `--no-env`, aby pominąć kopiowanie (np. gdy nie chcesz sekretów w `dist/`).
- **Frontend:** build produkcyjny (`ng build`) używa `environment.prod.ts` (przez `fileReplacements`). Skrypt czyści cache Angulara (`.angular/cache`) przed buildem, aby uniknąć stale bundle.

**Wynik builda (`dist/`):**

```
dist/
├── backend/            # backend PHP (document root: public/)
│   ├── public/         # index.php + .htaccess (front controller)
│   ├── .env            # konfiguracja produkcyjna (z .env.production)
│   ├── src/, vendor/, ...
├── frontend/           # zbudowana aplikacja Angular (SPA)
│   ├── index.html
│   └── .htaccess       # SPA fallback
└── *.tar.gz            # opcjonalne archiwa
```

**Routing i SPA fallback (Apache / nginx):**

- **Backend** to front controller — wszystkie żądania `/api/v1/*` muszą trafiać do `public/index.php`. Na Apache robi to `backend/public/.htaccess` (`RewriteRule ^ index.php [QSA,L]`); na nginx — `try_files $uri /index.php?$query_string;` (patrz `backend/deploy/nginx-https.conf`). Document root musi wskazywać na `public/`.
- **Frontend** to SPA — wszystkie nieistniejące ścieżki muszą trafiać do `index.html` (SPA fallback), inaczej twarde odświeżenie (Ctrl+Shift+R) na trasie typu `/pracownicy` zwraca 404. Na Apache robi to `frontend/public/.htaccess` (`RewriteRule ^ index.html [L]`); na nginx — `try_files $uri $uri/ /index.html;` (patrz `frontend/deploy/nginx-https.conf`).

**CORS:**

- Backend obsługuje CORS w `CorsMiddleware` (whitelist z `CORS_ALLOWED_ORIGINS` w `.env`). Przy niedozwolonym originie loguje diagnostykę `[PBS][CORS] ...` do logów serwera.
- `backend/public/.htaccess` zawiera dodatkowy fallback dla preflight (OPTIONS) z konkretnym originem (nie `*`, bo frontend używa `withCredentials: true`).

---

## 16. Skills (umiejętności AI)

Projekt wykorzystuje dwa skille AI zarejestrowane w `skills-lock.json`:

| Skill | Źródło | Zakres |
|---|---|---|
| `angular-developer` | `angular/skills` | Generowanie kodu Angular, komponenty, serwisy, routing, state management (Signals), formularze, dekoracje UI, testy |
| `php-pro` | `jeffallan/claude-skills` | Backend PHP 8.3+, REST API, PSR standards, PHPStan level 9, DTO/Value Objects, migracje, testy PHPUnit/Pest |

---

> **Koniec dokumentacji technicznej PBS v1.7**