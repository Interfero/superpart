#!/usr/bin/env bash
# =============================================================================
# Деплой 11.04.2026 (#8) — SuperPart.ru: ОБЪЕДИНЁННАЯ выгрузка за день (11.04.2026)
#
# Включает всё из deploy_260411_1 … _7 (без устаревшего orders/show.blade.php — шаблон
# перенесён в partner-order-detail.blade.php).
#
# FTP (reg.ru): u3398705_cursor_sp — в панели задайте домашний каталог = корень Laravel
# приложения SuperPart (например www/SuperPart.ru), где лежат app/, artisan, resources/.
#
# Пароль в файл не писать. Запуск из корня репозитория superpart.ru:
#   FTP_PASS='…' bash ./deploy/ftp/deploy_260411_8.sh
#
# Перед FTP выполняется composer dump-autoload -o (нужны актуальные vendor/composer/*).
# =============================================================================
set -euo pipefail

SCRIPT_BASENAME="$(basename "$0")"
SCRIPT_DATE="${SCRIPT_BASENAME#deploy_}"
SCRIPT_DATE="${SCRIPT_DATE%.sh}"
SCRIPT_DATE="${SCRIPT_DATE%_*}"
TODAY="$(date +%y%m%d)"
if [ "${SCRIPT_DATE}" != "${TODAY}" ]; then
  echo "ERROR: дата в имени файла (${SCRIPT_DATE}) не совпадает с сегодняшней (${TODAY}). Переименуйте скрипт."
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
cd "${ROOT}"

echo "============================================================================="
echo "${SCRIPT_BASENAME} — SuperPart.ru (объединённый деплой 11.04.2026)"
echo "Корень проекта: ${ROOT}"
echo "============================================================================="
echo ""
echo ">>> composer dump-autoload -o"
if command -v composer >/dev/null 2>&1; then
  composer dump-autoload -o
elif [ -f "${ROOT}/composer.phar" ]; then
  php "${ROOT}/composer.phar" dump-autoload -o
else
  echo "ERROR: не найден composer в PATH и нет ./composer.phar"
  exit 1
fi
if ! grep -q 'IntegrationTestPartnerSeeder' "${ROOT}/vendor/composer/autoload_classmap.php" 2>/dev/null; then
  echo "WARN: в classmap нет IntegrationTestPartnerSeeder — проверьте сидер."
fi
echo ""

FTP_HOST="31.31.197.5"
FTP_PORT=21
FTP_USER="u3398705_cursor_sp"
REMOTE_ROOT="${REMOTE_ROOT:-}"

if [ -z "${FTP_PASS:-}" ]; then
  echo "ERROR: не задан FTP_PASS."
  exit 1
fi

FILES=(
  "database/migrations/2026_04_06_120000_levelion_integration.php"
  "bootstrap/app.php"
  "routes/api.php"
  "config/services.php"
  "app/Http/Controllers/Api/LevelionWebhookController.php"
  "app/Http/Middleware/VerifyLevelionWebhook.php"
  "app/Services/LevelionApiService.php"
  "app/Http/Controllers/OrderController.php"
  "app/Providers/AppServiceProvider.php"
  "database/seeders/IntegrationTestPartnerSeeder.php"
  "database/seeders/DatabaseSeeder.php"
  "vendor/composer/autoload_classmap.php"
  "vendor/composer/autoload_static.php"
  "resources/views/orders/partner-order-detail.blade.php"
  "resources/views/layouts/app.blade.php"
  "deploy/ftp/deploy_260411_8.sh"
)

echo ">>> FTP → ${FTP_HOST} (пользователь ${FTP_USER})"
if [ -n "${REMOTE_ROOT}" ]; then
  echo "REMOTE_ROOT=${REMOTE_ROOT}"
fi
echo ""

uploaded=0
errors=0
for file in "${FILES[@]}"; do
  [[ -z "${file// }" ]] && continue
  if [ -n "${REMOTE_ROOT}" ]; then
    remote_path="${REMOTE_ROOT%/}/${file}"
  else
    remote_path="/${file}"
  fi
  if [ ! -f "${file}" ]; then
    echo "  SKIP (not found): ${file}"
    ((errors++)) || true
    continue
  fi
  echo "  Загрузка: ${file}"
  if curl -sS --ftp-create-dirs \
    -T "${file}" \
    "ftp://${FTP_HOST}:${FTP_PORT}${remote_path}" \
    --user "${FTP_USER}:${FTP_PASS}"; then
    echo "  OK: ${file}"
    ((uploaded++)) || true
  else
    echo "  ERROR: ${file}"
    ((errors++)) || true
  fi
done

echo ""
echo "Done! Uploaded: ${uploaded}, Errors: ${errors}"

cat <<'EOF'

=== SSH: корень Laravel SuperPart (каталог с artisan), например ~/www/SuperPart.ru ===

grep -n partner-order-detail app/Http/Controllers/OrderController.php
grep -n superpart-layout resources/views/layouts/app.blade.php

php83 artisan migrate --force
php83 artisan optimize:clear
php83 artisan view:clear
php83 artisan db:seed --class=IntegrationTestPartnerSeeder --force

# при необходимости после смены .env:
# php83 artisan config:cache

Интеграция CRM (отдельный хост): PartnerOrderController на стороне Lead Control — см. репозиторий Levelion_dev.

EOF
