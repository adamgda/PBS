# Port Baltic Shipping (PBS) — Plan wdrożenia

> **Wersja:** 1.0  
> **Data:** 2026-06-09  
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

- [ ] Endpoint: `POST /api/v1/auth/login`
- [ ] Endpoint: `POST /api/v1/auth/refresh`
- [ ] Endpoint: `POST /api/v1/auth/logout`
- [ ] Endpoint: `POST /api/v1/auth/set-password` (link z e-maila)
- [ ] Generacja i walidacja JWT (access + refresh)
- [ ] Middleware `AuthMiddleware` (weryfikacja tokena)
- [ ] Middleware `PermissionMiddleware` (uprawnienia per sekcja)
- [ ] Zarządzanie rolami (`super_admin`, `admin`, `user`)
- [ ] Mechanizm rotacji refresh tokena
- [ ] Testy: login, refresh, logout, set-password, permission denied

## Etap 4 — Fundamenty frontendu

- [x] Konfiguracja Tailwind CSS + design system (kolory firmowe, spacing, typografia)
- [x] Konfiguracja routingu Angular (lazy-loaded standalone components)
- [ ] `AuthGuard` (wymóg zalogowania)
- [ ] `PermissionGuard` (uprawnienia per sekcja)
- [ ] Serwis `AuthService` (login, refresh, logout, przechowywanie tokena)
- [ ] HTTP Interceptor: dołączanie JWT + automatyczny refresh
- [x] **Struktura katalogu lokalizacji `frontend/src/locales/pl/`** (common, dashboard, pracownicy, sprzet, terminale, harmonogram, analityka, raportowanie, ustawienia, awaria)
- [ ] Serwis `TranslateService` + pipe `translate`
- [ ] Współdzielone komponenty: `ToastNotificationComponent`, `ConfirmDialogComponent`
- [ ] Współdzielone komponenty: `DataTableComponent`, `FilterBarComponent`, `AutocompleteSelectComponent`
- [ ] Współdzielone komponenty: `KpiCardComponent`, `AlertWidgetComponent`, `TimelineComponent`, `CalendarComponent`
- [ ] Wszystkie listy rozwijane z autocomplete
- [ ] Wszystkie listy z opcją filtrowania

## Etap 5 — Sekcja: Użytkownicy (Ustawienia → Użytkownicy)

- [ ] Backend: `GET/POST /api/v1/users`
- [ ] Backend: `GET/PUT/DELETE /api/v1/users/{id}`
- [ ] Backend: `PATCH /api/v1/users/{id}/permissions`
- [ ] Frontend: lista użytkowników (DataTable + filtry)
- [ ] Frontend: formularz tworzenia użytkownika (email → link)
- [ ] Frontend: edycja uprawnień (per sekcja)
- [ ] Frontend: blokowanie/usuwanie użytkownika
- [ ] Lokalizacje: `ustawienia.json` w `locales/pl/`

## Etap 6 — Sekcja: Terminale

- [ ] Backend: `GET/POST /api/v1/terminals`
- [ ] Backend: `GET/PUT/DELETE /api/v1/terminals/{id}`
- [ ] Frontend: lista terminali (filtrowanie)
- [ ] Frontend: formularz dodawania/edycji terminala
- [ ] Frontend: usuwanie terminala (confirm dialog)
- [ ] Lokalizacje: `terminale.json` w `locales/pl/`

## Etap 7 — Sekcja: Pracownicy

- [ ] Backend: `GET/POST /api/v1/employees`
- [ ] Backend: `GET/PUT/DELETE /api/v1/employees/{id}`
- [ ] Backend: `PATCH /api/v1/employees/{id}/assignment` (terminal/sprzęt)
- [ ] Backend: `GET/POST /api/v1/employees/{id}/documents`
- [ ] Backend: `PUT/DELETE /api/v1/documents/{id}`
- [ ] Frontend: lista pracowników (DataTable + filtry: imię, nazwisko, terminal, sprzęt)
- [ ] Frontend: formularz dodawania/edycji pracownika
- [ ] Frontend: zakładka "Certyfikaty i uprawnienia" (dokumenty + detekcja wygaśnięcia)
- [ ] Frontend: szybkie przypisanie terminala/sprzętu
- [ ] Lokalizacje: `pracownicy.json` w `locales/pl/`

