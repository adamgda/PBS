#!/usr/bin/env bash
#
# Skrypt budujący produkcyjny pakiet backendu PBS API (do wgrania na FTP).
#
# Użycie:
#   ./scripts/build-backend.sh             # buduje pakiet do dist/backend
#   ./scripts/build-backend.sh --tar-only  # dodatkowo tworzy archiwum .tar.gz
#   ./scripts/build-backend.sh --help      # pomoc
#
# Co robi:
#   1. Instaluje zależności produkcyjne (composer install --no-dev --optimize-autoloader)
#   2. Składa czysty katalog dist/backend (bez testów, deploy/, Dockerfile, storage/)
#   3. (opcjonalnie) tworzy dist/backend-<data>.tar.gz do jednoplikowego wgrania
#
set -euo pipefail

# --- Konfiguracja -----------------------------------------------------------

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="${SCRIPT_DIR}/../backend"
DIST_DIR="${SCRIPT_DIR}/../dist/backend"
ARCHIVE_DIR="${SCRIPT_DIR}/../dist"
ARCHIVE_NAME="backend-$(date +%Y%m%d-%H%M%S).tar.gz"

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

check_php() {
    if ! command -v php >/dev/null 2>&1; then
        error "PHP nie jest zainstalowane lub nie jest dostępne w PATH."
        exit 1
    fi
    info "PHP: $(php -r 'echo PHP_VERSION;')"
}

check_composer() {
    if ! command -v composer >/dev/null 2>&1; then
        error "Composer nie jest zainstalowany lub nie jest dostępne w PATH."
        exit 1
    fi
    info "Composer: $(composer --version 2>/dev/null | head -n1)"
}

# --- Budowanie --------------------------------------------------------------

build() {
    info "=== 1/3 Instalacja zależności produkcyjnych ==="
    (cd "${BACKEND_DIR}" && composer install --no-dev --optimize-autoloader --no-interaction)

    info "=== 2/3 Składanie czystego katalogu dist/backend ==="
    rm -rf "${DIST_DIR}"
    mkdir -p "${DIST_DIR}"

    # Katalogi / pliki wchodzące w skład pakietu
    local dirs=(src public bin migrations seeds vendor)
    local files=(composer.json composer.lock pest.php m)

    for d in "${dirs[@]}"; do
        if [[ -d "${BACKEND_DIR}/${d}" ]]; then
            cp -R "${BACKEND_DIR}/${d}" "${DIST_DIR}/"
        else
            warn "Brak katalogu ${d}/ — pomijam."
        fi
    done

    for f in "${files[@]}"; do
        if [[ -f "${BACKEND_DIR}/${f}" ]]; then
            cp "${BACKEND_DIR}/${f}" "${DIST_DIR}/"
        else
            warn "Brak pliku ${f} — pomijam."
        fi
    done

    # === .env: kopiujemy .env.production jako .env (produkcja) ===
    if [[ "${DO_ENV}" -eq 1 ]]; then
        if [[ -f "${BACKEND_DIR}/.env.production" ]]; then
            cp "${BACKEND_DIR}/.env.production" "${DIST_DIR}/.env"
            warn "Skopiowano .env.production → .env (UWAGA: zawiera sekrety — nie udostępniaj dist/)."
        else
            warn "Brak .env.production — .env nie został utworzony. Utwórz go na serwerze."
        fi
    else
        warn "Pominięto kopiowanie .env (--no-env). Utwórz .env na serwerze."
    fi

    info "=== 3/3 Gotowe ==="
    info "Pakiet: ${DIST_DIR}"
}

make_tar() {
    info "Tworzenie archiwum ${ARCHIVE_DIR}/${ARCHIVE_NAME} ..."
    (cd "${DIST_DIR}" && tar -czf "${ARCHIVE_DIR}/${ARCHIVE_NAME}" .)
    info "Archiwum: ${ARCHIVE_DIR}/${ARCHIVE_NAME}"
}

# --- Obsługa argumentów ----------------------------------------------------

case "${1:-}" in
    -h|--help)
        cat <<EOF
Skrypt budujący produkcyjny pakiet backendu PBS API.

Użycie:
  ./scripts/build-backend.sh             Buduje pakiet do dist/backend
  ./scripts/build-backend.sh --tar-only  Dodatkowo tworzy .tar.gz do wgrania
  ./scripts/build-backend.sh --no-env    Nie kopiuj .env.production do pakietu
  ./scripts/build-backend.sh --help      Wyświetla tę pomoc

Uwaga: domyślnie .env.production jest kopiowany jako .env do pakietu
(zawiera sekrety — nie udostępniaj dist/). Użyj --no-env, aby pominąć.
EOF
        ;;
    --no-env)
        DO_ENV=0
        check_php
        check_composer
        build
        ;;
    --tar-only)
        DO_TAR=1
        check_php
        check_composer
        build
        make_tar
        ;;
    *)
        check_php
        check_composer
        build
        ;;
esac
