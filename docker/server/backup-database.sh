#!/usr/bin/env bash
# Dumps the production database into ~/backups and keeps a week of daily dumps; cron runs it
# (see provision.sh). Restore a dump with:
#   gunzip -c ~/backups/<file>.sql.gz | sudo docker compose -f ~/app/compose.prod.yaml exec -T mysql \
#     sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'

set -euo pipefail
umask 077

backups="${HOME}/backups"
keep_days=7
file="${backups}/app-$(date -u +%Y-%m-%dT%H%M%SZ).sql.gz"

mkdir -p "${backups}"
cd "${HOME}/app"

sudo docker compose -f compose.prod.yaml exec -T mysql \
    sh -c 'exec mysqldump --single-transaction --routines --triggers -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
    | gzip > "${file}.partial"
mv "${file}.partial" "${file}"
find "${backups}" -name 'app-*.sql.gz' -mtime +"${keep_days}" -delete

echo "Backed up the database to ${file}."
