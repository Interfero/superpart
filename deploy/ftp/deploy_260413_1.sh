#!/usr/bin/env bash
# =============================================================================
# Деплой 13.04.2026 (#1) — SuperPart.ru: форма «Новая заявка» как в CRM (persons/create)
#
# Файлы:
#   - Миграция orders/cities (timezone, client_age, статусы CRM), модели, OrderController,
#     LevelionApiService (телефон, timezone городов), маршруты, отчёты/экспорт/дашборд
#   - Blade: create, layout, input-phone-ru, status-badge, partner-order-detail
#   - resources/css/app.css (стили формы заявки, классы как в CRM)
#   - public/js/phone-mask.js
#
# FTP (reg.ru): u3398705_cursor_sp — домашний каталог = корень Laravel SuperPart.
#
# Запуск из корня репозитория superpart.ru:
#   FTP_PASS='…' bash ./deploy/ftp/deploy_260413_1.sh
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
  # Миграция
  "database/migrations/2026_04_13_120000_order_form_crm_alignment.php"
  # Поддержка и правила
  "app/Support/PersonClientValidation.php"
  "app/Support/OrderEquipment.php"
  # Модели
  "app/Models/City.php"
  "app/Models/Order.php"
  # Сервисы и контроллеры
  "app/Services/LevelionApiService.php"
  "app/Http/Controllers/OrderController.php"
  "app/Http/Controllers/DashboardController.php"
  "app/Http/Controllers/ReportController.php"
  "app/Exports/OrdersExport.php"
  "routes/web.php"
  # Стили (после выгрузки на сервере выполнить npm run build — см. блок SSH)
  "resources/css/app.css"
  # Представления
  "resources/views/layouts/app.blade.php"
  "resources/views/orders/create.blade.php"
  "resources/views/components/input-phone-ru.blade.php"
  "resources/views/components/status-badge.blade.php"
  "resources/views/orders/partner-order-detail.blade.php"
  # Статика
  "public/js/phone-mask.js"
  # Этот скрипт (для истории выгрузок на сервере)
  "deploy/ftp/deploy_260413_1.sh"
)

echo "============================================================================="
echo "${SCRIPT_BASENAME} — SuperPart.ru (форма заявки как в CRM)"
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

=== SSH: корень Laravel SuperPart (каталог приложения на reg.ru) ===

php83 artisan migrate --force
php83 artisan config:clear
php83 artisan cache:clear
php83 artisan view:clear

Сборка фронта (если менялись resources/css или resources/js): на своей машине npm run build,
затем отдельно залить каталог public/build/ (или выполнить npm run build на сервере, если установлены Node/npm).

EOF
