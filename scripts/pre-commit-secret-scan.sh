#!/usr/bin/env bash
# Pre-commit hook — skanowanie sekretów (dokumentacja 9.6).
# Instalacja:
#   ln -s ../../scripts/pre-commit-secret-scan.sh .git/hooks/pre-commit
#   (lub skopiuj plik do .git/hooks/pre-commit i nadaj chmod +x)
#
# Wymaga: gitleaks (https://github.com/gitleaks/gitleaks) lub fallback na grep.

set -euo pipefail

ROOT_DIR="$(git rev-parse --show-toplevel)"

if command -v gitleaks >/dev/null 2>&1; then
    echo "🔒 [pre-commit] Skanowanie sekretów (gitleaks)..."
    gitleaks detect --source "$ROOT_DIR" -c "$ROOT_DIR/.gitleaks.toml" --redact --verbose
    echo "✅ [pre-commit] Brak sekretów."
else
    echo "⚠️  [pre-commit] gitleaks nie zainstalowany — używam prostego skanu grep."
    # Prosty fallback: ostrzeżenie o plikach .env commitowanych
    STAGED_ENV=$(git diff --cached --name-only | grep -E '(^|/)\.env($|\.)' || true)
    if [ -n "$STAGED_ENV" ]; then
        echo "❌ [pre-commit] Znaleziono pliki .env w stage. Nie commituj sekretów!"
        echo "$STAGED_ENV"
        exit 1
    fi
    echo "✅ [pre-commit] Brak plików .env w stage."
fi
