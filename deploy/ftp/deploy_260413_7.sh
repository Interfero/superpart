#!/usr/bin/env bash
# =============================================================================
# Деплой 13.04.2026 (#7) — SuperPart.ru: интеграция источников CRM (SUPERPART_SOURCES)
#
# reference_sources, orders.reference_source_id, user_allowed_reference_sources;
# LevelionApiService (GET/PATCH reference/sources, source_id в partner-orders);
# форма заявки, фильтры, отчёты, management reference-sources, ACL менеджеров.
#
# Запуск из корня репозитория:
#   FTP_PASS='…' bash ./deploy/ftp/deploy_260413_7.sh
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
  "app/Exports/OrdersExport.php"
  "app/Http/Controllers/DashboardController.php"
  "app/Http/Controllers/Management/ManagementReferenceSourceController.php"
  "app/Http/Controllers/Management/ManagementUserController.php"
  "app/Http/Controllers/OrderController.php"
  "app/Http/Controllers/ReportController.php"
  "app/Http/Controllers/SourceController.php"
  "app/Models/Order.php"
  "app/Models/ReferenceSource.php"
  "app/Models/User.php"
  "app/Providers/AppServiceProvider.php"
  "app/Services/LevelionApiService.php"
  "app/Support/OrderSourceFilter.php"
  "database/migrations/2026_04_13_220000_create_reference_sources_table.php"
  "database/migrations/2026_04_13_220001_add_reference_source_id_to_orders_and_user_allowed_reference_sources.php"
  "docs/ИСТОРИЯ_РАЗРАБОТКИ.md"
  "resources/views/dashboard/index.blade.php"
  "resources/views/management/reference-sources/index.blade.php"
  "resources/views/management/users/edit.blade.php"
  "resources/views/management/users/index.blade.php"
  "resources/views/orders/create.blade.php"
  "resources/views/orders/index.blade.php"
  "resources/views/orders/partner-order-detail.blade.php"
  "resources/views/orders/unprocessed.blade.php"
  "resources/views/partials/navbar.blade.php"
  "resources/views/reports/orders.blade.php"
  "resources/views/sources/index.blade.php"
  "routes/web.php"
  "deploy/ftp/deploy_260413_7.sh"
)

echo "============================================================================="
echo "${SCRIPT_BASENAME} — SuperPart.ru (источники CRM: reference/sources, PATCH, source_id)"
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

=== SSH на сервере (корень Laravel, например www/SuperPart.ru) ===

php83 artisan migrate --force
php83 artisan view:clear
php83 artisan cache:clear

# В CRM: для источников включён флаг «Показывать в каталоге SuperPart»; ключи LEVELION_* совпадают с SUPERPART_* в CRM.

EOF
