#!/usr/bin/env bash
# =============================================================================
# Деплой 11.04.2026 (#10) — SuperPart.ru: sync_last_error + детали ошибки CRM в карточке
#
# Файлы:
#   - database/migrations/2026_04_11_130000_add_sync_last_error_to_orders_table.php
#   - app/Models/Order.php
#   - app/Services/LevelionApiService.php — pushPartnerOrder возвращает массив с текстом ошибки
#   - app/Http/Controllers/OrderController.php
#   - resources/views/orders/partner-order-detail.blade.php — блок «Причина»
#
# FTP (reg.ru): u3398705_cursor_sp — домашний каталог = корень Laravel SuperPart.
#
# Запуск из корня репозитория superpart.ru:
#   FTP_PASS='…' bash ./deploy/ftp/deploy_260411_10.sh
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

FTP_HOST="31.31.197.5"
FTP_PORT=21
FTP_USER="u3398705_cursor_sp"
REMOTE_ROOT="${REMOTE_ROOT:-}"

if [ -z "${FTP_PASS:-}" ]; then
  echo "ERROR: не задан FTP_PASS."
  exit 1
fi

FILES=(
  "database/migrations/2026_04_11_130000_add_sync_last_error_to_orders_table.php"
  "app/Models/Order.php"
  "app/Services/LevelionApiService.php"
  "app/Http/Controllers/OrderController.php"
  "resources/views/orders/partner-order-detail.blade.php"
  "deploy/ftp/deploy_260411_10.sh"
)

echo "============================================================================="
echo "${SCRIPT_BASENAME} — SuperPart.ru (sync_last_error + CRM)"
echo "Корень проекта: ${ROOT}"
echo "============================================================================="

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
echo "Done! Uploaded: ${uploaded}, Errors: ${errors}"

cat <<'EOF'

=== SSH: корень Laravel SuperPart ===

php83 artisan migrate --force
php83 artisan optimize:clear
php83 artisan view:clear

Старые заявки с ошибкой до этого деплоя не содержат текст причины — создайте тестовую заявку снова или смотрите storage/logs/laravel.log на сервере (LevelionApiService: partner-orders).

EOF
