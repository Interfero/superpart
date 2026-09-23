#!/usr/bin/env bash
# =============================================================================
# Деплой 13.04.2026 (#6) — SuperPart.ru: заявка + пользователи портала + источники + города ACL
#
# Блок 1 — форма заявки, management/users (@sp.ru, мультиселект, сброс пароля),
#   миграция users.comment, навбар, редирект /employees.
# Блок 2 — источники: карточка=форма, строка таблицы, чекбокс «Отзыв», soft delete,
#   правила exists для sources, Order::source withTrashed, компонент ui/checkbox (проп checked).
# Блок 3 — города партнёра/менеджера (user_allowed_cities), фильтры и формы, gate /cities без менеджера,
#   PortalCityOptions, страница городов без столбца «Загруженность», сидеры партнёров, история разработки.
#
# Запуск из корня репозитория:
#   FTP_PASS='…' bash ./deploy/ftp/deploy_260413_6.sh
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
  "app/Http/Controllers/CityController.php"
  "app/Http/Controllers/Controller.php"
  "app/Http/Controllers/DashboardController.php"
  "app/Http/Controllers/Management/ManagementUserController.php"
  "app/Http/Controllers/OrderController.php"
  "app/Http/Controllers/ReportController.php"
  "app/Http/Controllers/ReviewController.php"
  "app/Http/Controllers/SourceController.php"
  "app/Models/Order.php"
  "app/Models/Source.php"
  "app/Models/User.php"
  "app/Providers/AppServiceProvider.php"
  "app/Support/PortalCityOptions.php"
  "app/Support/PortalUserProvisioning.php"
  "app/Support/UserSessionRevoker.php"
  "database/migrations/2026_04_13_200000_add_comment_to_users_table.php"
  "database/migrations/2026_04_13_210000_add_soft_deletes_to_sources_table.php"
  "database/seeders/IntegrationTestPartnerSeeder.php"
  "database/seeders/UserSeeder.php"
  "docs/ИСТОРИЯ_РАЗРАБОТКИ.md"
  "resources/views/cities/index.blade.php"
  "resources/views/components/multiselect-checkboxes.blade.php"
  "resources/views/components/ui/checkbox.blade.php"
  "resources/views/management/users/create.blade.php"
  "resources/views/management/users/edit.blade.php"
  "resources/views/management/users/index.blade.php"
  "resources/views/orders/create.blade.php"
  "resources/views/partials/navbar.blade.php"
  "resources/views/sources/create.blade.php"
  "resources/views/sources/index.blade.php"
  "resources/views/sources/show.blade.php"
  "routes/web.php"
  "deploy/ftp/deploy_260413_6.sh"
)

echo "============================================================================="
echo "${SCRIPT_BASENAME} — SuperPart.ru (заявка + пользователи + источники + города ACL)"
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

# Партнёрам без городов в user_allowed_cities — назначить города: Управление → Пользователи.
# Сидеры на прод обычно не запускают.

# Удалить устаревший шаблон (если заливали старую версию источников):
# rm -f resources/views/sources/edit.blade.php

# Устаревшие файлы (если ещё лежат на сервере после перехода на management/users):
# rm -f app/Http/Controllers/EmployeeController.php
# rm -f resources/views/employees/index.blade.php resources/views/employees/create.blade.php

EOF