## Etap 8 — Sekcja: Sprzęt

- [ ] Backend: `GET/POST /api/v1/equipment`
- [ ] Backend: `GET/PUT/DELETE /api/v1/equipment/{id}`
- [ ] Backend: `PATCH /api/v1/equipment/{id}/assignment`
- [ ] Backend: `GET /api/v1/equipment/{id}/timeline`
- [ ] Backend: `GET/POST /api/v1/equipment/{id}/service-plans`
- [ ] Backend: `PUT/DELETE /api/v1/service-plans/{id}`
- [ ] Frontend: lista sprzętu (kategorie pojazdy/inne, filtrowanie)
- [ ] Frontend: formularz dodawania/edycji sprzętu + szczegóły pojazdu (przebieg, serwis, OC)
- [ ] Frontend: komponent `TimelineComponent` (historia sprzętu)
- [ ] Frontend: planowanie przeglądów (interwały km/dni, auto-oznaczanie serwisu)
- [ ] Frontend: szybkie przypisanie pracownika/terminala
- [ ] Lokalizacje: `sprzet.json` w `locales/pl/`

## Etap 9 — Sekcja: Harmonogram / Zlecenia

- [ ] Backend: `GET/POST /api/v1/orders`
- [ ] Backend: `GET/PUT/DELETE /api/v1/orders/{id}`
- [ ] Backend: `POST /api/v1/orders/{id}/copy-week`
- [ ] Backend: `POST/DELETE /api/v1/orders/{id}/assign-employee`
- [ ] Backend: `POST/DELETE /api/v1/orders/{id}/assign-equipment`
- [ ] Frontend: widok kalendarza (tydzień/miesiąc/dzień)
- [ ] Frontend: formularz zlecenia (numer, klient, terminal, datetime, zakres, wartość, status)
- [ ] Frontend: przypisywanie pracowników i sprzętu do zlecenia
- [ ] Frontend: kopiowanie tygodnia jako szablon
- [ ] Lokalizacje: `harmonogram.json` w `locales/pl/`

## Etap 10 — Sekcja: Awaria

- [ ] Backend: `GET/POST /api/v1/incidents`
- [ ] Backend: `GET /api/v1/incidents/{id}` (komentarze + historia statusów)
- [ ] Backend: `PATCH /api/v1/incidents/{id}/status`
- [ ] Backend: `POST /api/v1/incidents/{id}/comments`
- [ ] Frontend: uproszczony formularz zgłoszenia awarii
- [ ] Frontend: lista awarii (filtrowanie po statusie/typie)
- [ ] Frontend: widok szczegółowy awarii (komentarze, historia statusów, czas zakończenia)
- [ ] Frontend: zmiana statusu (zgłoszona → w trakcie naprawy → naprawiona / zamknięta)
- [ ] Lokalizacje: `awaria.json` w `locales/pl/`

## Etap 11 — Sekcja: Raportowanie

- [ ] Backend: `GET/POST /api/v1/reports/terminal`
- [ ] Backend: `GET/PUT /api/v1/reports/terminal/{id}`
- [ ] Backend: `GET/POST /api/v1/reports/vehicle`
- [ ] Backend: `GET/PUT /api/v1/reports/vehicle/{id}`
- [ ] Frontend: raporty terminalowe (auto-dane z harmonogramu)
- [ ] Frontend: raporty pojazdowe (przebieg, przebieg OC)
- [ ] Frontend: lista i edycja raportów
- [ ] Lokalizacje: `raportowanie.json` w `locales/pl/`

## Etap 12 — Sekcja: Analityka

