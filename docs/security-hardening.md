# PBS — Hardening bezpieczeństwa (Etap 15)

Dokument ten zbiera procedury i konfiguracje zrealizowane w ramach **Etapu 15 — Bezpieczeństwo i hardening**.
Szczegóły techniczne: `docs/technical-documentation.md` sekcja 9 (Bezpieczeństwo) i 14.5 (timeout'y).

## 1. Co zostało zaimplementowane w kodzie

| Obszar | Implementacja |
|---|---|
| Szyfrowanie at-rest | `backend/src/Services/CryptoService.php` — AES-256-GCM z `APP_KEY` (32 B base64) |
| Rate limiting | `backend/src/Security/RateLimitStore.php` + `RateLimiterMiddleware` (100/min IP, 1000/min user) |
| Login / set-password rate limit | `AuthService` — login 5/min/IP, 10/h/konto, set-password 3/h/token |
| Nagłówki bezpieczeństwa | `SecurityHeadersMiddleware` (HSTS, nosniff, DENY, CSP, Referrer, Permissions, Cache-Control no-store, X-Robots) |
| CSRF | `CsrfMiddleware` — walidacja `Origin` dla metod mutujących + opcjonalny `X-CSRF-Token` (endpoint `GET /api/v1/auth/csrf`) |
| JWT | `JwtService`/`AuthMiddleware` — **RS256** na produkcji, HS256 w dev, jawny algorytm + walidacja issuera |
| Walidacja produkcji | `App`/`Config` — odrzucenie `CORS_ALLOWED_ORIGINS=*`, słabego `JWT_SECRET`, `APP_DEBUG=true` na produkcji |
| display_errors | `public/index.php` — wyłączone poza trybem debug |

## 2. TLS / HTTPS (TLS 1.3)

- Przykładowa konfiguracja reverse-proxy: `backend/deploy/nginx-https.conf`.
- Wymagane: certyfikat (Let's Encrypt / komercyjny), przekierowanie HTTP → HTTPS, HSTS `max-age=31536000; includeSubDomains; preload`.
- Protokoły: **TLS 1.3** (wymuszony), TLS 1.2 jako fallback; wyłącznie cipher suites ECDHE/AEAD.

Procedura wdrożenia:
1. Wydaj certyfikat dla domeny API.
2. Wdróż `nginx-https.conf` (dostosuj ścieżki).
3. Ustaw w `.env` produkcji: `API_BASE_URL=https://api.pbs.pl`, `CORS_ALLOWED_ORIGINS=https://app.pbs.pl`.
4. Zweryfikuj HSTS za pomocą https://hstspreload.org po stabilnym okresie.

## 3. Rotacja sekretów (co 90 dni)

Rotacji podlegają: `JWT_SECRET` / para kluczy RSA, `APP_KEY`, hasła SMTP i DB.

- `JWT_PRIVATE_KEY` / `JWT_PUBLIC_KEY` (RS256): wygeneruj nową parę, podpisz, odśwież. Okres overlap 7 dni — stary i nowy publiczny klucz akceptowane równolegle.
- `APP_KEY`: zmiana klucza wymaga re-szyfrowania danych (odczyt kluczem starym → zapis nowym). Wykonywać podczas okna konserwacyjnego.
- `JWT_SECRET` (HS256 w dev): zmiana unieważnia wszystkie tokeny — wymaga ponownego logowania.

## 4. Zarządzanie sekretami (9.6)

- `.env` **nie są commitowane** — potwierdzone w `.gitignore` (`.env`, `.env.*`).
- Na produkcji użyj Docker Secrets / Vault / cloud secret manager (AWS Secrets Manager, GCP Secret Manager).
- Pre-commit skan sekretów: `scripts/pre-commit-secret-scan.sh` + `.gitleaks.toml`.
- Skan całego repo: `gitleaks detect --source . -c .gitleaks.toml`.

## 5. RODO — retencja i anonimizacja (9.2)

- Prawo do bycia zapomnianym: `DELETE /api/v1/employees/{id}` — fizyczne usunięcie, powiązania historyczne zostają (`ON DELETE SET NULL`).
- Retencja: pracownicy nieaktywni archiwizowani po 2 latach, usuwani po 5 latach.
- Retencja `audit_log`: 12 miesięcy.
- Do wdrożenia (cron): zadanie archiwizacji/anonimizacji pracowników po 2/5 latach oraz czyszczenia `audit_log` i `revoked_refresh_tokens`.

## 6. CSRF — aktywacja na produkcji

Obecnie `CsrfMiddleware` domyślnie wymusza tylko walidację `Origin` (niezależnie od frontendu).
Aby wymusić pełne tokeny CSRF:

1. Frontend po zalogowaniu pobiera token: `GET /api/v1/auth/csrf` (Bearer).
2. Wysyła go w nagłówku `X-CSRF-Token` dla POST/PUT/PATCH/DELETE.
3. W `.env` produkcji: `CSRF_ENFORCE=true`.

## 7. Eksport danych (CSV) — bezpieczeństwo (Etap 22)

- Endpoint `GET /api/v1/exports/{type}` jest **read-only** — zwraca surowy plik CSV (`Response::raw()`), nie JSON.
- Dostęp ograniczony uprawnieniem sekcji `export_csv` (`PermissionMiddleware`); `super_admin` ma bypass.
- Odpowiedź zawiera `Content-Type: text/csv; charset=utf-8` oraz `Cache-Control: no-store` — dane osobowe (np. eksport pracowników) nie są cache'owane.
- Biała lista typów eksportu w `ExportService` — nieobsługiwany typ zwraca 422; zakres dat (`from`/`to`) walidowany (nieprawidłowa data → 422).
- Eksport danych osobowych (pracownicy, sprzęt z przypisaniami) podlega tej samej polityce RODO co reszta API — ograniczony do użytkowników z uprawnieniem `export_csv`.
- **Zalecenie (do wdrożenia):** logowanie eksportów danych osobowych w `audit_log` (kto, kiedy, jaki typ i zakres wyeksportował).

## 8. Pozostałe elementy etapu (infrastruktura / zewnętrzne)

- **Penetration test / audyt bezpieczeństwa** — wykonać zewnętrznie przed produkcyjnym Go-Live.
- **Docker Secrets / Vault** — konfiguracja środowiskowa (poza repozytorium).
- **`slow_query_log`**, Redis cache — należą do **Etapu 15a (Wydajność)**.

## 9. Dzwonek powiadomień (Etap 23)

- `NotificationBellComponent` jest **read-only** — agreguje dane z `GET /api/v1/dashboard/alerts` i `GET /api/v1/dashboard/charts` (te same, co dashboard), **nie wprowadza nowych endpointów ani nowych danych osobowych**.
- Dane podlegają tej samej polityce co dashboard: cache TTL 60 s, brak `no-store` dla danych osobowych (alerty nie zawierają danych wrażliwych poza nazwiskami pracowników, które i tak są widoczne w sekcji Pracownicy).
- Dostęp do dzwonka mają wszyscy zalogowani użytkownicy (jak Notatki) — nie wymaga osobnego uprawnienia; treść pozycji prowadzi do sekcji, do których użytkownik ma uprawnienia (odnośniki do `/employees`, `/equipment`, `/incidents`).
