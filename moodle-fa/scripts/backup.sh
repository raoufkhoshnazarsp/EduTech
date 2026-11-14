#!/usr/bin/env bash
# پشتیبان‌گیری از دیتابیس و فایل‌های Moodle
# نصب خودکار در /usr/local/bin/moodle-backup توسط install.sh
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/moodle}"
KEEP_DAYS="${KEEP_DAYS:-14}"
STAMP="$(date +%Y%m%d-%H%M%S)"

mkdir -p "$BACKUP_DIR"
cd "$APP_DIR"

# دیتابیس
docker compose exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' \
  | gzip > "$BACKUP_DIR/db-$STAMP.sql.gz"

# فایل‌های درسی و آپلودها
docker compose exec -T moodle tar czf - -C /var/www/moodledata . \
  | gzip > "$BACKUP_DIR/moodledata-$STAMP.tar.gz"

# حذف پشتیبان‌های قدیمی
find "$BACKUP_DIR" -name '*.gz' -mtime +"$KEEP_DAYS" -delete

echo "$(date '+%F %T') backup ok: $STAMP"
