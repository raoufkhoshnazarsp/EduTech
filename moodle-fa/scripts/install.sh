#!/usr/bin/env bash
# نصب کامل سامانه آموزش آنلاین (Moodle) روی سرور اوبونتو/دبیان
# اجرا:  sudo bash scripts/install.sh
set -euo pipefail

cd "$(dirname "$0")/.."

say() { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }
die() { printf '\n\033[1;31mخطا: %s\033[0m\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "این اسکریپت را با sudo اجرا کنید."

# ---------- ۱) پیش‌نیازها ----------
say "بررسی سیستم‌عامل"
. /etc/os-release
case "${ID:-}" in
  ubuntu|debian) ;;
  *) die "این اسکریپت برای اوبونتو یا دبیان نوشته شده. سیستم شما: ${ID:-ناشناس}" ;;
esac

say "نصب داکر (اگر نصب نباشد)"
if ! command -v docker >/dev/null 2>&1; then
  apt-get update -qq
  apt-get install -y -qq ca-certificates curl gnupg
  install -m 0755 -d /etc/apt/keyrings
  curl -fsSL "https://download.docker.com/linux/${ID}/gpg" -o /etc/apt/keyrings/docker.asc
  chmod a+r /etc/apt/keyrings/docker.asc
  echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/${ID} $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
    > /etc/apt/sources.list.d/docker.list
  apt-get update -qq
  apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi
docker compose version >/dev/null 2>&1 || die "افزونه docker compose نصب نشد."

# ---------- ۲) فایل تنظیمات ----------
if [ ! -f .env ]; then
  say "ساخت فایل .env از نمونه"
  cp .env.example .env
  chmod 600 .env
  echo
  echo "فایل .env ساخته شد. حالا آن را باز کنید و مقادیر را پر کنید:"
  echo "    nano $(pwd)/.env"
  echo "بعد دوباره همین اسکریپت را اجرا کنید."
  exit 0
fi

set -a; . ./.env; set +a
[ -n "${SITE_URL:-}" ] || die "SITE_URL در .env خالی است."
[ -n "${MOODLE_PASSWORD:-}" ] || die "MOODLE_PASSWORD در .env خالی است."
case "$MOODLE_PASSWORD" in
  *یک-رمز*) die "رمز مدیر را در .env عوض کنید." ;;
esac

# ---------- ۳) بالا آوردن سرویس‌ها ----------
say "دانلود ایمیج‌ها و راه‌اندازی (چند دقیقه طول می‌کشد)"
docker compose pull -q
docker compose up -d

say "انتظار برای آماده شدن دیتابیس و Moodle"
for i in $(seq 1 60); do
  if docker compose exec -T moodle sh -c 'test -f /var/www/html/config.php' 2>/dev/null; then
    break
  fi
  sleep 5
  [ "$i" -eq 60 ] && die "Moodle در ۵ دقیقه بالا نیامد. لاگ: docker compose logs moodle"
done
sleep 10

# ---------- ۴) فارسی‌سازی ----------
say "نصب بسته زبان فارسی"
docker compose exec -T moodle sh -c '
  set -e
  command -v unzip >/dev/null 2>&1 || apk add --no-cache unzip >/dev/null 2>&1 || true
  mkdir -p /var/www/moodledata/lang
  cd /var/www/moodledata/lang
  curl -fsSL -o fa.zip "https://download.moodle.org/download.php/langpack/5.3/fa.zip"
  unzip -oq fa.zip
  rm -f fa.zip
  test -f fa/moodle.php
' || die "نصب بسته زبان فارسی ناموفق بود."

say "تنظیم فارسی به‌عنوان زبان پیش‌فرض"
docker compose exec -T moodle php /var/www/html/admin/cli/cfg.php --name=lang --set=fa
docker compose exec -T moodle php /var/www/html/admin/cli/cfg.php --name=langlist --set=fa,en
docker compose exec -T moodle php /var/www/html/admin/cli/cfg.php --name=langmenu --set=1
docker compose exec -T moodle php /var/www/html/admin/cli/cfg.php --name=langcache --set=0

say "فعال‌کردن ردیابی پیشرفت و تکمیل فعالیت‌ها"
docker compose exec -T moodle php /var/www/html/admin/cli/cfg.php --name=enablecompletion --set=1
docker compose exec -T moodle php /var/www/html/admin/cli/cfg.php --name=enablebadges --set=1
docker compose exec -T moodle php /var/www/html/admin/cli/cfg.php --name=enableoutcomes --set=1
docker compose exec -T moodle php /var/www/html/admin/cli/cfg.php --name=enableglobalsearch --set=0

say "پاک‌سازی کش"
docker compose exec -T moodle php /var/www/html/admin/cli/purge_caches.php

# ---------- ۵) وب‌سرور و HTTPS ----------
DOMAIN="${SITE_URL#https://}"; DOMAIN="${DOMAIN#http://}"; DOMAIN="${DOMAIN%%/*}"

if [ -n "$DOMAIN" ] && [ "$DOMAIN" != "localhost" ]; then
  say "نصب nginx و گواهی SSL برای $DOMAIN"
  apt-get install -y -qq nginx certbot python3-certbot-nginx

  cat > /etc/nginx/sites-available/moodle <<NGINX
server {
    listen 80;
    server_name $DOMAIN;
    client_max_body_size 50M;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 300s;
    }
}
NGINX
  ln -sf /etc/nginx/sites-available/moodle /etc/nginx/sites-enabled/moodle
  rm -f /etc/nginx/sites-enabled/default
  nginx -t && systemctl reload nginx

  if [ ! -d "/etc/letsencrypt/live/$DOMAIN" ]; then
    certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos \
      --register-unsafely-without-email --redirect || \
      echo "گواهی SSL نصب نشد. بعداً اجرا کنید: certbot --nginx -d $DOMAIN"
  fi
fi

# ---------- ۶) پشتیبان روزانه ----------
say "فعال‌کردن پشتیبان‌گیری روزانه"
install -m 755 scripts/backup.sh /usr/local/bin/moodle-backup
mkdir -p /var/backups/moodle
cat > /etc/cron.d/moodle-backup <<'CRON'
0 3 * * * root /usr/local/bin/moodle-backup >> /var/log/moodle-backup.log 2>&1
CRON

say "تمام شد"
echo
echo "آدرس سایت:  $SITE_URL"
echo "نام کاربری:  $MOODLE_USERNAME"
echo "رمز عبور:    (همان که در .env گذاشتید)"
echo
echo "قدم بعدی: وارد شوید و از «مدیریت سایت ← زبان‌ها» مطمئن شوید فارسی فعال است."
