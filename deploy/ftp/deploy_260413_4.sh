#!/usr/bin/env bash
# =============================================================================
# Деплой 13.04.2026 (#4) — SuperPart.ru: визуальная сетка формы «Новая заявка»
#
# Причина: кастомные классы из resources/css/app.css не попадали в старый public/build
# без пересборки; вёрстка переведена на утилиты Tailwind в Blade + npm run build.
#
# Файлы:
#   - resources/views/orders/create.blade.php — grid/grid-cols, скрытие spinners у number
#   - public/build/manifest.json, public/build/assets/app-*.css, app-*.js — актуальный бандл Vite
#
# FTP (reg.ru): u3398705_cursor_sp — домашний каталог = корень Laravel SuperPart.
#
# Запуск из корня репозитория superpart.ru:
#   FTP_PASS='…' bash ./deploy/ftp/deploy_260413_4.sh
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
  "resources/views/orders/create.blade.php"
  "public/build/manifest.json"
  "public/build/assets/app-oz3q14pL.css"
  "public/build/assets/app-C2JHiaRZ.js"
  "deploy/ftp/deploy_260413_4.sh"
)

echo "============================================================================="
echo "${SCRIPT_BASENAME} — SuperPart.ru (форма заявки: Tailwind + build)"
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

=== SSH на сервере (корень Laravel SuperPart) ===

php83 artisan view:clear
php83 artisan cache:clear

Старые файлы в public/build/assets/ (другие хеши app-*.css / app-*.js) при желании удалите вручную — браузер берёт имена из manifest.json.

EOF
