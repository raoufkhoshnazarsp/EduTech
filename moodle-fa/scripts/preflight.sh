#!/usr/bin/env bash
# بررسی آماده بودن سرور پیش از نصب — هیچ تغییری ایجاد نمی‌کند.
# اجرا:  sudo bash scripts/preflight.sh
set -uo pipefail

ok()   { printf '  \033[1;32m✓\033[0m %s\n' "$*"; }
warn() { printf '  \033[1;33m!\033[0m %s\n' "$*"; }
bad()  { printf '  \033[1;31m✗\033[0m %s\n' "$*"; FAILED=1; }
FAILED=0

echo "بررسی سرور"
echo

# سیستم‌عامل
if [ -f /etc/os-release ]; then
  . /etc/os-release
  case "${ID:-}" in
    ubuntu|debian)
      ok "سیستم‌عامل: ${PRETTY_NAME}"
      ;;
    *) bad "سیستم‌عامل ${PRETTY_NAME:-ناشناس} پشتیبانی نمی‌شود (اوبونتو یا دبیان لازم است)" ;;
  esac
else
  bad "فایل /etc/os-release پیدا نشد"
fi

# دسترسی root
if [ "$(id -u)" -eq 0 ]; then
  ok "دسترسی root دارد"
else
  bad "با sudo اجرا کنید"
fi

# رم
RAM_MB=$(awk '/MemTotal/ {printf "%d", $2/1024}' /proc/meminfo 2>/dev/null || echo 0)
if   [ "$RAM_MB" -ge 7500 ]; then ok "رم: ${RAM_MB} مگابایت"
elif [ "$RAM_MB" -ge 3500 ]; then warn "رم: ${RAM_MB} مگابایت — کار می‌کند ولی ۸ گیگ توصیه می‌شود"
else bad "رم: ${RAM_MB} مگابایت — برای ۵۰۰ دانش‌آموز کم است"; fi

# دیسک
DISK_GB=$(df -BG --output=avail / 2>/dev/null | tail -1 | tr -dc '0-9')
DISK_GB=${DISK_GB:-0}
if   [ "$DISK_GB" -ge 50 ]; then ok "فضای خالی دیسک: ${DISK_GB} گیگابایت"
elif [ "$DISK_GB" -ge 20 ]; then warn "فضای خالی دیسک: ${DISK_GB} گیگابایت — ممکن است کم بیاید"
else bad "فضای خالی دیسک: ${DISK_GB} گیگابایت — حداقل ۲۰ گیگ لازم است"; fi

# هسته‌های CPU
CORES=$(nproc 2>/dev/null || echo 0)
if   [ "$CORES" -ge 4 ]; then ok "پردازنده: ${CORES} هسته"
elif [ "$CORES" -ge 2 ]; then warn "پردازنده: ${CORES} هسته — قابل قبول"
else warn "پردازنده: ${CORES} هسته — کند خواهد بود"; fi

# معماری
ARCH=$(uname -m)
case "$ARCH" in
  x86_64|aarch64) ok "معماری: $ARCH" ;;
  *) bad "معماری $ARCH پشتیبانی نمی‌شود (x86_64 یا aarch64 لازم است)" ;;
esac

# داکر
if command -v docker >/dev/null 2>&1; then
  ok "داکر نصب است: $(docker --version | cut -d, -f1)"
  if docker compose version >/dev/null 2>&1; then
    ok "افزونه docker compose موجود است"
  else
    warn "افزونه docker compose نیست — نصب خودش اضافه می‌کند"
  fi
else
  warn "داکر نصب نیست — اسکریپت نصب خودش نصب می‌کند"
fi

# پورت‌های لازم
for p in 80 443; do
  if ss -ltn 2>/dev/null | awk '{print $4}' | grep -qE "[:.]${p}\$"; then
    bad "پورت $p اشغال است (nginx یا وب‌سرور دیگری در حال اجراست)"
  else
    ok "پورت $p آزاد است"
  fi
done

# اتصال به اینترنت
if curl -fsS --max-time 10 https://api.ipify.org >/dev/null 2>&1; then
  ok "اتصال اینترنت برقرار است (IP عمومی: $(curl -fsS --max-time 10 https://api.ipify.org))"
else
  bad "اتصال خروجی به اینترنت برقرار نیست"
fi

# دسترسی به مخازن لازم
for u in https://download.docker.com https://download.moodle.org https://github.com; do
  if curl -fsS --max-time 15 -o /dev/null -r 0-0 "$u" 2>/dev/null || curl -fsS --max-time 15 -o /dev/null -I "$u" 2>/dev/null; then
    ok "دسترسی به $u"
  else
    warn "دسترسی به $u برقرار نشد — ممکن است نصب ناموفق شود"
  fi
done

echo
if [ "$FAILED" -eq 1 ]; then
  echo "نتیجه: چند مشکل جدی هست. قبل از نصب برطرف کنید."
  exit 1
fi
echo "نتیجه: سرور آماده است. ادامه دهید به «قدم ۲»."
