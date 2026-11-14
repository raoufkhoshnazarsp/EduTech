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
[ -n "${MOODLE_PASSWORD:-}" ] || die "MOODLE_PASSWORD در .env خالی است."
case "$MOODLE_PASSWORD" in
  *یک-رمز*) die "رمز مدیر را در .env عوض کنید." ;;
esac

# ---------- ۳) تعیین آدرس سایت (بدون نیاز به خرید دامنه) ----------
# اگر SITE_URL خالی یا IP باشد، از زیردامنه رایگان DuckDNS استفاده می‌کنیم.
# DuckDNS در لیست دامنه‌های عمومی (PSL) است، پس Let's Encrypt برایش
# گواهی معتبر صادر می‌کند و به سقف نرخ مشترک نمی‌خورد.
#
# مهم: IP «خروجی» سرور همیشه همان IP «ورودی» نیست. بعضی سرویس‌دهنده‌ها
# (از جمله برخی سرورهای ایران) ترافیک خروجی را از IP دیگری می‌فرستند.
# DNS و SSL به IP ورودی نیاز دارند، پس اول آن را تشخیص می‌دهیم.
detect_public_ip() {
  local candidate ip
  # ۱) IP ورودی همان اتصال SSH — مطمئن‌ترین منبع
  if [ -n "${SSH_CONNECTION:-}" ]; then
    ip="${SSH_CONNECTION%% *}"
    if printf '%s' "$ip" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$'; then
      printf '%s' "$ip"; return 0
    fi
  fi
  # ۲) IP روی کارت شبکه (اگر عمومی باشد)
  ip="$(ip -4 addr show scope global 2>/dev/null | awk '/inet /{print $2}' | cut -d/ -f1 | head -1)"
  if printf '%s' "$ip" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$' \
     && ! printf '%s' "$ip" | grep -qE '^(10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.|100\.6[4-9]\.|100\.[7-9][0-9]\.|100\.1[0-2][0-9]\.)'; then
    printf '%s' "$ip"; return 0
  fi
  # ۳) سرویس‌های تشخیص IP
  for candidate in https://api.ipify.org https://ifconfig.me/ip https://icanhazip.com; do
    ip="$(curl -fsS --max-time 10 "$candidate" 2>/dev/null | tr -d '[:space:]')"
    if printf '%s' "$ip" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$'; then
      printf '%s' "$ip"; return 0
    fi
  done
  return 1
}

PUBLIC_IP="$(detect_public_ip || true)"
[ -n "$PUBLIC_IP" ] || die "IP عمومی سرور تشخیص داده نشد. اتصال شبکه را بررسی کنید."
echo "IP ورودی سرور: $PUBLIC_IP"

OUTBOUND_IP="$(curl -fsS --max-time 10 https://api.ipify.org 2>/dev/null | tr -d '[:space:]' || true)"
if [ -n "$OUTBOUND_IP" ] && [ "$OUTBOUND_IP" != "$PUBLIC_IP" ]; then
  echo "توجه: IP خروجی سرور $OUTBOUND_IP است (با IP ورودی فرق دارد)."
  echo "      برای DNS و SSL از IP ورودی $PUBLIC_IP استفاده می‌شود — که درست است."
fi

SITE_URL="${SITE_URL:-}"
SITE_URL="${SITE_URL%/}"
if [ -z "$SITE_URL" ] || printf '%s' "$SITE_URL" | grep -qE '^https?://[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$'; then
  SUB="${DUCKDNS_SUBDOMAIN:-}"
  if [ -z "$SUB" ]; then
    echo
    echo "شما دامنه ندارید. یک زیردامنه رایگان DuckDNS می‌سازیم."
    echo "  ۱) به https://www.duckdns.org بروید و با گوگل/گیت‌هاب وارد شوید"
    echo "  ۲) یک نام انتخاب کنید (مثلاً myacademy) و در فیلد current ip مقدار $PUBLIC_IP را بگذارید"
    echo "  ۳) همان نام را اینجا وارد کنید"
    echo
    read -r -p "زیردامنه DuckDNS (بدون .duckdns.org): " SUB
  fi
  SUB="$(printf '%s' "$SUB" | tr 'A-Z' 'a-z' | tr -cd 'a-z0-9-')"
  [ -n "$SUB" ] || die "زیردامنه خالی است. دوباره اجرا کنید یا SITE_URL را در .env بگذارید."

  AUTO_HOST="${SUB}.duckdns.org"
  SITE_URL="https://${AUTO_HOST}"
  say "آدرس سایت: $AUTO_HOST"

  # به‌روزرسانی رکورد DNS به IP فعلی سرور
  if [ -n "${DUCKDNS_TOKEN:-}" ]; then
    curl -fsS "https://www.duckdns.org/update?domains=${SUB}&token=${DUCKDNS_TOKEN}&ip=${PUBLIC_IP}" >/dev/null \
      && echo "رکورد DNS به‌روز شد." || echo "هشدار: به‌روزرسانی DNS ناموفق بود."
  fi

  # نوشتن آدرس نهایی در .env تا داکر همان را ببیند
  if grep -q '^SITE_URL=' .env; then
    sed -i "s|^SITE_URL=.*|SITE_URL=\"${SITE_URL}\"|" .env
  else
    printf 'SITE_URL="%s"\n' "$SITE_URL" >> .env
  fi
  export SITE_URL
