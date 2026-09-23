#!/usr/bin/env bash
# =============================================================================
# Деплой 11.04.2026 (#9) — SuperPart.ru: форма «Новая заявка» + кнопка submit + flash status
#
# Файлы:
#   - app/Http/Controllers/OrderController.php — withErrors для города без CRM
#   - resources/views/components/ui/button.blade.php — type="submit" из атрибутов
#   - resources/views/layouts/app.blade.php — алерт session('status')
#   - resources/views/orders/create.blade.php — ошибки, «Сохранение…», id формы
#
# FTP (reg.ru): u3398705_cursor_sp — домашний каталог = корень Laravel SuperPart.
#
# Запуск из корня репозитория superpart.ru:
#   FTP_PASS='…' bash ./deploy/ftp/deploy_260411_9.sh
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
  "app/Http/Controllers/OrderController.php"
  "resources/views/components/ui/button.blade.php"
  "resources/views/layouts/app.blade.php"
  "resources/views/orders/create.blade.php"
  "deploy/ftp/deploy_260411_9.sh"
)

echo "============================================================================="
echo "${SCRIPT_BASENAME} — SuperPart.ru (форма заявки, кнопка, layout)"
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

=== SSH: корень Laravel SuperPart (каталог с artisan) ===

php83 artisan optimize:clear
php83 artisan view:clear

EOF
