# Port Baltic Shipping (PBS) — Dokumentacja Techniczna

> **Wersja dokumentu:** 1.1  
> **Data:** 2026-08-07  
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
├── docs/
│   ├── technical-documentation.md
│   └── implementation-roadmap.md  # Plan wdrożenia z etapami do odznaczania
├── backend/
│   ├── .env                      # Zmienne środowiskowe (DATABASE_HOST, DATABASE_NAME, DATABASE_LOGIN, DATABASE_PASSWORD)
│   ├── public/                   # Document root (index.php)
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
│   │   └── environments/         # Konfiguracja środowisk
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
│           └── awaria.json      # Teksty sekcji Awaria
```

Zasady:

- Język domyślny aplikacji: **pl (polski)** — wszystkie teksty UI muszą mieć wpis w `locales/pl/`.
- Każdy tekst odwoływany jest przez klucz (np. `common.buttons.save`, `pracownicy.form.firstName`).
- Hardcodowane stringi w szablonach (.html) i komponentach (.ts) są **niedozwolone** — wszystkie teksty użytkownika przechodzą przez serwis tłumaczeń.
- Serwis `TranslateService` udostępnia metodę `translate(key: string): string` oraz pipe `translate` do użycia w szablonach: `{{ 'pracownicy.title' | translate }}`.
- Struktura obiektu w `locales/pl/` jest jedynym źródłem prawdy dla tekstów UI — ułatwia to przyszłą internacjonalizację i utrzymanie spójności.
- Komunikaty błędów i powodzenia (toast, alerty) również pochodzą z plików lokalizacji, z podziałem na `success`, `error`, `warning`, `info`.
- Zmienne dynamiczne w tłumaczeniach obsługiwane są przez interpolację (np. `pracownicy.deleted.success: "Usunięto pracownika {{name}}"`).

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
- `PermissionGuard` — sprawdza uprawnienia użytkownika do danej sekcji
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

### 6.5 State Management

- Angular Signals dla lokalnego stanu komponentów
- Serwisy z `BehaviorSubject` lub `signal()` dla stanu współdzielonego
- Cache HTTP z interceptorem

### 6.6 Stylowanie

- Tailwind CSS — utility-first, brak osobnych plików CSS (poza globalnymi)
- Design system: zmienne Tailwind dla kolorów firmowych, spacing, typografii
- Responsywność: breakpointy Tailwind (`sm`, `md`, `lg`, `xl`)

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
- Role: `super_admin` (konto główne) + custom roles z per-sekcja uprawnieniami
- Uprawnienia per sekcja: `dashboard`, `pracownicy`, `sprzet`, `terminale`, `harmonogram`, `analityka`, `raportowanie`, `ustawienia`, `awaria`

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
| role | ENUM('super_admin', 'admin', 'user') | Rola |
| permissions | JSON | Uprawnienia per sekcja |
| is_active | BOOLEAN | Czy konto aktywne |
| created_at | DATETIME | |
| updated_at | DATETIME | |

#### 8.1.2 Pracownicy (`employees`)

| Kolumna | Typ | Opis |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| imie | VARCHAR(100) | |
| nazwisko | VARCHAR(100) | |
| telefon | VARCHAR(20) | |
| email | VARCHAR(255) | |
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
| employee_id | INT UNSIGNED FK → employees.id | |

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

### 8.2 Diagram relacji (uproszczony)

```
users ──┬── incident_comments
        └── incidents (zgloszona_przez)
        └── daily_*_reports (utworzony_przez)

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
- **Prawo do bycia zapomnianym**: endpoint `DELETE /api/v1/employees/{id}` realizuje anonymizację (nadpisanie danych osobowych wartościami `[deleted]`) zamiast fizycznego usunięcia, gdy wymagane przez RODO
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

- Widok: tabela z filtrowaniem (imię, nazwisko, terminal, sprzęt)
- Akcje: dodaj, edytuj, usuń, szybkie przypisanie terminala/sprzętu
- Podstrona edycji: dane podstawowe + zakładka "Certyfikaty i uprawnienia"
- Certyfikaty: lista dokumentów z detekcją wygaśnięcia (30 dni przed = alert)

