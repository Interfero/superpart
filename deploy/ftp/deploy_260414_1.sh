#!/usr/bin/env bash
# =============================================================================
# Деплой 14.04.2026 (#1) — SuperPart.ru: навбар/баланс + фильтры таблиц без лупы, сборка Vite;
#   чекбокс «Без звонка» при создании заявки (миграция orders.without_call, CRM payload);
#   раздел «Заявки»: убран пункт «Не оформленные» в меню, дубль «Создать» на списке, блок действий на карточке;
#   начисления: узкий фильтр ID, клик по строке → карточка заявки; заявка на вывод д/с: белые поля в просмотре,
#   кнопки «Добавить комментарий» / «Претензия» / «Негативный отзыв» (вёрстка).
# 6) Отчёты: строка «Итого» — светлый фон + верхняя граница (cities, work-types, statistics); актуальный public/build.
#
# 1) Тема только в настройках; баланс без дублей (layout, начисления, TransactionController).
# 2) Фильтры: без иконки поиска, debounce отправки формы; «Вид» first_time → «Впервые» на главной;
#    правки Blade + app.js + public/build (обязательно вместе с manifest.json).
# 3) Заявка: поле without_call, форма create, карточка заявки, LevelionApiService::pushPartnerOrder.
# 4) Заявки (UI): navbar (без «Не оформленные»), orders/index (без кнопки «Создать» у вкладок),
#    partner-order-detail (неактивные кнопки действий).
# 5) Начисления / вывод д/с: filter-input (narrow), transactions/index, withdrawals/index, withdrawals/form.
# 7) Справочники — список сотрудников (перенос из «Управление»): ФИО, email, пароль 10, last_login_at, destroy;
#    города — «Доступ партнера», фильтр available=all|1|0; WorkTypeSeeder (тексты описаний).
#    Файлы: User, PersonName, PortalUserProvisioning, Auth, CityController, ManagementUserController, routes,
#    management/users/*, миграция users; navbar и cities/index уже в списке выше — повторная заливка актуальных версий.
#
# 8) Страница «Настройки»: API (логин/пароль/код партнёра), заглушка «Цена заявки», банковские карты, телефоны
#    (источники как в заявке; добавление телефона — только разработчик), правка переключателя темы.
#    Миграции: api_* / partner_code на users, partner_bank_cards, partner_phones; модели PartnerBankCard, PartnerPhone;
#    SettingsController, Form Requests Settings/*, resources/views/settings/index.blade.php; эталон Import/settings-api-reference.png.
#
# 9) Дополнение: адаптив — бургер и выезжающая шторка навигации (&lt; lg), блок быстрой статистики на главной в колонку;
#    resources/views/partials/navbar.blade.php, resources/js/app.js (initMobileNav), resources/views/dashboard/index.blade.php,
#    актуальный public/build (manifest.json + хеши app-*.js / app-*.css).
#
# Запуск из корня репозитория:
#   FTP_PASS='…' bash ./deploy/ftp/deploy_260414_1.sh
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
  # Навбар / баланс / начисления
  "app/Http/Controllers/TransactionController.php"
  "resources/views/layouts/app.blade.php"
  "resources/views/partials/navbar.blade.php"
  "resources/views/transactions/index.blade.php"

  # Фильтры таблиц (контроллер, JS, Vite-сборка)
  "app/Http/Controllers/DashboardController.php"
  "resources/js/app.js"
  "resources/views/components/ui/filter-input.blade.php"
  "resources/views/dashboard/index.blade.php"
  # orders/index — также: без кнопки «Создать» у вкладок (дубль навбара)
  "resources/views/orders/index.blade.php"
  "resources/views/orders/unprocessed.blade.php"
  "resources/views/reports/orders.blade.php"
  "resources/views/reports/cities.blade.php"
  "resources/views/reports/work-types.blade.php"
  "resources/views/reports/statistics.blade.php"
  "resources/views/withdrawals/index.blade.php"
  "resources/views/withdrawals/form.blade.php"
  "resources/views/cities/index.blade.php"
  "public/build/manifest.json"
  "public/build/assets/app-CdzL_WcI.js"
  "public/build/assets/app-B5GjBiLl.css"

  # «Без звонка» + карточка заявки (блок действий по заявке — пока вёрстка)
  "database/migrations/2026_04_14_120000_add_without_call_to_orders_table.php"
  "app/Models/Order.php"
  "app/Http/Controllers/OrderController.php"
  "app/Services/LevelionApiService.php"
  "resources/views/orders/create.blade.php"
  "resources/views/orders/partner-order-detail.blade.php"

  # Справочники: сотрудники + города (контроллер) + виды работ (сидер)
  "database/migrations/2026_04_14_120000_add_profile_and_last_login_to_users_table.php"
  "app/Models/User.php"
  "app/Support/PersonName.php"
  "app/Support/PortalUserProvisioning.php"
  "app/Http/Controllers/AuthController.php"
  "app/Http/Controllers/CityController.php"
  "app/Http/Controllers/Management/ManagementUserController.php"
  "routes/web.php"
  "resources/views/management/users/index.blade.php"
  "resources/views/management/users/create.blade.php"
  "resources/views/management/users/edit.blade.php"
  "database/seeders/WorkTypeSeeder.php"

  # Настройки (API, карты, телефоны, тема)
  "database/migrations/2026_04_14_210000_add_api_credentials_to_users_table.php"
  "database/migrations/2026_04_14_210001_create_partner_bank_cards_table.php"
  "database/migrations/2026_04_14_210002_create_partner_phones_table.php"
  "app/Models/PartnerBankCard.php"
  "app/Models/PartnerPhone.php"
  "app/Http/Controllers/SettingsController.php"
  "app/Http/Requests/Settings/UpdateApiCredentialsRequest.php"
  "app/Http/Requests/Settings/StoreBankCardRequest.php"
  "app/Http/Requests/Settings/UpdateBankCardRequest.php"
  "app/Http/Requests/Settings/StorePartnerPhoneRequest.php"
  "app/Http/Requests/Settings/UpdatePartnerPhonesRequest.php"
  "resources/views/settings/index.blade.php"
  "Import/settings-api-reference.png"

  "docs/ИСТОРИЯ_РАЗРАБОТКИ.md"
  "deploy/ftp/deploy_260414_1.sh"
)

echo "============================================================================="
echo "${SCRIPT_BASENAME} — SuperPart.ru (навбар/баланс + фильтры + build + без звонка + UI заявок + начисления/вывод д/с + отчёты Итого + справочники/сотрудники + настройки + адаптив навбар/главная)"
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
php83 artisan db:seed --class=WorkTypeSeeder --force
php83 artisan view:clear
php83 artisan cache:clear

# migrate — orders.without_call (если ещё не накатывали) и users (last_login_at, ФИО-поля);
#   плюс настройки: users (api_login, api_password, partner_code), partner_bank_cards, partner_phones.
# db:seed WorkTypeSeeder — актуальные тексты «Видов работ» (при необходимости).
# Остальное — Blade, JS, public/build.

EOF
