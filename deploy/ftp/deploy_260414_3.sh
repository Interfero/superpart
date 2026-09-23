#!/usr/bin/env bash
# =============================================================================
# Деплой 14.04.2026 (#3) — SuperPart.ru: недостающие миграции на прод (не попали в прошлые выгрузки).
#
#   2026_04_13_140000_user_roles_and_acl — users.role, parent_user_id, user_allowed_cities, user_allowed_sources;
#   2026_04_13_150000_normalize_order_status_enum — нормализация статусов заявок + ENUM в MySQL.
#
# После заливки обязательно: php83 artisan migrate --force
# (сидер RoleSampleUsersSeeder — только после успешного migrate, если нужны образцовые учётки).
#
# Запуск из корня репозитория:
#   FTP_PASS='…' bash ./deploy/ftp/deploy_260414_3.sh
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
  "database/migrations/2026_04_13_140000_user_roles_and_acl.php"
  "database/migrations/2026_04_13_150000_normalize_order_status_enum.php"
  "deploy/ftp/deploy_260414_3.sh"
)

echo "============================================================================="
echo "${SCRIPT_BASENAME} — SuperPart.ru (миграции 140000 + 150000)"
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

# Опционально — после migrate, если нужны учётки roles-sample-*:
# php83 artisan db:seed --class=RoleSampleUsersSeeder --force

EOF
