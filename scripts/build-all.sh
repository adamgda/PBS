#!/usr/bin/env bash
#
# Skrypt budujący CAŁY projekt PBS (backend + frontend) i składający
# gotowe do uploadu artefakty w katalogu dist/.
#
# Użycie:
#   ./scripts/build-all.sh              # buduje backend + frontend do dist/
#   ./scripts/build-all.sh --tar-only   # dodatkowo tworzy archiwa .tar.gz
#   ./scripts/build-all.sh --help       # pomoc
#
# Wynikowa struktura katalogu dist/:
#   dist/backend/    - produkcyjny backend PHP (document root: public/)
#   dist/frontend/   - zbudowana aplikacja Angular (SPA)
#   (opcjonalnie) dist/backend-<data>.tar.gz, dist/frontend-<data>.tar.gz
#
set -euo pipefail

# --- Konfiguracja -----------------------------------------------------------

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
BACKEND_DIR="${ROOT_DIR}/backend"
FRONTEND_DIR="${ROOT_DIR}/frontend"
DIST_DIR="${ROOT_DIR}/dist"
DIST_BACKEND="${DIST_DIR}/backend"
DIST_FRONTEND="${DIST_DIR}/frontend"
ARCHIVE_NAME="$(date +%Y%m%d-%H%M%S)"
DO_TAR=0
# Domyślnie kopiujemy .env.production → .env do pakietu (produkcja).
# Użyj --no-env, aby pominąć (np. gdy nie chcesz sekretów w dist/).
DO_ENV=1

# --- Kolory ----------------------------------------------------------------

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

info()  { printf "${GREEN}[INFO]${NC}  %s\n" "$1"; }
warn()  { printf "${YELLOW}[WARN]${NC}  %s\n" "$1"; }
error() { printf "${RED}[BŁĄD]${NC} %s\n" "$1"; }

# --- Sprawdzenie zależności ------------------------------------------------

check_php()    { command -v php     >/dev/null 2>&1 || { error "PHP nie jest zainstalowane."; exit 1; }; }
check_composer(){ command -v composer >/dev/null 2>&1 || { error "Composer nie jest zainstalowany."; exit 1; }; }
check_node()   { command -v node    >/dev/null 2>&1 || { error "Node.js nie jest zainstalowane."; exit 1; }
                  command -v npm    >/dev/null 2>&1 || { error "npm nie jest zainstalowany."; exit 1; }; }

# --- Backend ---------------------------------------------------------------

build_backend() {
    info "=========== BACKEND ==========="
    check_php
    check_composer

    # Produkcyjne zależności (bez dev)
    (cd "${BACKEND_DIR}" && composer install --no-dev --optimize-autoloader --no-interaction)

    info "Składanie dist/backend ..."
    rm -rf "${DIST_BACKEND}"
    mkdir -p "${DIST_BACKEND}"

    local dirs=(src public bin migrations seeds vendor)
    local files=(composer.json composer.lock pest.php m)

    for d in "${dirs[@]}"; do
        if [[ -d "${BACKEND_DIR}/${d}" ]]; then
            cp -R "${BACKEND_DIR}/${d}" "${DIST_BACKEND}/"
        else
            warn "Brak katalogu backend/${d}/ — pomijam."
        fi
    done
    for f in "${files[@]}"; do
        if [[ -f "${BACKEND_DIR}/${f}" ]]; then
            cp "${BACKEND_DIR}/${f}" "${DIST_BACKEND}/"
        else
            warn "Brak pliku backend/${f} — pomijam."
        fi
    done

    # === .env: kopiujemy .env.production jako .env (produkcja) ===
    if [[ "${DO_ENV}" -eq 1 ]]; then
        if [[ -f "${BACKEND_DIR}/.env.production" ]]; then
            cp "${BACKEND_DIR}/.env.production" "${DIST_BACKEND}/.env"
            warn "Skopiowano .env.production → .env (UWAGA: zawiera sekrety — nie udostępniaj dist/)."
        else
            warn "Brak backend/.env.production — .env nie został utworzony. Utwórz go na serwerze."
        fi
    else
        warn "Pominięto kopiowanie .env (--no-env). Utwórz .env na serwerze."
    fi
    info "Backend: ${DIST_BACKEND}"
}

# --- Frontend --------------------------------------------------------------