- [ ] Backend: `GET /api/v1/analytics/overview`
- [ ] Backend: `GET /api/v1/analytics/terminals`
- [ ] Backend: `GET /api/v1/analytics/employees`
- [ ] Backend: `GET /api/v1/analytics/equipment`
- [ ] Backend: `GET /api/v1/analytics/relations`
- [ ] Frontend: wykresy (integracja danych + cross-sekcje)
- [ ] Frontend: filtry czasowe (zakres dat, domyślnie 30 dni)
- [ ] Frontend: statystyki (terminale, pracownicy, sprzęt + relacje)
- [ ] Frontend: czas przestoju awarii w analityce
- [ ] Lokalizacje: `analityka.json` w `locales/pl/`

## Etap 13 — Sekcja: Dashboard

- [ ] Backend: `GET /api/v1/dashboard/summary` (KPI)
- [ ] Backend: `GET /api/v1/dashboard/alerts`
- [ ] Frontend: siatka kart KPI
- [ ] Frontend: widgety alertów (certyfikaty, przeglądy, awarie)
- [ ] Frontend: skróty akcji (zgłoś awarię, utwórz raport, dodaj zlecenie)
- [ ] Frontend: responsywność mobile-first
- [ ] Lokalizacje: `dashboard.json` w `locales/pl/`

## Etap 14 — Alerty i powiadomienia

- [ ] Backend: `GET/POST /api/v1/settings/alert-configs`
- [ ] Backend: `PUT/DELETE /api/v1/settings/alert-configs/{id}`
- [ ] Frontend: sekcja Ustawienia → Alerty (lista odbiorców, typy, harmonogram)
- [ ] Backend: mechanizm sprawdzania warunków alertów (cron/queue)
- [ ] Backend: wysyłka e-mail (SMTP) — certyfikaty, przeglądy, brak raportu OC, awarie
- [ ] Testy: scenariusze alertów (wygaśnięcie 30 dni, brak raportu do 10:00)
- [ ] Lokalizacje: komunikaty alertów w `ustawienia.json` / `common.json`

## Etap 15 — Bezpieczeństwo i hardening

- [ ] HTTPS/TLS 1.3 na wszystkich środowiskach
- [ ] CORS whitelist
- [ ] Rate limiting (100 req/min IP, 1000 req/min user)
- [ ] CSRF tokeny dla mutate endpoints
- [ ] Content-Security-Policy + Helmet-style headers
- [ ] Walidacja i sanitization wejścia (kontroler + serwis)
- [ ] Prepared statements (PDO) — weryfikacja braku SQL injection
- [ ] Szyfrowanie danych wrażliwych (AES-256-GCM)
- [ ] Hash haseł: bcrypt/Argon2id
- [ ] JWT RS256 + krótki TTL + rotacja refresh
- [ ] Audyt bezpieczeństwa / penetration test

## Etap 16 — Testy i jakość

- [ ] Frontend: Jasmine/Karma — testy jednostkowe komponentów
- [ ] Frontend: testy serwisów i guardów
- [ ] Frontend: testy e2e (opcjonalnie Cypress/Playwright)
- [ ] Backend: PHPUnit/Pest — testy kontrolerów i serwisów
- [ ] Backend: testy repozytoriów i migracji
- [ ] Backend: testy integracyjne API
- [ ] PHPStan level 9 — brak błędów
- [ ] Pokrycie testów ≥ 80% (backend)
- [ ] CI/CD: uruchomienie testów na każdym PR

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

- [ ] Weryfikacja wszystkich lokalizacji UI w `locales/pl/` (brak hardcodowanych stringów)
- [ ] Weryfikacja autocomplete w wszystkich selectach
- [ ] Weryfikacja filtrowania we wszystkich listach
- [ ] Weryfikacja responsywności (≥ 320px)
- [ ] Weryfikacja uprawnień per sekcja
- [ ] Weryfikacja wydajności API (< 500ms dla 95% zapytań)
- [ ] Aktualizacja dokumentacji technicznej
- [ ] Szkolenie użytkowników / handover

---

> **Uwaga:** Po wdrożeniu i weryfikacji każdego kroku odznacz go zmieniając `- [ ]` na `- [x]`. Dokument jest źródłem prawdy o postępie projektu PBS.