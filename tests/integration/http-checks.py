import re, urllib.request, urllib.parse, json, sys

B = "http://127.0.0.1:8080/product-category/cnc/"
FA = str.maketrans("۰۱۲۳۴۵۶۷۸۹", "0123456789")

def get(url):
    req = urllib.request.Request(url, headers={"User-Agent": "zig-test"})
    try:
        with urllib.request.urlopen(req) as r:
            return r.status, r.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace")

def num(s):
    return int(s.translate(FA)) if s else None

def count(html):
    m = re.search(r'class="zig-archive__count"[^>]*>([^<]*)', html)
    if not m: return None
    d = re.search(r'[۰-۹0-9]+', m.group(1))
    return num(d.group(0)) if d else None

def cards(html):
    return len(re.findall(r'zig-archive__cell', html))

def facet_count(html, param, slug):
    m = re.search(r'data-zig-toggle="%s\|%s".*?zig-facet__count">([۰-۹]+)' % (re.escape(param), re.escape(slug)), html, re.S)
    return num(m.group(1)) if m else None

results = []
def check(name, ok, detail=""):
    results.append((name, ok, detail))
    print(f"  {'PASS' if ok else 'FAIL'}  {name}" + (f" — {detail}" if detail else ""))

print("── ۱ و ۶: عدد کنار گزینه = نتیجهٔ بعد از کلیک")
_, home = get(B)
for slug in ("up3d", "vhf", "roland"):
    side = facet_count(home, "filter_brand", slug)
    _, page = get(B + "?filter_brand=" + slug)
    real = count(page)
    check(f"brand {slug}", side == real and side is not None, f"سایدبار={side} واقعی={real}")

print("── ۳: چندانتخابی OR = اجتماع")
_, a = get(B + "?filter_brand=up3d")
_, b = get(B + "?filter_brand=vhf")
_, u = get(B + "?filter_brand=up3d,vhf&query_type_brand=or")
check("union", count(u) == count(a) + count(b), f"{count(a)}+{count(b)}={count(u)}")

print("── ۸: اسلاگ فارسی")
fa = urllib.parse.quote("زیرکونیا")
_, z = get(B + "?filter_material=" + fa)
check("persian slug filters", z is not None and count(z) not in (None, 0, count(home)), f"زیرکونیا={count(z)} از {count(home)}")

print("── ۲: محصول متغیر یک بار")
_, five = get(B + "?filter_axis=5-axis")
check("variable counted once", count(five) == 16, f"ویجت={count(five)} دیتابیس=16")

print("── صفحه‌بندی")
s1, p1 = get(B)
s2, p2 = get(B + "?paged=2")
check("page 1 is full", cards(p1) == 16, str(cards(p1)))
check("page 2 differs", cards(p2) == 15 and p1 != p2, str(cards(p2)))
check("page 2 is 200", s2 == 200, str(s2))

print("── نتیجهٔ خالی، نه خطا")
s, e = get(B + "?filter_brand=up3d&filter_axis=3-axis&filter_material=pmma&query_type_brand=or")
check("empty filter is 404", s == 404, str(s))
check("but page still renders the archive", "Page not found" not in e, "قالب ۴۰۴ عمومی نیست")

print("── سئو")
_, h = get(B + "?filter_brand=up3d")
robots = re.findall(r'<meta name="robots" content="([^"]*)"', h)
check("filtered page is noindex, follow", "noindex, follow" in robots, str(robots))
canon = re.findall(r'<link rel="canonical" href="([^"]*)"', h)
check("canonical points at the clean URL", len(canon) == 1 and "filter_" not in canon[0], str(canon))
_, c = get(B)
rc = re.findall(r'<meta name="robots" content="([^"]*)"', c)
check("clean page is index, follow", "index, follow" in rc, str(rc))

print()
bad = sum(1 for _, ok, _ in results if not ok)
print(f"{len(results)} سنجه، {bad} ناموفق")
sys.exit(1 if bad else 0)
