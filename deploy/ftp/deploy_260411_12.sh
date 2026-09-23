#!/usr/bin/env bash
# =============================================================================
# Деплой 11.04.2026 (#12) — SuperPart.ru: LEVELION_API_PATH_PREFIX + crmApiUrl()
#
# Файлы: config/services.php, app/Services/LevelionApiService.php, .env.example,
#        deploy/regru/.env.example
#
# FTP: u3398705_cursor_sp
#   FTP_PASS='…' bash ./deploy/ftp/deploy_260411_12.sh
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
  "config/services.php"
  "app/Services/LevelionApiService.php"
  ".env.example"
  "deploy/regru/.env.example"
  "deploy/ftp/deploy_260411_12.sh"
)

echo "============================================================================="
echo "${SCRIPT_BASENAME} — Levelion API path prefix"
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

=== SSH (SuperPart) ===

# При необходимости в .env на сервере:
# LEVELION_API_PATH_PREFIX=api/v1
# Если BASE_URL уже был с /api — уберите /api из LEVELION_BASE_URL.

php83 artisan config:clear
php83 artisan cache:clear

=== На сервере CRM (Lead Control) ===

Должен существовать POST /api/v1/partner-orders (routes/api.php, PartnerOrderController).

EOF
