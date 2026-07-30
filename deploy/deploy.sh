#!/usr/bin/env bash
# SpendLog — server-side deploy. Run on the VPS from anywhere:
#   bash /var/www/spendlog/deploy/deploy.sh
# Pulls main, installs deps, rebuilds assets, migrates, refreshes caches.
set -euo pipefail

APP_DIR=/var/www/spendlog

cd "$APP_DIR"

php artisan down --retry=15 || true

git pull origin main

composer install --no-dev --optimize-autoloader --no-interaction

npm ci
npm run build

# --force: production refuses interactive confirmation otherwise.
php artisan migrate --force

# Rebuild the config/route/view caches against the new code.
php artisan optimize

php artisan up

echo "Deployed $(git rev-parse --short HEAD)"