### 10.3 Sprzęt

- Widok: tabela z kategoriami pojazdy/inne, filtrowanie
- Pojazdy: dodatkowe kolumny (przebieg, serwis, awaria, OC)
- Akcje: dodaj, edytuj, usuń, szybkie przypisanie
- Timeline: oś czasu dla każdego sprzętu
- Planowanie przeglądów: konfiguracja interwałów, automatyczne oznaczanie wymagających serwisu

### 10.4 Terminale

- Widok: lista terminali (adres, operator, dane kontaktowe)
- Akcje: dodaj, edytuj, usuń

### 10.5 Harmonogram (zintegrowany ze Zleceniami)

- Widok: siatka tygodniowa/miesięczna z przełączaniem na dzień
- Każde zlecenie: numer, klient, terminal, datetime, zakres prac, wartość, status, przypisani pracownicy/sprzęt
- Akcje: utwórz, edytuj, usuń zlecenie, kopiuj tydzień jako szablon
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

- Zarządzanie użytkownikami: tworzenie (email z linkiem do ustawienia hasła), edycja uprawnień, blokowanie
- Zarządzanie alertami: konfiguracja e-maili odbiorców, typów alertów, harmonogramów wysyłki
- Dostęp: tylko `super_admin`

### 10.9 Awaria!

- Zgłaszanie awarii: uproszczony formularz (czego dotyczy → sprzęt/inne → wybór sprzętu → opis)
- Lifecycle: zgłoszona → w trakcie naprawy → naprawiona / zamknięta
- Widok szczegółowy: zmiana statusu (+ data zakończenia), komentarze, historia statusów
- Czas przestoju: dostępny w analityce

---

## 11. API Endpoints

### 11.1 Autentykacja

| Metoda | Endpoint | Opis |
|---|---|---|
| POST | `/api/v1/auth/login` | Logowanie (zwraca JWT) |
| POST | `/api/v1/auth/refresh` | Odświeżenie tokena |
| POST | `/api/v1/auth/logout` | Wylogowanie |
| POST | `/api/v1/auth/set-password` | Ustawienie hasła (link z e-maila) |

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

| Metoda | Endpoint | Opis |
|---|---|---|
| GET | `/api/v1/employees` | Lista pracowników (z filtrami) |
| POST | `/api/v1/employees` | Dodanie pracownika |
| GET | `/api/v1/employees/{id}` | Szczegóły pracownika (z dokumentami) |
| PUT | `/api/v1/employees/{id}` | Edycja pracownika |
| DELETE | `/api/v1/employees/{id}` | Usunięcie pracownika |
| PATCH | `/api/v1/employees/{id}/assignment` | Szybkie przypisanie terminala/sprzętu |

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
| GET | `/api/v1/equipment` | Lista sprzętu (z filtrami) |
| POST | `/api/v1/equipment` | Dodanie sprzętu |
| GET | `/api/v1/equipment/{id}` | Szczegóły sprzętu (z historią) |
| PUT | `/api/v1/equipment/{id}` | Edycja sprzętu |
| DELETE | `/api/v1/equipment/{id}` | Usunięcie sprzętu |
| PATCH | `/api/v1/equipment/{id}/assignment` | Szybkie przypisanie |
| GET | `/api/v1/equipment/{id}/timeline` | Oś czasu sprzętu |

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
| GET | `/api/v1/terminals` | Lista terminali |
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
| POST | `/api/v1/orders/{id}/assign-employee` | Przypisanie pracownika |
| DELETE | `/api/v1/orders/{id}/assign-employee/{empId}` | Usunięcie przypisania pracownika |
| POST | `/api/v1/orders/{id}/assign-equipment` | Przypisanie sprzętu |
| DELETE | `/api/v1/orders/{id}/assign-equipment/{eqId}` | Usunięcie przypisania sprzętu |

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

---

## 12. Modele danych

### 12.1 Frontend (TypeScript interfaces)

Przykładowe interfejsy TypeScript dla kluczowych encji:

