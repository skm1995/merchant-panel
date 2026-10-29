#!/usr/bin/env bash
# Dumps the production database into ~/backups, keeps a week of daily dumps there, and uploads
# each dump to Oracle Object Storage, so the backups survive losing the server; cron runs it
# (see provision.sh). BACKUP_UPLOAD_URL in ~/app/.env is a pre-authenticated request that may
# only write objects into the backup bucket, whose lifecycle rule deletes them after 30 days.
# Restore a dump with:
#   gunzip -c ~/backups/<file>.sql.gz | sudo docker compose -f ~/app/compose.prod.yaml exec -T mysql \
#     sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'

set -euo pipefail
umask 077

backups="${HOME}/backups"
keep_days=7
upload_attempts=3
file="${backups}/app-$(date -u +%Y-%m-%dT%H%M%SZ).sql.gz"

mkdir -p "${backups}"
cd "${HOME}/app"

sudo docker compose -f compose.prod.yaml exec -T mysql \
    sh -c 'exec mysqldump --single-transaction --routines --triggers -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
    | gzip > "${file}.partial"
mv "${file}.partial" "${file}"
find "${backups}" -name 'app-*.sql.gz' -mtime +"${keep_days}" -delete

# The local dump exists either way; a missing upload URL fails the job after it.
upload_url=$(sed -n 's/^BACKUP_UPLOAD_URL=//p' .env)
if [ -z "${upload_url}" ]; then
    echo "BACKUP_UPLOAD_URL is missing from ${HOME}/app/.env." >&2
    exit 1
fi

# The URL carries its access token, so it goes to curl on standard input, not in the arguments.
curl --fail --silent --show-error --retry "${upload_attempts}" --upload-file "${file}" \
    --config - <<< "url = \"${upload_url}$(basename "${file}")\""

echo "Backed up the database to ${file} and uploaded it to Object Storage."
