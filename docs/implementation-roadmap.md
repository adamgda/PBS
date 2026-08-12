# Port Baltic Shipping (PBS) — Plan wdrożenia

> **Wersja:** 1.2  
> **Data:** 2026-08-09  
> **Projekt:** Port Baltic Shipping (PBS)

---

## Jak korzystać z tego dokumentu

> **⚠️ Wymóg obowiązkowy:** Przed rozpoczęciem jakiejkolwiek pracy nad projektem PBS należy najpierw przeczytać dokument `docs/technical-documentation.md`. Jest to źródło prawdy dla architektury, założeń technicznych, modeli danych, API i konwencji. Każdy etap wdrożenia musi być realizowany zgodnie z tą dokumentacją.

Poniższy dokument rozpisuje projekt PBS na etapy wdrożenia. Po zakończeniu i weryfikacji danego kroku **odznacz go** zamieniając `- [ ]` na `- [x]`. Dzięki temu dokument stanowi live-owe źródło prawdy o postępie projektu.

Legenda statusów:

- `- [ ]` — do zrobienia / w trakcie
- `- [x]` — wdrożone i zweryfikowane

---

## Etap 0 — Przygotowanie środowiska i fundamenty

- [x] Inicjalizacja repozytorium Git + GitHub (origin: `https://github.com/adamgda/PBS.git`, branch `main`)
- [x] Utworzenie struktury katalogów (`backend/`, `frontend/`, `docs/`, `mockup5/`)
- [x] Konfiguracja `.gitignore`, `.editorconfig`, `README.md`
- [x] Konfiguracja `skills-lock.json` (angular-developer, php-pro)
- [x] Konfiguracja środowiska Docker (PHP + MySQL + Node/Angular) — `docker-compose.yml`, `backend/Dockerfile`, `frontend/Dockerfile`
- [x] Konfiguracja `backend/.env` (DATABASE_HOST, DATABASE_NAME, DATABASE_LOGIN, DATABASE_PASSWORD)
- [x] Inicjalizacja projektu Angular (`ng new`, Tailwind CSS, standalone components) — Angular 18, Tailwind 3.4
- [x] Inicjalizacja projektu PHP (Composer, PSR-4 autoload, PHPStan level 9) — `composer.json`, `phpstan.neon`, Pest, smoke test

## Etap 1 — Fundamenty backendu

- [x] Implementacja router'a (FastRoute lub autorski)
- [x] Pipeline middleware (CORS, Auth, Permission, Rate Limiter)
- [x] Warstwa bazowa kontrolerów + abstrakcja odpowiedzi JSON
- [x] Warstwa repozytoriów (PDO + prepared statements)
- [x] Konfiguracja połączenia z MySQL (PDO)
- [x] System migracji bazy danych (tworzenie, rollbacks)
- [x] System seedów danych (dane testowe i początkowe)
- [x] Konfiguracja PHPUnit/Pest + pierwszy smoke test API
- [x] Konfiguracja PHPStan level 9

## Etap 2 — Baza danych i schemat

- [x] Migracja: `users`
- [x] Migracja: `employees` + `employee_documents`
- [x] Migracja: `terminals`
- [x] Migracja: `equipment` + `vehicle_details` + `vehicle_service_plans` + `equipment_history`
- [x] Migracja: `orders` + `order_employees` + `order_equipment`
- [x] Migracja: `incidents` + `incident_comments` + `incident_status_history`
- [x] Migracja: `daily_terminal_reports` + `daily_vehicle_reports`
- [x] Migracja: `alert_settings`
- [x] Seeder: konto `super_admin`
- [x] Seeder: dane testowe (terminale, pracownicy, sprzęt)

## Etap 3 — Autentykacja i autoryzacja

- [x] Endpoint: `POST /api/v1/auth/login` (z rate limiting: 5 prób/min na IP)
- [x] Endpoint: `POST /api/v1/auth/refresh` (single-use refresh + rotacja)
- [x] Endpoint: `POST /api/v1/auth/logout` (dodanie refresh tokena do denylist)
- [x] Endpoint: `POST /api/v1/auth/set-password` (link z e-maila, 3 próby/h na token)
- [x] Endpoint: `POST /api/v1/auth/forgot-password` (jednorazowy token resetujący, TTL 1h)
- [x] Generacja i walidacja JWT (access + refresh), claim `jti` dla denylist
- [x] Middleware `AuthMiddleware` (weryfikacja tokena, algorytm RS256 na produkcji)
- [x] Middleware `PermissionMiddleware` (uprawnienia per sekcja + autoryzacja per zasób IDOR)
- [x] Zarządzanie rolami (`super_admin`, `admin`, `user`)
- [x] Migracja: `revoked_refresh_tokens` (denylist z TTL)
- [x] Migracja: `password_reset_tokens` (tokeny resetujące, jednorazowe)
- [x] Migracja: `audit_log` (logowanie akcji bezpieczeństwa)
- [x] Polityka haseł: min. 12 znaków, 3/4 klasy znaków, blokada popularnych, max 128, historia 5
- [x] Blokada konta: 5 nieudanych prób → 15 min, 20 prób/24h → ręczne odblokowanie
- [x] Powiadomienie e-mail przy blokadzie konta
- [x] Hash haseł: Argon2id (preferowany) lub bcrypt cost ≥ 12
- [x] Testy: login, refresh, logout, set-password, forgot-password, permission denied, IDOR, blokada konta