build_frontend() {
    info "=========== FRONTEND ==========="
    check_node

    if [[ ! -d "${FRONTEND_DIR}/node_modules" ]]; then
        info "Instalowanie zależności npm (npm ci) ..."
        (cd "${FRONTEND_DIR}" && npm ci)
    else
        info "node_modules już istnieje."
    fi

    info "Budowanie produkcyjnej wersji (ng build) ..."
    # Czyszczenie cache Angulara — bez tego build może zwrócić stale bundle
    # (nie rejestruje zmian w environment.prod.ts itd.). Usuwamy też stary dist.
    rm -rf "${FRONTEND_DIR}/.angular/cache" "${FRONTEND_DIR}/dist"
    (cd "${FRONTEND_DIR}" && npm run build)

    local built_dir="${FRONTEND_DIR}/dist/frontend"
    if [[ ! -d "${built_dir}" ]]; then
        error "Nie znaleziono wyniku builda: ${built_dir}"
        exit 1
    fi

    info "Kopiowanie do dist/frontend ..."
    rm -rf "${DIST_FRONTEND}"
    mkdir -p "${DIST_FRONTEND}"

    # Angular 18 (builder application) składa SPA w podkatalogu browser/ —
    # spłaszczamy go, żeby pliki leżały bezpośrednio w dist/frontend/.
    if [[ -d "${built_dir}/browser" ]]; then
        cp -R "${built_dir}/browser/." "${DIST_FRONTEND}/"
        # Pliki towarzyszące (licencje itd.) też kopiujemy, jeśli istnieją
        cp "${built_dir}"/3rdpartylicenses.txt "${DIST_FRONTEND}/" 2>/dev/null || true
    else
        cp -R "${built_dir}/." "${DIST_FRONTEND}/"
    fi

    # .htaccess (SPA fallback dla Apache) — kopiujemy jawnie, bo Angular
    # często pomija pliki z kropką przy kopiowaniu assetów.
    if [[ -f "${FRONTEND_DIR}/public/.htaccess" ]]; then
        cp "${FRONTEND_DIR}/public/.htaccess" "${DIST_FRONTEND}/.htaccess"
        info "Skopiowano .htaccess (SPA fallback) do dist/frontend/"
    fi
    info "Frontend: ${DIST_FRONTEND}"
}

# --- Archiwa ----------------------------------------------------------------

make_archives() {
    info "Tworzenie archiwów .tar.gz ..."
    (cd "${DIST_BACKEND}"  && tar -czf "${DIST_DIR}/backend-${ARCHIVE_NAME}.tar.gz" .)
    (cd "${DIST_FRONTEND}" && tar -czf "${DIST_DIR}/frontend-${ARCHIVE_NAME}.tar.gz" .)
    info "Archiwa: dist/backend-${ARCHIVE_NAME}.tar.gz, dist/frontend-${ARCHIVE_NAME}.tar.gz"
}

# --- Obsługa argumentów ----------------------------------------------------

case "${1:-}" in
    -h|--help)
        cat <<EOF
Skrypt budujący cały projekt PBS (backend + frontend) do katalogu dist/.

Użycie:
  ./scripts/build-all.sh             Buduje backend + frontend do dist/
  ./scripts/build-all.sh --tar-only  Dodatkowo tworzy archiwa .tar.gz
  ./scripts/build-all.sh --no-env    Nie kopiuj .env.production do pakietu
  ./scripts/build-all.sh --help      Wyświetla tę pomoc

Wynik:
  dist/backend/   - backend PHP (document root: public/)
  dist/frontend/  - aplikacja Angular (SPA)

Uwagi:
  - Backend: composer install --no-dev (bez zależności deweloperskich).
  - Frontend: ng build (konfiguracja produkcyjna).
  - Domyślnie .env.production jest kopiowany jako .env do dist/backend/
    (zawiera sekrety — nie udostępniaj dist/). Użyj --no-env, aby pominąć.
EOF
        ;;
    --no-env)
        DO_ENV=0
        build_backend
        build_frontend
        ;;
    --tar-only)
        DO_TAR=1
        build_backend
        build_frontend
        make_archives
        ;;
    *)
        build_backend
        build_frontend
        ;;
esac

info "=============================="
info "Build zakończony. Gotowe do uploadu w: ${DIST_DIR}"
