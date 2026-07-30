#!/usr/bin/env bash
#
# Skrypt uruchamiający lokalny backend PBS API (PHP built-in server)
#
# Użycie:
#   ./start-backend.sh            # uruchamia serwer na http://localhost:8080
#   ./start-backend.sh --check     # weryfikuje konfigurację bez uruchamiania serwera
#
set -euo pipefail

# --- Konfiguracja -----------------------------------------------------------

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="${SCRIPT_DIR}/backend"
HOST="localhost"
PORT="8080"
ENV_FILE="${BACKEND_DIR}/.env"
ENV_EXAMPLE="${BACKEND_DIR}/.env.example"

# --- Kolory ----------------------------------------------------------------

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

info()  { printf "${GREEN}[INFO]${NC}  %s\n" "$1"; }
warn()  { printf "${YELLOW}[WARN]${NC}  %s\n" "$1"; }
error() { printf "${RED}[BŁĄD]${NC} %s\n" "$1"; }

# --- Sprawdzenie zależności -------------------------------------------------

check_php() {
    if ! command -v php >/dev/null 2>&1; then
        error "PHP nie jest zainstalowane lub nie jest dostępne w PATH."
        exit 1
    fi

    PHP_VERSION="$(php -r 'echo PHP_VERSION;')"
    PHP_MAJOR_MINOR="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')"
    REQUIRED_MIN="8.3"

    info "Znaleziono PHP: ${PHP_VERSION}"

    # Porównanie wersji (wymagane >= 8.3)
    if ! php -r "exit(version_compare('${PHP_MAJOR_MINOR}', '${REQUIRED_MIN}', '>=') ? 0 : 1);"; then
        error "Wymagane PHP >= ${REQUIRED_MIN}, znaleziono ${PHP_MAJOR_MINOR}."
        exit 1
    fi
}

check_composer() {
    if ! command -v composer >/dev/null 2>&1; then
        error "Composer nie jest zainstalowany lub nie jest dostępny w PATH."
        exit 1
    fi
    info "Znaleziono Composera: $(composer --version 2>/dev/null | head -n1)"
}

check_env() {
    if [[ ! -f "${ENV_FILE}" ]]; then
        warn "Nie znaleziono pliku .env (${ENV_FILE})."
        if [[ -f "${ENV_EXAMPLE}" ]]; then
            cp "${ENV_EXAMPLE}" "${ENV_FILE}"
            info "Utworzono .env na podstawie .env.example — dostosuj wartości bazy danych."
        else
            error "Brak pliku .env.example — nie można utworzyć .env automatycznie."
            exit 1
        fi
    else
        info "Plik .env istnieje."
    fi
}

install_dependencies() {
    if [[ ! -d "${BACKEND_DIR}/vendor" ]]; then
        info "Brak katalogu vendor/ — instalowanie zależności (composer install)..."
        (cd "${BACKEND_DIR}" && composer install --no-interaction)
    else
        info "Zależności Composera są już zainstalowane (vendor/)."
    fi
}

# --- Tryb weryfikacji (--check) --------------------------------------------

run_check() {
    info "=== Weryfikacja konfiguracji backendu ==="
    check_php
    check_composer
    check_env
    install_dependencies
    info "=== Konfiguracja prawidłowa — backend gotowy do uruchomienia ==="
}

# --- Główne uruchomienie ----------------------------------------------------

run_serve() {
    run_check

    info "=== Uruchamianie serwera lokalnego ==="
    printf "${GREEN}[INFO]${NC}  Adres: http://${HOST}:${PORT}\n"
    printf "${GREEN}[INFO]${NC}  Aby zatrzymać: Ctrl+C\n\n"

    cd "${BACKEND_DIR}"
    exec php -S "${HOST}:${PORT}" -t public
}

# --- Obsługa argumentów ----------------------------------------------------

case "${1:-}" in
    --check|-c)
        run_check
        ;;
    -h|--help)
        cat <<EOF
Skrypt uruchamiający lokalny backend PBS API.

Użycie:
  ./start-backend.sh             Uruchamia serwer na http://${HOST}:${PORT}
  ./start-backend.sh --check     Weryfikuje konfigurację bez uruchamiania serwera
  ./start-backend.sh --help       Wyświetla tę pomoc
EOF
        ;;
    *)
        run_serve
        ;;
esac