```typescript
export interface User {
  id: number;
  email: string;
  role: 'super_admin' | 'admin' | 'user';
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
  equipment?: Equipment[];
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
| `equipment` | `INDEX(is_active)`, `INDEX(kategoria)`, `INDEX(current_employee_id)`, `INDEX(current_terminal_id)` |
| `orders` | `INDEX(terminal_id)`, `INDEX(status)`, `INDEX(data_rozpoczecia)`, `INDEX(data_zakonczenia)`, `INDEX(numer_zlecenia UNIQUE)` |
| `incidents` | `INDEX(status)`, `INDEX(equipment_id)`, `INDEX(data_zgloszenia)`, `INDEX(zgloszona_przez)` |
| `equipment_history` | `INDEX(equipment_id, data)` |
| `daily_terminal_reports` | `INDEX(terminal_id, data_raportu)` |
| `daily_vehicle_reports` | `INDEX(equipment_id, data_raportu)` |
| `audit_log` | `INDEX(user_id, created_at)`, `INDEX(action)`, `INDEX(resource_type, resource_id)` |
| `order_employees` | `INDEX(order_id)`, `INDEX(employee_id)` |
| `order_equipment` | `INDEX(order_id)`, `INDEX(equipment_id)` |

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


## 15. Środowiska

| Środowisko | Cel | URL |
|---|---|---|
| Development | Lokalny development | `http://localhost:4200` (frontend) + `http://localhost:8080` (backend) |
| Staging | Testy QA / UAT | `https://staging.pbs.example.com` |
| Production | Produkcja | `https://app.pbs.example.com` |

### 15.1 Konfiguracja domen w plikach `.env`

Domeny, na których działają aplikacja frontendowa i backend API, są konfigurowane w plikach `.env` — **nie są hardcodowane w kodzie**. Pozwala to na łatwe przełączanie między środowiskami (dev/staging/production) bez zmian w kodzie.

#### `backend/.env`

```env
# === Baza danych ===
DATABASE_HOST=...
DATABASE_NAME=...
DATABASE_LOGIN=...
DATABASE_PASSWORD=...

# === Domeny ===
API_BASE_URL=https://api.pbs.pl          # Domena backend API (z protokołem, bez końcowego /)
FRONTEND_BASE_URL=https://app.pbs.pl      # Domena frontend (do linków w e-mailach, CORS)
CORS_ALLOWED_ORIGINS=https://app.pbs.pl   # Dozwolone originy dla CORS (oddzielone przecinkiem)

# === JWT ===
JWT_SECRET=...                            # Klucz tajny do podpisywania tokenów
JWT_ACCESS_TTL=900                        # Czas życia access tokena (sekundy) — 15 min
JWT_REFRESH_TTL=604800                     # Czas życia refresh tokena (sekundy) — 7 dni
```

#### `frontend/.env`

```env
# === Domeny i endpointy ===
API_BASE_URL=https://api.pbs.pl/api/v1     # Pełny adres API, z którego frontend pobiera dane
FRONTEND_BASE_URL=https://app.pbs.pl        # Domena, na której działa frontend
```

### 15.2 Pliki `.env.example`

W repozytorium znajdują się pliki `.env.example` (`backend/.env.example`, `frontend/.env.example`) z przykładowymi wartościami. Pliki `.env` są ignorowane przez Git (`.gitignore`) i zawierają rzeczywiste, wrażliwe dane. Przy wdrożeniu na nowe środowisko należy skopiować `.env.example` → `.env` i podmienić wartości na właściwe dla danego środowiska.

---

## 16. Skills (umiejętności AI)

Projekt wykorzystuje dwa skille AI zarejestrowane w `skills-lock.json`:

| Skill | Źródło | Zakres |
|---|---|---|
| `angular-developer` | `angular/skills` | Generowanie kodu Angular, komponenty, serwisy, routing, state management (Signals), formularze, dekoracje UI, testy |
| `php-pro` | `jeffallan/claude-skills` | Backend PHP 8.3+, REST API, PSR standards, PHPStan level 9, DTO/Value Objects, migracje, testy PHPUnit/Pest |

---

> **Koniec dokumentacji technicznej PBS v1.1**