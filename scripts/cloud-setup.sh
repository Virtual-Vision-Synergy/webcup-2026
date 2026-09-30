#!/usr/bin/env bash
# Prépare le projet dans une session cloud (Claude Code) : dépendances, .env, base SQLite, build.
# Usage : bash scripts/cloud-setup.sh   (idempotent, peut être relancé)
set -euo pipefail
cd "$(dirname "$0")/.."

export COMPOSER_ALLOW_SUPERUSER=1

composer install --no-interaction --prefer-dist --no-progress
[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force
mkdir -p database && touch database/database.sqlite
php artisan migrate --force
npm ci --no-audit --no-fund
npm run build

echo "OK : projet prêt. Lance vendor/bin/pint puis php artisan test avant de pousser."
