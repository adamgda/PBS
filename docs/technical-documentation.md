# Port Baltic Shipping (PBS) — Dokumentacja Techniczna

> **Wersja dokumentu:** 1.0  
> **Data:** 2026-06-09  
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

- Hasła: bcrypt (PASSWORD_BCRYPT) lub Argon2id (PASSWORD_ARGON2ID)
- Klucze API / tokeny: random_bytes() + base64 encode
- Szyfrowanie danych wrażliwych: AES-256-GCM (openssl_encrypt)

### 9.2 Ochrona danych osobowych

- Minimalizacja: tylko niezbędne dane pracowników przechowywane
- Ekspozycja przez API: odpowiednie mapowanie DTO, bez przesyłania niepotrzebnych pól
- Pseudonimizacja tam gdzie możliwe

### 9.3 REST API Security

- HTTPS (TLS 1.3)
- CORS: whitelist dozwolonych originów
- Rate limiting (np. 100 req/min na IP, 1000 req/min na użytkownika)
- Input validation i sanitization
- SQL Injection: prepared statements (PDO)
- XSS: Content-Security-Policy headers, Output encoding
- CSRF: tokeny w nagłówkach dla mutate endpoints
- Helmet-style HTTP headers

### 9.4 JWT Security

- Krótki czas wygaśnięcia access tokena (15 minut)
- Refresh token z rotacją
- Token przechowywany w HttpOnly cookie (opcjonalnie)
- Sygnowanie RS256 z dedykowaną parą kluczy

---

## 10. Sekcje aplikacji — szczegółowy opis

### 10.1 Dashboard

- Widok: siatka kart KPI + widgety alertów + skróty
- KPI: liczba aktywnych pracowników, obsługiwane terminale, pojazdy w użyciu, aktywne awarie
- Alerty: certyfikaty bliskie wygaśnięcia, zbliżające się przeglądy, nierozwiązane awarie
- Skróty: zgłoś awarię, utwórz raport, dodaj zlecenie
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
| Wydajność | Czas odpowiedzi API < 500ms dla 95% zapytań |
| Responsywność | Mobile first, wszystkie widoki działają na ekranach ≥ 320px |
| Dostępność | Aplikacja dostępna 24/7 (SLA 99.5%) |
| Bezpieczeństwo | OWASP Top 10, HTTPS, Content Security Policy |
| Skalowalność | Architektura pozwalająca na horyzontalne skalowanie backendu |
| Testowalność | Frontend: Jasmine/Karma; Backend: PHPUnit/Pest |
| Monitorowanie | Logowanie błędów, metryki API |

---

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

> **Koniec dokumentacji technicznej PBS v1.0**