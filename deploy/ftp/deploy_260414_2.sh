#!/usr/bin/env bash
# =============================================================================
# Деплой 14.04.2026 (#2) — SuperPart.ru:
#   откат расширения «Список сотрудников» для роли менеджер (снова только партнёр + повышенные роли);
#   сидер образцовых учётных записей по ролям (RoleSampleUsersSeeder).
#
# Файлы:
#   app/Providers/AppServiceProvider.php — Gate access-management-users;
#   ManagementUserController, management/users/index.blade.php;
#   database/seeders/RoleSampleUsersSeeder.php, DatabaseSeeder.php;
#   docs/ИСТОРИЯ_РАЗРАБОТКИ.md
#
# Запуск из корня репозитория:
#   FTP_PASS='…' bash ./deploy/ftp/deploy_260414_2.sh
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
  # Права и управление пользователями
  "app/Providers/AppServiceProvider.php"
  "app/Http/Controllers/Management/ManagementUserController.php"
  "resources/views/management/users/index.blade.php"

  # Сидеры
  "database/seeders/RoleSampleUsersSeeder.php"
  "database/seeders/DatabaseSeeder.php"

  "docs/ИСТОРИЯ_РАЗРАБОТКИ.md"
  "deploy/ftp/deploy_260414_2.sh"
)

echo "============================================================================="
echo "${SCRIPT_BASENAME} — SuperPart.ru (доступ к списку сотрудников + сидер roles-sample)"
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

php83 artisan view:clear
php83 artisan cache:clear

# Опционально — создать образцовые учётки (roles-sample-*@superpart.ru), только если нужно на этом контуре:
# php83 artisan db:seed --class=RoleSampleUsersSeeder --force

# Миграции для этого деплоя не требуются.

EOF
