#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:?Укажите каталог приложения}"
BACKUP_ROOT="${2:?Укажите защищенный каталог резервных копий}"
PHP_BIN="${PHP_BIN:-/opt/php/8.3/bin/php}"
STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP_DIR="${BACKUP_ROOT}/${STAMP}_statistics"
TEMP_DIR="$(mktemp -d)"

cleanup() {
    rm -f "${TEMP_DIR}/mysql.cnf" "${TEMP_DIR}/database_name"
    rmdir "${TEMP_DIR}" 2>/dev/null || true
}
trap cleanup EXIT

mkdir -p "${BACKUP_DIR}"
chmod 700 "${BACKUP_ROOT}" "${BACKUP_DIR}"

"${PHP_BIN}" -r '
    chdir($argv[1]);
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $connection = config("database.default");
    $db = config("database.connections.".$connection);
    $escape = static fn ($value) => addcslashes((string) $value, "\\\"");
    $lines = [
        "[client]",
        "host=\"".$escape($db["host"] ?? "127.0.0.1")."\"",
        "port=\"".$escape($db["port"] ?? "3306")."\"",
        "user=\"".$escape($db["username"] ?? "")."\"",
        "password=\"".$escape($db["password"] ?? "")."\"",
    ];
    file_put_contents($argv[2], implode(PHP_EOL, $lines).PHP_EOL);
    file_put_contents($argv[3], (string) ($db["database"] ?? ""));
' "${APP_ROOT}" "${TEMP_DIR}/mysql.cnf" "${TEMP_DIR}/database_name"

chmod 600 "${TEMP_DIR}/mysql.cnf" "${TEMP_DIR}/database_name"
DATABASE_NAME="$(<"${TEMP_DIR}/database_name")"

mysqldump \
    --defaults-extra-file="${TEMP_DIR}/mysql.cnf" \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    --no-tablespaces \
    "${DATABASE_NAME}" | gzip -9 > "${BACKUP_DIR}/database.sql.gz"

tar -C "${APP_ROOT}" -czf "${BACKUP_DIR}/application.tar.gz" \
    --exclude='.env' \
    --exclude='.git' \
    --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='storage/app' \
    --exclude='storage/logs' \
    --exclude='storage/framework' \
    --exclude='public/uploads' \
    --exclude='private_backups' \
    app bootstrap config database public resources routes composer.json composer.lock artisan

sha256sum "${BACKUP_DIR}/database.sql.gz" "${BACKUP_DIR}/application.tar.gz" > "${BACKUP_DIR}/SHA256SUMS"
chmod 600 "${BACKUP_DIR}"/*

echo "Backup created: ${BACKUP_DIR}"
