import re, json, urllib.request, urllib.parse, sys

B = "http://127.0.0.1:8080/product-category/cnc/"
AJAX = "http://127.0.0.1:8080/wp-admin/admin-ajax.php"

def get(url):
    with urllib.request.urlopen(urllib.request.Request(url, headers={"User-Agent":"zig"})) as r:
        return r.read().decode("utf-8","replace")

html = get(B)
attr = lambda n: (re.search(r'data-zig-%s="([^"]*)"' % n, html) or [None,None])[1]

ctx = {n: attr(n) for n in ("nonce","post","widget","term")}
print("context:", {k: (v[:12]+"…" if k=="nonce" and v else v) for k,v in ctx.items()})

def post(fields, referer=B):
    data = urllib.parse.urlencode(fields).encode()
    req = urllib.request.Request(AJAX, data=data, headers={"User-Agent":"zig","Referer":referer})
    try:
        with urllib.request.urlopen(req) as r:
            return r.status, json.loads(r.read().decode())
    except urllib.error.HTTPError as e:
        body = e.read().decode()
        try: return e.code, json.loads(body)
        except Exception: return e.code, body

base = {
    "action": "zig3d_archive",
    "nonce": ctx["nonce"],
    "post_id": ctx["post"],
    "widget_id": ctx["widget"],
    "term_id": ctx["term"],
    "contract": "1",
}

results = []
def check(name, ok, detail=""):
    results.append(ok)
    print(f"  {'PASS' if ok else 'FAIL'}  {name}" + (f" — {detail}" if detail else ""))

print("── پاسخ موفق")
s, r = post({**base, "query": "filter_brand=up3d"})
check("HTTP 200", s == 200, str(s))
d = r.get("data", {}) if isinstance(r, dict) else {}
check("success envelope", r.get("success") is True)
check("contract", d.get("contract") == 1, str(d.get("contract")))
check("state ok", d.get("state") == "ok", str(d.get("state")))
check("found = 11", d.get("found") == 11, str(d.get("found")))
check("grid fragment", "zig-archive__cell" in d.get("grid",""), str(len(d.get("grid",""))) + " bytes")
check("facets fragment", "zig-facet__item" in d.get("facets",""))
check("count fragment", "zig-archive__count" in d.get("count",""))
check("url is canonical", "filter_brand=up3d" in d.get("url",""), d.get("url",""))

print("── نتیجهٔ خالی، نه خطا")
s, r = post({**base, "query": "filter_brand=up3d&filter_axis=3-axis&filter_material=pmma"})
d = r.get("data", {}) if isinstance(r, dict) else {}
check("HTTP is still 200", s == 200, str(s))
check("state is filtered_empty", d.get("state") == "filtered_empty", str(d.get("state")))
check("grid carries the empty block", "zig-archive__empty" in d.get("grid",""))

print("── آدرس بی‌معنا")
s, r = post({**base, "query": "filter_ghost=x"})
d = r.get("data", {}) if isinstance(r, dict) else {}
check("HTTP 200 (transport ok)", s == 200, str(s))
check("state invalid", d.get("state") == "invalid", str(d.get("state")))

print("── خطاهای فنی، تنها مسیر آلرت")
s, r = post({**base, "nonce": "bogus", "query": ""})
check("bad nonce is 403", s == 403, str(s))
s, r = post({**base, "contract": "999", "query": ""})
check("wrong contract is 400", s == 400, str(s))
s, r = post({**base, "post_id": "999999", "query": ""})
check("unknown post is 400", s == 400, str(s))
s, r = post({**base, "widget_id": "nope", "query": ""})
check("unknown widget is 400", s == 400, str(s))
s, r = post({**base, "query": "filter_brand=" + ",".join(f"s{i}" for i in range(5000))})
check("oversized query is 400", s == 400, str(s))

print("── سقف در آژاکس هم برقرار است")
s, r = post({**base, "query": "&".join(f"filter_g{i:02d}=x" for i in range(20))})
d = r.get("data", {}) if isinstance(r, dict) else {}
check("many groups -> invalid", d.get("state") == "invalid", str(d.get("state")))

print()
bad = results.count(False)
print(f"{len(results)} سنجه، {bad} ناموفق")
sys.exit(1 if bad else 0)
