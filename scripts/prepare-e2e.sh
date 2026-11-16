#!/usr/bin/env bash
# Prepares a self-contained application for the Playwright tests: a dedicated
# .env.e2e (APP_ENV=e2e, SQLite), a fresh database with the seeded admin and
# the built assets. The local .env is left untouched.
set -euo pipefail
cd "$(dirname "$0")/.."

admin_email="${ADMIN_EMAIL:-admin@famapp.test}"
admin_password="${ADMIN_PASSWORD:-mot-de-passe-e2e}"
port="${E2E_PORT:-8000}"
database="$PWD/database/e2e.sqlite"

sed \
  -e "s|^APP_ENV=.*|APP_ENV=e2e|" \
  -e "s|^APP_DEBUG=.*|APP_DEBUG=false|" \
  -e "s|^APP_URL=.*|APP_URL=http://127.0.0.1:${port}|" \
  -e "s|^DB_CONNECTION=.*|DB_CONNECTION=sqlite|" \
  -e "s|^DB_DATABASE=.*|DB_DATABASE=${database}|" \
  -e "s|^CACHE_STORE=.*|CACHE_STORE=file|" \
  -e "s|^QUEUE_CONNECTION=.*|QUEUE_CONNECTION=sync|" \
  -e "s|^MAIL_MAILER=.*|MAIL_MAILER=log|" \
  -e "s|^ADMIN_EMAIL=.*|ADMIN_EMAIL=${admin_email}|" \
  -e "s|^ADMIN_PASSWORD=.*|ADMIN_PASSWORD=${admin_password}|" \
  .env.example > .env.e2e

rm -f "$database"
touch "$database"

export APP_ENV=e2e
php artisan key:generate --force
php artisan migrate:fresh --seed --force
npm run build
