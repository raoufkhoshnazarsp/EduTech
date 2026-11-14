#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
تبدیل فایل اکسل دانش‌آموزان به CSV قابل بارگذاری در Moodle.

استفاده:
    python3 excel-to-moodle-csv.py students.xlsx -o students.csv
    python3 excel-to-moodle-csv.py students.xlsx -o students.csv --course MATH101

فقط از کتابخانه استاندارد پایتون استفاده می‌کند (نیازی به pip install نیست).

ستون‌های اکسل شما می‌تواند فارسی باشد. این نام‌ها شناخته می‌شوند:
    نام کاربری / username
    نام / firstname
    نام خانوادگی / lastname
    ایمیل / email
    رمز / password
    کد ملی / idnumber
    درس / course
    گروه / group

اگر ستون نام کاربری نبود، از کد ملی و در نبود آن از شماره ردیف ساخته می‌شود.
"""

import argparse
import csv
import re
import sys
import unicodedata
import zipfile
from xml.etree import ElementTree as ET

NS = "{http://schemas.openxmlformats.org/spreadsheetml/2006/main}"

# نگاشت سرستون‌های فارسی/انگلیسی به فیلدهای Moodle
ALIASES = {
    "username": ["username", "user name", "نام کاربری", "نام‌کاربری", "یوزرنیم", "کد دانش‌آموزی", "کد دانش آموزی"],
    "firstname": ["firstname", "first name", "نام", "نام کوچک"],
    "lastname": ["lastname", "last name", "surname", "نام خانوادگی", "فامیل", "نام‌خانوادگی"],
    "email": ["email", "e-mail", "mail", "ایمیل", "پست الکترونیک", "رایانامه"],
    "password": ["password", "pass", "رمز", "رمز عبور", "گذرواژه"],
    "idnumber": ["idnumber", "id number", "کد ملی", "کدملی", "شماره ملی", "کد"],
    "course1": ["course", "course1", "درس", "دوره", "کلاس", "کد درس"],
    "group1": ["group", "group1", "گروه", "کلاس گروه"],
    "phone1": ["phone", "mobile", "موبایل", "تلفن", "شماره تماس"],
    "city": ["city", "شهر"],
    "institution": ["institution", "مدرسه", "آموزشگاه"],
    "department": ["department", "رشته", "پایه"],
}

REQUIRED = ["firstname", "lastname", "email"]


def norm(text):
    """یکسان‌سازی متن فارسی: حذف نیم‌فاصله، یکسان‌کردن ی/ک عربی، حذف فاصله اضافه."""
    if text is None:
        return ""
    text = unicodedata.normalize("NFKC", str(text))
    text = text.replace("‌", " ").replace("‏", "").replace("‎", "")
    text = text.replace("ي", "ی").replace("ك", "ک").replace("ۀ", "ه")
    text = re.sub(r"\s+", " ", text)
    return text.strip()


def col_index(ref):
    """A1 -> 0، B1 -> 1، AA1 -> 26"""
    letters = re.match(r"([A-Z]+)", ref)
    if not letters:
        return 0
    n = 0
    for ch in letters.group(1):
        n = n * 26 + (ord(ch) - 64)
    return n - 1


def read_xlsx(path):
    """خواندن اولین شیت فایل xlsx با کتابخانه استاندارد."""
    with zipfile.ZipFile(path) as z:
        # رشته‌های مشترک
        shared = []
        if "xl/sharedStrings.xml" in z.namelist():
            root = ET.fromstring(z.read("xl/sharedStrings.xml"))
            for si in root.findall(f"{NS}si"):
                shared.append("".join(t.text or "" for t in si.iter(f"{NS}t")))

        # نام اولین شیت
        wb = ET.fromstring(z.read("xl/workbook.xml"))
        sheets = wb.find(f"{NS}sheets")
        first = sheets[0]
        rid = first.get("{http://schemas.openxmlformats.org/officeDocument/2006/relationships}id")

        rels = ET.fromstring(z.read("xl/_rels/workbook.xml.rels"))
        target = None
        for rel in rels:
            if rel.get("Id") == rid:
                target = rel.get("Target")
                break
        if not target:
            raise ValueError("شیت اول فایل اکسل پیدا نشد.")
        target = target.lstrip("/")
        if not target.startswith("xl/"):
            target = "xl/" + target

        root = ET.fromstring(z.read(target))
        rows = []
        for row in root.iter(f"{NS}row"):
            cells = {}
            for c in row.findall(f"{NS}c"):
                idx = col_index(c.get("r", "A1"))
                t = c.get("t")
                v = c.find(f"{NS}v")
                if t == "inlineStr":
                    is_el = c.find(f"{NS}is")
                    val = "".join(x.text or "" for x in is_el.iter(f"{NS}t")) if is_el is not None else ""
                elif v is None:
                    val = ""
                elif t == "s":
                    val = shared[int(v.text)]
                else:
                    val = v.text or ""
                cells[idx] = val
            if cells:
                width = max(cells) + 1
                rows.append([cells.get(i, "") for i in range(width)])
        return rows


def read_csv(path):
    with open(path, newline="", encoding="utf-8-sig") as f:
        sample = f.read(4096)
        f.seek(0)
        try:
            dialect = csv.Sniffer().sniff(sample, delimiters=",;\t")
        except csv.Error:
            dialect = csv.excel
        return [row for row in csv.reader(f, dialect)]


def map_headers(header_row):
    """برگرداندن دیکشنری: نام فیلد Moodle -> شماره ستون"""
    mapping = {}
    for i, raw in enumerate(header_row):
        h = norm(raw).lower()
        if not h:
            continue
        for field, names in ALIASES.items():
            if field in mapping:
                continue
            if h in [norm(n).lower() for n in names]:
                mapping[field] = i
                break
    return mapping


def slug_username(text, fallback):
    """ساخت نام کاربری مجاز Moodle: حروف کوچک لاتین، رقم، نقطه، خط تیره."""
    text = norm(text)
    text = re.sub(r"[^a-zA-Z0-9._-]", "", text.lower())
    return text or fallback


def main():
    ap = argparse.ArgumentParser(description="تبدیل اکسل دانش‌آموزان به CSV مودل")
    ap.add_argument("input", help="فایل ورودی .xlsx یا .csv")
    ap.add_argument("-o", "--output", default="students-moodle.csv", help="فایل CSV خروجی")
    ap.add_argument("--course", help="کد درس پیش‌فرض برای همه ردیف‌ها")
    ap.add_argument("--password", help="رمز پیش‌فرض؛ اگر نبود رمز تصادفی ساخته می‌شود")
    ap.add_argument("--default-password", default="ChangeMe123!", help="رمز پیش‌فرض (پیش‌فرض: ChangeMe123!)")
    args = ap.parse_args()

    if args.input.lower().endswith((".xlsx", ".xlsm")):
        rows = read_xlsx(args.input)
    elif args.input.lower().endswith(".csv"):
        rows = read_csv(args.input)
    else:
        sys.exit("خطا: فقط .xlsx یا .csv پشتیبانی می‌شود. اگر فایل .xls قدیمی است، در اکسل «ذخیره به‌عنوان xlsx» کنید.")

    if len(rows) < 2:
        sys.exit("خطا: فایل باید یک ردیف سرستون و حداقل یک ردیف داده داشته باشد.")

    header, data = rows[0], rows[1:]
    mapping = map_headers(header)

    missing = [f for f in REQUIRED if f not in mapping]
    if missing:
        print("سرستون‌های پیدا شده:", [norm(h) for h in header if norm(h)])
        sys.exit(
            "خطا: این ستون‌ها پیدا نشد: " + ", ".join(missing) +
            "\nراهنما: سرستون‌ها را به یکی از این نام‌ها تغییر دهید: "
            "نام کاربری، نام، نام خانوادگی، ایمیل"
        )

    out_fields = ["username", "firstname", "lastname", "email", "password", "idnumber", "course1", "group1"]
    written = 0
    seen = set()

    with open(args.output, "w", newline="", encoding="utf-8-sig") as f:
        w = csv.writer(f)
        w.writerow(out_fields)

        for n, row in enumerate(data, start=2):
            def get(field):
                i = mapping.get(field)
                if i is None or i >= len(row):
                    return ""
                return norm(row[i])

            first = get("firstname")
            last = get("lastname")
            email = get("email")
            if not (first or last or email):
                continue  # ردیف خالی

            idnumber = get("idnumber")
            username = get("username")
            if not username:
                username = slug_username(idnumber, f"student{n}")
            username = slug_username(username, f"student{n}")

            # جلوگیری از تکرار نام کاربری
            base, k = username, 1
            while username in seen:
                k += 1
                username = f"{base}{k}"
            seen.add(username)

            if not email:
                email = f"{username}@example.com"

            course = get("course1") or (args.course or "")
            group = get("group1")
            password = get("password") or args.password or args.default_password

            w.writerow([username, first, last, email, password, idnumber, course, group])
            written += 1

    print(f"✓ {written} دانش‌آموز در فایل «{args.output}» نوشته شد.")
    print()
    print("قدم بعدی در Moodle:")
    print("  مدیریت سایت ← کاربران ← حساب‌ها ← بارگذاری دسته‌ای کاربران")
    print("  فایل را انتخاب کنید، «CSV» را بزنید، پیش‌نمایش را ببینید و تأیید کنید.")
    if not args.course and "course1" not in mapping:
        print()
        print("نکته: ستون درس خالی است. بعداً از «ثبت‌نام‌ها ← روش‌های ثبت‌نام ← بارگذاری»")
        print("      دانش‌آموزان را به درس‌ها اضافه کنید، یا با --course کد درس بدهید.")


if __name__ == "__main__":
    main()
