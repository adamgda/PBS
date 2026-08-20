#!/usr/bin/env bash
#
# Skrypt wgrywający zbudowany backend PBS API na FTP (przez curl).
#
# Użycie:
#   FTP_HOST=ftp.example.com FTP_USER=user FTP_PASS=pass \
#       ./scripts/deploy-backend.sh [ścieżka_do_pakietu]
#
# Domyślnie wgrywa dist/backend/ (z build-backend.sh). Można podać archiwum
# .tar.gz zamiast katalogu — wtedy plik zostanie wgrany bez rozpakowania.
#
# Konfiguracja (zmienne środowiskowe):
#   FTP_HOST        host FTP (wymagane)
#   FTP_USER        login FTP (wymagane)
#   FTP_PASS        hasło FTP (wymagane)
#   FTP_REMOTE_PATH katalog docelowy na serwerze (domyślnie: /)
#   FTP_USE_TLS     '1' = FTPS (explicit, port 21), domyślnie czysty FTP
#
set -euo pipefail

# --- Kolory ----------------------------------------------------------------

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

info()  { printf "${GREEN}[INFO]${NC}  %s\n" "$1"; }
warn()  { printf "${YELLOW}[WARN]${NC}  %s\n" "$1"; }
error() { printf "${RED}[BŁĄD]${NC} %s\n" "$1"; }

# --- Konfiguracja -----------------------------------------------------------

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE="${1:-${SCRIPT_DIR}/../dist/backend}"
REMOTE_PATH="${FTP_REMOTE_PATH:-/}"
REMOTE_PATH="${REMOTE_PATH%/}"   # usuń końcowy slash

# --- Walidacja --------------------------------------------------------------

[[ -z "${FTP_HOST:-}" ]] && { error "Brak FTP_HOST (np. FTP_HOST=ftp.example.com)."; exit 1; }
[[ -z "${FTP_USER:-}" ]] && { error "Brak FTP_USER."; exit 1; }
[[ -z "${FTP_PASS:-}" ]] && { error "Brak FTP_PASS."; exit 1; }

command -v curl >/dev/null 2>&1 || { error "curl nie jest zainstalowany."; exit 1; }

if [[ -f "${SOURCE}" && "${SOURCE}" == *.tar.gz ]]; then
    IS_ARCHIVE=1
elif [[ -d "${SOURCE}" ]]; then
    IS_ARCHIVE=0
else
    error "Nie znaleziono pakietu: ${SOURCE}. Uruchom najpierw ./scripts/build-backend.sh"
    exit 1
fi

# --- Helper curl ------------------------------------------------------------

curl_flags=(-s --show-error --fail --ftp-create-dirs)
if [[ "${FTP_USE_TLS:-0}" == "1" ]]; then
    curl_flags+=(--ssl-reqd)
fi
URL_BASE="ftp://${FTP_HOST}${REMOTE_PATH}"

put_file() {
    local local_path="$1"
    local remote_path="$2"
    curl "${curl_flags[@]}" -u "${FTP_USER}:${FTP_PASS}" -T "${local_path}" "ftp://${FTP_HOST}${remote_path}"
}

# --- Deploy ---------------------------------------------------------------

info "Łączenie z FTP: ${FTP_HOST}${REMOTE_PATH}"

if [[ "${IS_ARCHIVE}" -eq 1 ]]; then
    info "Wgrywanie archiwum: ${SOURCE}"
    put_file "${SOURCE}" "${REMOTE_PATH}/$(basename "${SOURCE}")"
    info "OK. Rozpakuj archiwum w katalogu docelowym na serwerze."
else
    info "Wgrywanie katalogu: ${SOURCE} → ${REMOTE_PATH}"
    count=0
    while IFS= read -r -d '' f; do
        rel="${f#"${SOURCE}"/}"
        put_file "${f}" "${REMOTE_PATH}/${rel}"
        count=$((count + 1))
    done < <(find "${SOURCE}" -type f -print0)
    info "Wgranych plików: ${count}"
fi

info "Deploy zakończony pomyślnie."
info "Pamiętaj: utwórz .env na serwerze (np. na podstawie .env.production)."
