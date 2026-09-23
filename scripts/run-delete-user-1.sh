#!/bin/bash
set -euo pipefail
cd "$(dirname "$0")/../"
set -a
source .env
set +a
mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" < scripts/delete-user-1.sql
echo "Done."