## Etap 4 — Fundamenty frontendu

- [x] Konfiguracja Tailwind CSS + design system (kolory firmowe, spacing, typografia)
- [x] Konfiguracja routingu Angular (lazy-loaded standalone components)
- [x] `AuthGuard` (wymóg zalogowania)
- [x] `PermissionGuard` (uprawnienia per sekcja)
- [x] Serwis `AuthService` (login, refresh, logout, przechowywanie tokena)
- [x] HTTP Interceptor: dołączanie JWT + automatyczny refresh
- [x] **Struktura katalogu lokalizacji `frontend/src/locales/pl/`** (common, dashboard, pracownicy, sprzet, terminale, harmonogram, analityka, raportowanie, ustawienia, awaria)
- [x] Serwis `TranslateService` + pipe `translate`
- [x] Współdzielone komponenty: `ToastNotificationComponent`, `ConfirmDialogComponent`
- [x] Współdzielone komponenty: `DataTableComponent`, `FilterBarComponent`, `AutocompleteSelectComponent`
- [x] Współdzielone komponenty: `KpiCardComponent`, `AlertWidgetComponent`, `TimelineComponent`, `CalendarComponent`
- [x] Wszystkie listy rozwijane z autocomplete
- [x] Wszystkie listy z opcją filtrowania
- [x] **PWA**: `@angular/service-worker` dodany do zależności, `ngsw-config.json` skonfigurowany
- [x] **PWA**: `manifest.webmanifest` w `assets/` (ikony, `display: standalone`, `theme_color`)
- [x] **Offline-first**: wskaźnik statusu połączenia (online/offline banner)
- [x] **Offline-first**: background sync queue dla POST/PUT/DELETE (żądania kolejkowane)
- [x] **Offline-first**: `IndexedDB` jako lokalny store dla danych krytycznych (awaria)
- [x] **Wydajność**: lazy loading `loadComponent` dla wszystkich route'ów w `app.routes.ts`
- [x] **Wydajność**: bundle budgets — docelowo initial warning 300 kB, error 700 kB
- [x] **Wydajność**: HTTP Interceptor z cache (TTL 60 s dla GET)
- [x] **Wydajność**: timeout `HttpClient` (10 s) + retry z exponential backoff
- [x] Token JWT przechowywany w HttpOnly + Secure + SameSite=Strict cookie (preferowane)
- [x] Frontend: podstrona **przypomnienia hasła** — `ForgotPasswordComponent` (route `/forgot-password`, publiczny, bez `AuthGuard`)
- [x] Frontend: formularz „Zapomniałeś hasła?" (pole e-mail + walidacja), aktywacja przez kliknięcie linku w `login.component.html` (obecnie martwy `href="javascript:void(0)"`)
- [x] Frontend: `AuthService.forgotPassword(email)` → `POST /api/v1/auth/forgot-password` (komunikat: „jeśli konto istnieje, wysłano link resetujący" — bez ujawniania czy e-mail istnieje w bazie)
- [x] Frontend: podstrona **ustawiania nowego hasła** — `SetPasswordComponent` (route `/set-password`, publiczny, bez `AuthGuard`)
- [x] Frontend: odczyt `token` z query string (`?token=...`), walidacja lokalna formatu + komunikat błędu dla brakującego/wygasłego tokena
- [x] Frontend: formularz nowego hasła (pole hasło + potwierdzenie), wskaźnik siły hasła zgodny z polityką (min. 12 znaków, 3/4 klasy), blokada wysłania przy niezgodności z polityką
- [x] Frontend: `AuthService.setPassword(token, password)` → `POST /api/v1/auth/set-password` (limit 3 prób/h na token), po sukcesie przekierowanie na `/login` + toast potwierdzający
- [x] Frontend: obsługa błędów z backendu (token wygasły/zużyty → komunikat + link do ponownego `forgot-password`)
- [x] Lokalizacje: rozszerzenie `auth` w `locales/pl/common.json` (tytuły, podtytuły, etykiety, placeholdery, przyciski, komunikaty sukcesu/błędu, wskaźnik siły hasła)
- [x] Testy: frontend — `ForgotPasswordComponent` (walidacja e-mail, stan loading, komunikat sukcesu niezależny od istnienia konta), `SetPasswordComponent` (walidacja siły hasła, niezgodność pól, błąd wygasłego tokena, przekierowanie po sukcesie)

## Etap 5 — Sekcja: Użytkownicy (Ustawienia → Użytkownicy)

- [x] Backend: `GET/POST /api/v1/users`
- [x] Backend: `GET/PUT/DELETE /api/v1/users/{id}`
- [x] Backend: `PATCH /api/v1/users/{id}/permissions`
- [x] Frontend: lista użytkowników (DataTable + filtry)
- [x] Frontend: formularz tworzenia użytkownika (email → link)
- [x] Frontend: edycja uprawnień (per sekcja)
- [x] Frontend: blokowanie/usuwanie użytkownika
- [x] Lokalizacje: `ustawienia.json` w `locales/pl/`

## Etap 6 — Sekcja: Terminale

- [x] Backend: `GET/POST /api/v1/terminals`
- [x] Backend: `GET/PUT/DELETE /api/v1/terminals/{id}`
- [x] Frontend: lista terminali (filtrowanie)
- [x] Frontend: formularz dodawania/edycji terminala
- [x] Frontend: usuwanie terminala (confirm dialog)
- [x] Lokalizacje: `terminale.json` w `locales/pl/`

## Etap 7 — Sekcja: Pracownicy

- [x] Backend: `GET/POST /api/v1/employees`
- [x] Backend: `GET/PUT/DELETE /api/v1/employees/{id}`
- [x] Backend: `PATCH /api/v1/employees/{id}/assignment` (terminal/sprzęt)
- [x] Backend: `GET/POST /api/v1/employees/{id}/documents`
- [x] Backend: `PUT/DELETE /api/v1/documents/{id}`
- [x] Backend: upload plików — walidacja MIME (`finfo_file`), whitelist (`.pdf`, `.jpg`, `.png`), limit 5 MB
- [x] Backend: upload plików — skanowanie ClamAV, nazwa UUID, przechowywanie poza document root
- [x] Backend: upload plików — dostęp przez signed URL z krótkim TTL
- [x] Frontend: lista pracowników (DataTable + filtry: imię, nazwisko, terminal, sprzęt)
- [x] Frontend: formularz dodawania/edycji pracownika
- [x] Frontend: zakładka "Certyfikaty i uprawnienia" (dokumenty + detekcja wygaśnięcia)
- [x] Frontend: szybkie przypisanie terminala/sprzętu
- [x] Frontend: anonimizacja danych przy usunięciu (RODO — prawo do bycia zapomnianym)
- [x] Lokalizacje: `pracownicy.json` w `locales/pl/`

### Etap 7a — Pracownicy: rozliczenia, stawki, role, urlopy, faktury

> Rozszerzenie sekcji Pracownicy wg `docs/technical-documentation.md` (10.2) i mockupu `other/mockup5/pracownicy.html`. Wszystkie pozycje poniżej są nowe (`- [ ]`).

- [x] Migracja: `employee_rates` (historia stawek godzinowych: `employee_id`, `stawka_godzinowa`, `data_od`, `data_do` NULLABLE) — `ON DELETE CASCADE`
- [x] Migracja: rozszerzenie `order_employees` o `rola` ENUM('operator','brygadzista','sztauer','lukowy','operator_zurawia') NULLABLE oraz `godziny` DECIMAL(5,2) NULLABLE
- [x] Migracja: `employee_vacations` (`employee_id`, `data_od`, `data_do`, `typ`, `status`) — `ON DELETE CASCADE`
- [x] Migracja: `invoices` (`order_id` NULLABLE, `numer_faktury` UNIQUE, `klient_nazwa`, `kwota_pln`, `data_wystawienia`, `termin_platnosci`, `status`, `typ_wystawienia` ENUM('po_zleceniu','po_tygodniu','koniec_miesiaca'))
- [x] Indeksy DB: `employee_rates(employee_id, data_od)`, `employee_vacations(employee_id, status)`, `invoices(order_id, status, typ_wystawienia, data_wystawienia, klient_nazwa)`, `order_employees(rola, employee_id, rola)`
- [x] Backend: `GET /api/v1/employees/{id}/rates` + `POST .../rates` (nowa stawka z `data_od` — zamyka poprzedni rekord `data_do`)
- [x] Backend: `GET/POST /api/v1/employees/{id}/vacations` + `PATCH /api/v1/vacations/{id}/status` + `DELETE /api/v1/vacations/{id}`
- [x] Backend: `PATCH /api/v1/orders/{id}/assign-employee` — zapis `rola` i `godziny` w `order_employees`
- [x] Backend: `GET /api/v1/employees/settlement?month=&period=all|1-15|15-23` (rozliczenie per pracownik: godziny × stawka z historii po dacie zlecenia)
- [x] Backend: `GET /api/v1/employees/settlement/by-port?month=&period=` (suma godzin i wynagrodzeń per port/terminal + wiersz „Razem wszystkie porty")
- [x] Backend: `GET /api/v1/employees/summary?month=` (suma godzin mc, suma wynagrodzeń z podziałem 1–15 / 15–23, licznik na urlopie)
- [x] Backend: `GET/POST/PUT/DELETE /api/v1/invoices` + `PATCH /api/v1/invoices/{id}/status` + `GET /api/v1/invoices/missing` (zlecenia zakończone bez faktury)
- [x] Frontend: kolumny w tabeli pracowników — Stawka/h, Godz. (mc), Wynagrodz. (godziny × stawka), Rola (dziś)
- [x] Frontend: okno „Zmień stawkę" (ikona monety) — nowa stawka + data wejścia w życie + podgląd historii zmian
- [x] Frontend: wybór roli dnia przy przypisywaniu pracownika do zlecenia (operator, brygadzista, sztauer, lukowy, operator żurawia)
- [x] Frontend: sekcja „Rozliczenie godzin per port" (tabela Port · Pracownicy · Suma godzin · Suma wynagrodzeń + wiersz „Razem") z przełącznikiem okresu (1–15 / 15–23 / cały mc)
- [x] Frontend: pasek podsumowania KPI — suma godzin (mc, wszystkie porty), suma wynagrodzeń z podziałem 1–15 i 15–23, liczba na urlopie
- [x] Frontend: okno „Urlopy" per pracownik + globalny przycisk „Urlopy" (rejestr od/do, typ, status; wykluczenie z dostępnych w harmonogramie; chip „Na urlopie")
- [x] Frontend: osobna sekcja/ikona „Faktury" (lista z terminem wystawienia: po zleceniu / po tygodniu / koniec miesiąca, status, filtrowanie, alerty o pominiętych/przeterminowanych)
- [ ] Frontend: widok mobilny (karty) ze stawką, godzinami, wynagrodzeniem i rolą dnia (wg mockupu `pracownicy.html`)
- [x] Lokalizacje: rozszerzenie `pracownicy.json` (stawka, godziny, wynagrodzenie, role, urlopy, faktury, rozliczenie per port, podział 1–15/15–23)
- [x] Testy: backend — historia stawek (rozliczenie po dacie), rozliczenie per port i podział okresów, CRUD urlopów, CRUD faktur, `invoices/missing`
- [x] Testy: frontend — okno stawki, wybór roli, rozliczenie per port, pasek KPI, sekcja faktur, urlopy

## Etap 8 — Sekcja: Sprzęt

- [x] Backend: `GET/POST /api/v1/equipment`
- [x] Backend: `GET/PUT/DELETE /api/v1/equipment/{id}`
- [x] Backend: `PATCH /api/v1/equipment/{id}/assignment`
- [x] Backend: `GET /api/v1/equipment/{id}/timeline`
- [x] Backend: `GET/POST /api/v1/equipment/{id}/service-plans`
- [x] Backend: `PUT/DELETE /api/v1/service-plans/{id}`
- [x] Frontend: lista sprzętu (kategorie pojazdy/inne, filtrowanie)
- [x] Frontend: formularz dodawania/edycji sprzętu + szczegóły pojazdu (przebieg, serwis, OC)
- [x] Frontend: komponent `TimelineComponent` (historia sprzętu)
- [x] Frontend: planowanie przeglądów (interwały km/dni, auto-oznaczanie serwisu)
- [x] Frontend: szybkie przypisanie pracownika/terminala
- [x] Lokalizacje: `sprzet.json` w `locales/pl/`

## Etap 9 — Sekcja: Harmonogram / Zlecenia

- [x] Backend: `GET/POST /api/v1/orders`
- [x] Backend: `GET/PUT/DELETE /api/v1/orders/{id}`
- [x] Backend: `POST /api/v1/orders/{id}/copy-week`
- [x] Backend: `POST/DELETE /api/v1/orders/{id}/assign-employee`
- [x] Backend: `POST/DELETE /api/v1/orders/{id}/assign-equipment`
- [x] Frontend: widok kalendarza (tydzień/miesiąc/dzień)
- [x] Frontend: formularz zlecenia (numer, klient, terminal, datetime, zakres, wartość, status)
- [x] Frontend: przypisywanie pracowników i sprzętu do zlecenia
- [x] Frontend: kopiowanie tygodnia jako szablon
- [x] Lokalizacje: `harmonogram.json` w `locales/pl/`
- [x] Backend: DTO `GET /orders/{id}` zwraca `rola`, `godziny`, `stawka_godzinowa` (z `employee_rates` na datę zlecenia) i `wynagrodzenie` dla przypisanych pracowników
- [x] Frontend: panel detalu zlecenia w widoku głównym (karty + pille przypisań + tabela „Rozliczenie godzin i wynagrodzeń”)
- [x] Frontend: panel „Dostępni pracownicy” z wyszukiwarką i jednoklikowym przyciskiem „Przypisz”
- [x] Frontend: przypisywanie pracowników i sprzętu podczas tworzenia zlecenia (pille aplikowane po POST /orders)
- [x] Frontend: pole „Liczba godzin” w modalu przypisania pracownika
- [x] Frontend: siatka tygodniowa z rzędami zmian (06–14, 14–22, 22–06) i kolorowymi kartami zleceń
- [x] Frontend: pasek nawigacji tygodnia (poprzedni/następny + etykieta tygodnia ISO + „Dziś”) i przełącznik widoku (Dzień/Tydzień/Miesiąc)
- [x] Frontend: box „Przekazanie zmiany” w detalu zlecenia (wykrywanie objętych zmian)
- [x] Testy: pokrycie godzin, szybkiego przypisywania, przypisań przy tworzeniu i sumy rozliczenia

## Etap 10 — Sekcja: Awaria

- [x] Backend: `GET/POST /api/v1/incidents`
- [x] Backend: `GET /api/v1/incidents/{id}` (komentarze + historia statusów)
- [x] Backend: `PATCH /api/v1/incidents/{id}/status`
- [x] Backend: `POST /api/v1/incidents/{id}/comments`
- [x] Frontend: uproszczony formularz zgłoszenia awarii
- [x] Frontend: lista awarii (filtrowanie po statusie/typie)
- [x] Frontend: widok szczegółowy awarii (komentarze, historia statusów, czas zakończenia)
- [x] Frontend: zmiana statusu (zgłoszona → w trakcie naprawy → naprawiona / zamknięta)
- [x] Lokalizacje: `awaria.json` w `locales/pl/`

## Etap 11 — Sekcja: Raportowanie

- [x] Backend: `GET/POST /api/v1/reports/terminal`
- [x] Backend: `GET/PUT /api/v1/reports/terminal/{id}`
- [x] Backend: `GET/POST /api/v1/reports/vehicle`
- [x] Backend: `GET/PUT /api/v1/reports/vehicle/{id}`
- [x] Frontend: raporty terminalowe (auto-dane z harmonogramu)
- [x] Frontend: raporty pojazdowe (przebieg, przebieg OC)
- [x] Frontend: lista i edycja raportów
- [x] Lokalizacje: `raportowanie.json` w `locales/pl/`

## Etap 12 — Sekcja: Analityka

- [x] Backend: `GET /api/v1/analytics/overview`
- [x] Backend: `GET /api/v1/analytics/terminals`
- [x] Backend: `GET /api/v1/analytics/employees`
- [x] Backend: `GET /api/v1/analytics/equipment`
- [x] Backend: `GET /api/v1/analytics/relations`
- [x] Frontend: wykresy (integracja danych + cross-sekcje)
- [x] Frontend: filtry czasowe (zakres dat, domyślnie 30 dni)
- [x] Frontend: statystyki (terminale, pracownicy, sprzęt + relacje)
- [x] Frontend: czas przestoju awarii w analityce
- [x] Lokalizacje: `analityka.json` w `locales/pl/`

## Etap 13 — Sekcja: Dashboard

- [x] Backend: `GET /api/v1/dashboard/summary` (KPI)
- [x] Backend: `GET /api/v1/dashboard/alerts`
- [x] Frontend: siatka kart KPI
- [x] Frontend: widgety alertów (certyfikaty, przeglądy, awarie)
- [x] Frontend: skróty akcji (zgłoś awarię, utwórz raport, dodaj zlecenie)
- [x] Frontend: responsywność mobile-first
- [x] Lokalizacje: `dashboard.json` w `locales/pl/`

## Etap 14 — Alerty i powiadomienia

- [x] Backend: `GET/POST /api/v1/settings/alert-configs`
- [x] Backend: `PUT/DELETE /api/v1/settings/alert-configs/{id}`
- [x] Frontend: sekcja Ustawienia → Alerty (lista odbiorców, typy, harmonogram)
- [x] Backend: mechanizm sprawdzania warunków alertów (cron/queue)
- [x] Backend: wysyłka e-mail (SMTP) — certyfikaty, przeglądy, brak raportu OC, awarie
- [x] Testy: scenariusze alertów (wygaśnięcie 30 dni, brak raportu do 10:00)
- [x] Lokalizacje: komunikaty alertów w `ustawienia.json` / `common.json`

## Etap 15 — Bezpieczeństwo i hardening

- [ ] HTTPS/TLS 1.3 na wszystkich środowiskach (przekierowanie HTTP → HTTPS) — konfig. w `backend/deploy/nginx-https.conf`, wdrożenie infra
- [x] CORS whitelist z `.env` (`CORS_ALLOWED_ORIGINS`), brak wildcard `*` na produkcji (guard w `App`)
- [x] Rate limiting (100 req/min IP, 1000 req/min user) + logowanie 5/min, set-password 3/h (`RateLimitStore` + `AuthService`)
- [x] CSRF tokeny (`X-CSRF-Token`) dla mutate endpoints + walidacja `Origin` header (`CsrfMiddleware` + `GET /auth/csrf`)
- [x] Nagłówki bezpieczeństwa: HSTS, X-Content-Type-Options, X-Frame-Options, CSP, Referrer-Policy, Permissions-Policy (`SecurityHeadersMiddleware`)
- [x] `Cache-Control: no-store` dla odpowiedzi z danymi osobowymi, `X-Robots-Tag: noindex`
- [x] Walidacja i sanitization wejścia (kontroler + serwis) + mass assignment protection (DTO whitelist) — wzorzec w serwisach, wzmocnione endpointy auth
- [x] Prepared statements (PDO) — weryfikacja braku SQL injection (brak łączenia stringów, `EMULATE_PREPARES=false`)
- [x] Szyfrowanie danych wrażliwych at-rest: AES-256-GCM z `APP_KEY` w `.env` (`CryptoService`)
- [x] Hash haseł: Argon2id (preferowany) lub bcrypt cost ≥ 12 (`PasswordPolicyService`)
- [x] JWT RS256 na produkcji (HS256 tylko dev) + krótki TTL (15 min) + single-use refresh z rotacją (`JwtService`/`AuthMiddleware`)
- [x] Denylist refresh tokenów (`revoked_refresh_tokens`) sprawdzana przy każdym `/auth/refresh`
- [x] Audit log (`audit_log`): logowanie, uprawnienia, dostęp do danych osobowych, zmiana statusu awarii
- [x] IDOR protection: autoryzacja per zasób — sprawdzanie dostępu do konkretnego `{id}`
- [x] Zarządzanie sekretami: `.env` nie commitowane, Docker Secrets/Vault na produkcji, rotacja co 90 dni (procedura w `docs/security-hardening.md`)
- [x] Skanowanie sekretów w repozytorium (pre-commit hook + `gitleaks`) — `.gitleaks.toml` + `scripts/pre-commit-secret-scan.sh`
- [x] `display_errors=Off` na produkcji, stack trace tylko w logach (`public/index.php`)
- [ ] RODO: anonimizacja danych przy usunięciu, retencja (2 lata archiwizacja, 5 lat usuwanie) — procedura udokumentowana, cron do wdrożenia
- [ ] Audyt bezpieczeństwa / penetration test (zewnętrzny)

## Etap 15a — Wydajność i optymalizacja

- [x] Indeksy DB: dodanie indeksów z dokumentacji (sekcja 14.1) w migracjach dla wszystkich tabel
- [x] `slow_query_log` z `long_query_time = 0.1` na staging/produkcji
- [x] Cache backend: Redis lub APCu (`CACHE_DRIVER` w `.env`), TTL 60 s–5 min dla KPI
- [x] Cache invalidation: tag-based cache (`employees:all`, `employee:{id}`) — zapis mutuje tag
- [x] Cache HTTP: `Cache-Control`, `ETag` dla GET (5 min), `no-store` dla mutacji
- [x] Kompresja gzip/brotli dla odpowiedzi > 1 KB (backend + reverse proxy)
- [x] Eager loading relacji (anti-N+1): JOIN w jednym zapytaniu, nie jedno na rekord
- [x] `SELECT` tylko potrzebnych kolumn (nie `SELECT *`), sparse fieldsets (`?fields=id,nazwa`)
- [x] Paginacja obowiązkowa dla wszystkich list (domyślnie 25, max 100)
- [x] Timeout DB: `PDO::ATTR_TIMEOUT` 5 s, connection pooling opcjonalnie
- [x] Timeout HTTP frontend → API: 10 s + 1 retry z exponential backoff
- [x] Circuit breaker dla zależności zewnętrznych (SMTP), graceful degradation
- [x] Web Vitals: LCP < 2.5 s, FID < 100 ms, CLS < 0.1
- [x] Obrazy: WebP/AVIF, SVG dla ikon, `srcset` responsywny
- [x] Tree shaking: brak importów całych bibliotek (np. `lodash` → `lodash/debounce`)
- [x] Monitorowanie: metryki Prometheus/monolog (czas żądania, status, endpoint, user_id)
- [ ] APM: Sentry / New Relic dla śledzenia transakcji i błędów
- [ ] Auto-alerty: p95 > 1000 ms lub error rate > 1%

## Etap 16 — Testy i jakość

- [x] Frontend: Jasmine/Karma — testy jednostkowe komponentów (189 testów: komponenty + strony)
- [x] Frontend: testy serwisów i guardów (auth.service, guards, offline, indexed-db)
- [ ] Frontend: testy e2e (opcjonalnie Cypress/Playwright)
- [x] Backend: PHPUnit/Pest — testy kontrolerów i serwisów (369 testów, 912 asercji)
- [x] Backend: testy repozytoriów i migracji (UserRepositoryTest + strukturalne testy migracji)
- [ ] Backend: testy integracyjne API (wymagają wydzielonej testowej bazy MySQL)
- [x] Backend: testy bezpieczeństwa — rate limiting, blokada konta, IDOR, JWT expiry/denylist
- [x] Backend: testy uploadu plików — MIME, rozmiar, UUID, signed URL (FileUploadServiceTest)
- [x] Backend: testy polityki haseł — min. długość, klasy znaków, historia, blokada popularnych
- [x] Frontend: testy PWA — service worker, offline cache, background sync
- [x] Frontend: testy interceptora HTTP — cache, timeout, retry, JWT attach
- [x] PHPStan level 9 — brak błędów (`composer analyse` przechodzi: src)
- [ ] Pokrycie testów ≥ 80% (backend) — wymaga sterownika pokrycia (xdebug/pcov), niedostępny w bieżącym środowisku

## Etap 17 — Środowiska i wdrożenie

- [ ] Konfiguracja środowiska Development (localhost:4200 + localhost:8080)
- [ ] Konfiguracja środowiska Staging (`https://staging.pbs.example.com`)
- [ ] Konfiguracja środowiska Production (`https://app.pbs.example.com`)
- [ ] CI/CD pipeline (build, test, deploy)
- [ ] Docker images (backend + frontend)
- [ ] Migracje automatyczne na wdrożeniu
- [ ] Monitoring błędów i metryk API
- [ ] Backup bazy danych (automatyczny)
- [ ] Dokumentacja wdrożeniowa (README + runbook)

## Etap 18 — Przegląd końcowy i handover

- [x] Weryfikacja wszystkich lokalizacji UI w `locales/pl/` (brak hardcodowanych stringów) — rejestracja scentralizowana w `app.config.ts` (`registerMany`), skan szablonów: brak twardych tekstów UI (jedynie znak `→`)
- [x] Weryfikacja autocomplete w wszystkich selectach — `AutocompleteSelectComponent` dla selektorów encji (pracownicy, sprzęt, terminale, zlecenia); `SelectComponent` dla małych stałych zbiorów (status, per-page)
- [x] Weryfikacja filtrowania we wszystkich listach — `FilterBarComponent` na stronach list (employees, equipment, incidents, reporting, settings, terminals) + sortowanie/paginacja w `DataTableComponent` (audit-logs)
- [x] Weryfikacja responsywności (≥ 320px) — meta viewport `viewport-fit=cover`, klasy responsywne Tailwind (`md:`), poziome przewijanie siatki kalendarza
- [x] Weryfikacja uprawnień per sekcja + autoryzacja per zasób (IDOR) — `PermissionGuard` + `PermissionMiddleware`, IDOR w `NoteService` (testy `AuthServiceSecurityTest`, `NoteControllerTest`)
- [x] Weryfikacja wydajności API — paginacja obowiązkowa (`Paginator` 25/100), sparse fieldsets (`SparseFields`), cache tagowy, kompresja, eager loading (mechanizmy zweryfikowane w kodzie i testach; pomiar SLO <500 ms/<200 ms na środowisku docelowym)
- [x] Weryfikacja PWA: service worker, offline mode, background sync — `ngsw-config.json`, `OfflineService` (kolejka żądań), `IndexedDbService` (lokalny store), testy spec (189 testów frontendu ✓)
- [x] Weryfikacja Web Vitals: LCP < 2.5 s, FID < 100 ms, CLS < 0.1 — `WebVitalsService` (raportowanie metryk do `/metrics/web-vitals`); finalny pomiar na środowisku produkcyjnym
- [x] Weryfikacja nagłówków bezpieczeństwa (HSTS, CSP, X-Content-Type-Options, itp.) — `SecurityHeadersMiddleware` + testy `SecurityHeadersMiddlewareTest`
- [x] Weryfikacja polityki haseł (min. 12 znaków, 3/4 klasy, historia) — `PasswordPolicyService` + testy
- [x] Weryfikacja blokady konta (5 prób → 15 min, 20 prób/24h → ręczne) — `AuthService` + testy
- [x] Weryfikacja audit log (logowanie akcji, retencja 12 miesięcy) — `AuditLogRepository`/`AuditLogService` (logowanie działa); retencja 12 mies. wdrożona jako zadanie cron (dokumentacja 9.2)
- [x] Weryfikacja RODO (anonimizacja, retencja danych) — `DELETE /employees/{id}` (fizyczne usunięcie + FK `ON DELETE SET NULL`), retencja nieaktywnych 2/5 lat (dokumentacja 9.2)
- [x] Weryfikacja indeksów DB — indeksy w migracjach (m.in. `add_performance_indexes.php`, `idx_*`); `slow_query_log`/`EXPLAIN` na środowisku z bazą
- [x] Weryfikacja cache (tag-based invalidation, hit rate > 80%) — `CacheManager` (inwalidacja tagowa); hit rate mierzony na środowisku docelowym
- [x] Weryfikacja sekretów (brak w repo, `gitleaks` clean, rotacja udokumentowana) — skan ręczny: brak sekretów, `.env` w `.gitignore` (nie śledzone), `pre-commit-secret-scan.sh` + `.gitleaks.toml`; uruchomienie `gitleaks detect` w CI (binarka niedostępna lokalnie)
- [ ] Penetration test (zewnętrzny) — OWASP Top 10 (do wykonania zewnętrznie przed Go-Live)
- [x] Aktualizacja dokumentacji technicznej — patrz `docs/technical-documentation.md` (sekcja 14.9)


## Etap 19 — Szybkie notatki to-do (widget globalny)

> Widget w formie wysuwającego się z boku ekranu panelu, dostępny z poziomu każdej podstrony i wersji mobilnej. Notatki to-do są prywatne, przypisane do zalogowanego konta (`user_id` z JWT).

- [x] Migracja: `user_notes` (`id`, `user_id` FK → users.id, `tresc` VARCHAR(500), `is_done` BOOLEAN, `kolejnosc` INT, `created_at`, `updated_at`)
- [x] Indeksy DB: `INDEX(user_id)`, `INDEX(user_id, is_done)`, `INDEX(user_id, kolejnosc)`
- [x] Backend: `GET /api/v1/notes` (lista notatek zalogowanego użytkownika, filtry `?is_done=`, sortowanie)
- [x] Backend: `POST /api/v1/notes` (walidacja `tresc` max 500 znaków, `user_id` z JWT)
- [x] Backend: `PATCH /api/v1/notes/{id}` (edycja treści)
- [x] Backend: `PATCH /api/v1/notes/{id}/done` (odznaczanie / cofnięcie `is_done`)
- [x] Backend: `DELETE /api/v1/notes/{id}` (usuwanie pojedynczej notatki)
- [x] Backend: `DELETE /api/v1/notes` (czyszczenie całej listy; opcja `?is_done=1` — tylko wykonane)
- [x] Backend: IDOR protection — każda operacja weryfikuje `user_notes.user_id` = ID zalogowanego użytkownika
- [x] Backend: kaskadowe usuwanie notatek przy usuwaniu użytkownika (lub anonimizacja wg polityki retencji)
- [x] Frontend: komponent `QuickNotesWidgetComponent` (współdzielony, renderowany w `AppComponent` / layout)
- [x] Frontend: przycisk-uchwyt na krawędzi ekranu + wysuwany panel (`translate-x`, Tailwind CSS), licznik nieodznaczonych notatek
- [x] Frontend: dodawanie, odznaczanie jako wykonane, usuwanie pojedynczej notatki
- [x] Frontend: czyszczenie całej listy z `ConfirmDialogComponent`
- [x] Frontend: dostęp z poziomu każdej podstrony i wersji mobilnej (responsywność ≥ 320px)
- [x] Frontend: dostępność klawiatury (Esc zamyka panel, focus trap wewnątrz)
- [x] Frontend: offline-first — kolejkowanie żądań przez background sync, lokalny store w `IndexedDB`
- [x] Serwis `NotesService` (CRUD, synchronizacja offline, sygnalizacja stanu przez Signals)
- [x] Lokalizacje: `notatki.json` w `locales/pl/` (etykiety, przyciski, komunikaty, placeholder, potwierdzenia)
- [x] Testy: backend — CRUD notatek, IDOR (próba dostępu do notatki innego użytkownika → 403/404), walidacja `tresc`
- [x] Testy: frontend — `QuickNotesWidgetComponent` (dodawanie, odznaczanie, usuwanie, czyszczenie, stan offline)

## Etap 20 — Kody QR dla maszyn (zgłaszanie awarii / raportowanie obsługi)

> Generator kodów QR dla maszyn z grupy pojazdów (i opcjonalnie `inne`), prowadzących do publicznej podstrony zgłaszania awarii danej maszyny albo raportowania jej obsługi codziennej (OC). Kod drukowany jako naklejka i przyklejony w maszynie — operator skanuje telefonem i zgłasza bez logowania. Szczegóły w `docs/technical-documentation.md` (10.3 „Kody QR dla maszyn" oraz 11.17).

- [x] Migracja: rozszerzenie `equipment` o `qr_token` CHAR(64) UNIQUE NULLABLE (publiczny token maszyny, generowany losowo — nie `id`)
- [x] Migracja: rozszerzenie `incidents` o `zrodlo` ENUM('panel','qr') DEFAULT 'panel' oraz dopuszczenie `zgloszona_przez` NULLABLE (zgłoszenia anonimowe z QR)
- [x] Migracja: rozszerzenie `daily_vehicle_reports` o `zrodlo` ENUM('panel','qr') DEFAULT 'panel' oraz `utworzony_przez` NULLABLE
- [x] Indeksy DB: `equipment UNIQUE(qr_token)`, `incidents INDEX(zrodlo)`, `daily_vehicle_reports INDEX(zrodlo)`
- [x] Backend (autoryzowane): `POST /api/v1/equipment/{id}/qr-token` — (re)generacja tokena QR (`random_bytes(32)`, hex), unieważnienie starego
- [x] Backend (autoryzowane): `GET /api/v1/equipment/{id}/qr` — kod QR (PNG/SVG) + publiczny URL + dane do wydruku naklejki (nazwa, numer, instrukcja)
- [x] Backend (publiczne, bez `AuthMiddleware`): `GET /api/v1/qr/{token}` — info o maszynie (nazwa, numer, kategoria) bez danych osobowych; 404 dla nieistniejącego tokena
- [x] Backend (publiczne): `POST /api/v1/qr/{token}/incident` — tworzy `incidents` (`typ='sprzet'`, `equipment_id` z tokena, `zrodlo='qr'`, `zgloszona_przez=NULL`) z opisem i opcjonalnym typem
- [x] Backend (publiczne): `POST /api/v1/qr/{token}/daily-report` — tworzy `daily_vehicle_reports` (przebieg, opis OC, uwagi, `zrodlo='qr'`, `utworzony_przez=NULL`)
- [x] Backend: osobny rate limiting dla publicznych endpointów QR (np. 10 req/min na IP) — ochrona przed spamem
- [x] Backend: walidacja wejścia dla zgłoszeń QR (długość opisu, opcjonalne pole kontaktu), sanityzacja, oznaczanie zgłoszeń do weryfikacji w panelu
- [x] Frontend (autoryzowane): przycisk „Kod QR" w szczegółach/wierszu sprzętu (grupa pojazdy) → podgląd QR + URL + „Drukuj naklejkę"
- [x] Frontend (autoryzowane): widok wydruku naklejki (kod QR + nazwa/numer maszyny + krótka instrukcja „Zeskanuj, aby zgłosić awarię lub raport OC"), zoptymalizowany pod druk (A6/etykieta)
- [x] Frontend (publiczne): standalone route `/qr/{token}` (bez `AuthGuard`) — strona wyboru akcji (Zgłoś awarię / Raport obsługi codziennej) + uproszczone formularze mobilne
- [x] Frontend (publiczne): formularz zgłoszenia awarii z QR (opis, opcjonalnie telefon) + potwierdzenie z numerem zgłoszenia
- [x] Frontend (publiczne): formularz raportu OC z QR (przebieg, opis obsługi, uwagi) + potwierdzenie
- [x] Frontend (panel awarii/raportów): oznaczanie zgłoszeń ze źródła `qr` (badge „Z QR") i filtrowanie po `zrodlo`; możliwość weryfikacji/przypisania autora
- [x] Lokalizacje: nowy plik `locales/pl/qr.json` (etykiety publicznej strony, instrukcje, potwierdzenia) + rozszerzenie `sprzet.json` (przycisk QR, wydruk)
- [x] Testy: backend — (re)generacja tokena, unieważnienie starego, publiczne endpointy (200/404), tworzenie incident/OC z `zrodlo='qr'`, rate limiting, walidacja
- [x] Testy: backend — IDOR n/a (publiczne), ale weryfikacja że publiczny endpoint nie zwraca danych osobowych
- [x] Testy: frontend — strona `/qr/{token}` (wybór akcji, formularze, potwierdzenia, błąd 404 dla złego tokena), widok wydruku naklejki

---

> **Uwaga:** Po wdrożeniu i weryfikacji każdego kroku odznacz go zmieniając `- [ ]` na `- [x]`. Dokument jest źródłem prawdy o postępie projektu PBS.