fi

DOMAIN="${SITE_URL#https://}"; DOMAIN="${DOMAIN#http://}"; DOMAIN="${DOMAIN%%/*}"
[ -n "$DOMAIN" ] || die "آدرس سایت نامعتبر است: $SITE_URL"

# ---------- ۴) بالا آوردن سرویس‌ها ----------
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

# ---------- ۵) فارسی‌سازی ----------
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

# ---------- ۶) تنظیمات آموزشی ----------
say "فعال‌کردن ردیابی پیشرفت، نشان‌ها و کارنامه"
docker compose exec -T moodle php /var/www/html/admin/cli/cfg.php --name=enablecompletion --set=1
docker compose exec -T moodle php /var/www/html/admin/cli/cfg.php --name=enablebadges --set=1
docker compose exec -T moodle php /var/www/html/admin/cli/cfg.php --name=enableoutcomes --set=1
docker compose exec -T moodle php /var/www/html/admin/cli/cfg.php --name=enableglobalsearch --set=0
docker compose exec -T moodle php /var/www/html/admin/cli/cfg.php --name=enableplagiarism --set=0
docker compose exec -T moodle php /var/www/html/admin/cli/cfg.php --name=enableportfolios --set=0

say "تنظیم پیش‌فرض آزمون‌ها (نمره‌دار + تمرینی)"
# آزمون‌ها به‌صورت پیش‌فرض نمره‌دار و با نمایش نمره به دانش‌آموز
docker compose exec -T moodle php /var/www/html/admin/cli/cfg.php --name=quiz_showuserpicture --set=1
docker compose exec -T moodle php /var/www/html/admin/cli/cfg.php --name=gradepointdefault --set=100
docker compose exec -T moodle php /var/www/html/admin/cli/cfg.php --name=grade_export_enabled --set=1
# ثبت‌نام خودکار خاموش: فقط مدیر دانش‌آموز می‌سازد
docker compose exec -T moodle php /var/www/html/admin/cli/cfg.php --name=registerauth --set=email
docker compose exec -T moodle php /var/www/html/admin/cli/cfg.php --name=guestloginbutton --set=0

# ---------- ۷) افزونه گواهی پایان دوره ----------
say "نصب افزونه گواهی پایان دوره (Custom Certificate)"
docker compose exec -T moodle sh -c '
  set -e
  cd /var/www/html/mod
  if [ ! -d customcert ]; then
    curl -fsSL -o cc.zip "https://github.com/mdjnelson/moodle-mod_customcert/archive/refs/heads/MOODLE_503_STABLE.zip"
    unzip -oq cc.zip
    mv moodle-mod_customcert-MOODLE_503_STABLE customcert
    rm -f cc.zip
  fi
  test -f customcert/version.php
' || echo "هشدار: افزونه گواهی نصب نشد. بعداً از «افزونه‌ها ← نصب افزونه» نصب کنید."

docker compose exec -T moodle php /var/www/html/admin/cli/upgrade.php --non-interactive --allow-unstable \
  || echo "هشدار: ارتقای دیتابیس برای افزونه گواهی انجام نشد."

say "پاک‌سازی کش"
docker compose exec -T moodle php /var/www/html/admin/cli/purge_caches.php

# ---------- ۸) وب‌سرور و HTTPS ----------
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

# باز کردن پورت‌ها اگر فایروال روشن است
if command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -q "Status: active"; then
  say "باز کردن پورت‌های ۸۰ و ۴۴۳ در فایروال"
  ufw allow 80/tcp >/dev/null
  ufw allow 443/tcp >/dev/null
fi

# بررسی اینکه دامنه واقعاً به این سرور اشاره می‌کند
say "بررسی DNS برای $DOMAIN"
RESOLVED=""
for i in $(seq 1 12); do
  RESOLVED="$(getent ahostsv4 "$DOMAIN" 2>/dev/null | awk 'NR==1{print $1}')"
  [ "$RESOLVED" = "$PUBLIC_IP" ] && break
  sleep 5
done

if [ "$RESOLVED" != "$PUBLIC_IP" ]; then
  echo
  echo "هشدار: دامنه $DOMAIN به $PUBLIC_IP اشاره نمی‌کند (مقدار فعلی: ${RESOLVED:-هیچ})."
  echo "گواهی SSL صادر نمی‌شود تا DNS درست شود."
  echo "بعد از اصلاح DNS این را اجرا کنید:"
  echo "    certbot --nginx -d $DOMAIN"
  echo
  echo "سایت فعلاً با http://$PUBLIC_IP کار می‌کند."
else
  if [ ! -d "/etc/letsencrypt/live/$DOMAIN" ]; then
    certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos \
      --register-unsafely-without-email --redirect || \
      echo "گواهی SSL نصب نشد. بعداً اجرا کنید: certbot --nginx -d $DOMAIN"
  else
    echo "گواهی SSL از قبل موجود است."
  fi
fi

# ---------- ۹) پشتیبان روزانه ----------
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
