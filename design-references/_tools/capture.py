"""Capture viewport screenshots + above-the-fold facts for one e-commerce site.

Usage: python3 capture.py <site_key>
Writes: /home/user/design-references/<folder>/screens/NN-<name>.png
        /home/user/design-references/<folder>/facts.json
Only public pages are visited (no login, no checkout, no cart writes).
"""
import json
import os
import re
import sys

from playwright.sync_api import sync_playwright

ROOT = "/home/user/design-references"
UA = ("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
      "(KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36")
MOBILE_UA = ("Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 "
             "(KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1")

SITES = {
    "01-amazon": dict(name="Amazon", home="https://www.amazon.com",
                      listing="https://www.amazon.com/s?k=wireless+headphones",
                      product_re=r"amazon\.com/[^\"' ]*/dp/[A-Z0-9]{10}"),
    "02-nordstrom": dict(name="Nordstrom", home="https://www.nordstrom.com",
                         listing="https://www.nordstrom.com/browse/women/clothing",
                         product_re=r"nordstrom\.com/s/[^\"' ]+/\d+"),
    "02-costco": dict(name="Costco", home="https://www.costco.com",
                      listing="https://www.costco.com/CatalogSearch?keyword=headphones",
                      product_re=r"costco\.com/[^\"' ]*\.product\.\d+\.html"),
    "03-walmart": dict(name="Walmart", home="https://www.walmart.com",
                       listing="https://www.walmart.com/search?q=headphones",
                       product_re=r"walmart\.com/ip/[^\"' ]+"),
    "04-best-buy": dict(name="Best Buy", home="https://www.bestbuy.com",
                        listing="https://www.bestbuy.com/site/searchpage.jsp?st=headphones",
                        product_re=r"bestbuy\.com/product/[^\"' #]+/sku/\d+"),
    "05-nike": dict(name="Nike", home="https://www.nike.com",
                    listing="https://www.nike.com/w/mens-shoes-nik1zy7ok",
                    product_re=r"nike\.com/t/[^\"' ]+"),
    "06-target": dict(name="Target", home="https://www.target.com",
                      listing="https://www.target.com/s?searchTerm=headphones",
                      product_re=r"target\.com/p/[^\"' ]+/-/A-\d+"),
    "07-home-depot": dict(name="Home Depot", home="https://www.homedepot.com",
                          listing="https://www.homedepot.com/s/drill",
                          product_re=r"homedepot\.com/p/[^\"' ]+/\d+"),
    "08-bed-bath-beyond": dict(name="Bed Bath & Beyond", home="https://www.bedbathandbeyond.com",
                               listing="https://www.bedbathandbeyond.com/search?q=towels",
                               product_re=r"bedbathandbeyond\.com/[^\"' ]+/\d+\.html"),
    "08-apple": dict(name="Apple Store", home="https://www.apple.com",
                     listing="https://www.apple.com/shop/buy-iphone",
                     product_re=r"apple\.com/shop/buy-iphone/[^\"' ]+"),
    "09-gap": dict(name="Gap", home="https://www.gap.com",
                   listing="https://www.gap.com/browse/category.do?cid=5094",
                   product_re=r"gap\.com/products/[^\"' ]+\.jsp"),
    "10-walgreens": dict(name="Walgreens", home="https://www.walgreens.com",
                         listing="https://www.walgreens.com/search/results.jsp?Ntt=headphones",
                         product_re=r"walgreens\.com/store/c/[^\"' ]+/ID=[^\"' ]+"),
}

# viewport name, width, height, mobile?, device-scale, path key
SHOTS = [
    ("home-desktop-16x10-1440", 1440, 900, False, "home"),
    ("home-desktop-16x9-1920", 1920, 1080, False, "home"),
    ("home-ultrawide-21x9-2560", 2560, 1080, False, "home"),
    ("home-laptop-16x9-1366", 1366, 768, False, "home"),
    ("home-tablet-portrait-3x4-768", 768, 1024, True, "home"),
    ("home-tablet-landscape-4x3-1024", 1024, 768, True, "home"),
    ("home-phone-small-360", 360, 740, True, "home"),
    ("home-phone-iphone-390", 390, 844, True, "home"),
    ("home-phone-large-430", 430, 932, True, "home"),
    ("listing-desktop-16x10-1440", 1440, 900, False, "listing"),
    ("listing-phone-390", 390, 844, True, "listing"),
    ("listing-tablet-portrait-768", 768, 1024, True, "listing"),
    ("product-desktop-16x10-1440", 1440, 900, False, "product"),
    ("product-phone-390", 390, 844, True, "product"),
    ("product-phone-small-360", 360, 740, True, "product"),
    ("extra-home-phone-android-412", 412, 915, True, "home"),
    ("extra-home-ipad-pro-834", 834, 1194, True, "home"),
    ("extra-home-desktop-16x10-1280", 1280, 800, False, "home"),
]
EXTRA = os.environ.get("EXTRA") == "1"

BLOCK_RE = re.compile(r"unusual activity|robot or human|access denied|access to this page|security verification|error processing|error page|403 error|captcha|pardon our interruption|something went wrong", re.I)

DISMISS = ["Accept All", "Accept all", "Accept", "I Accept", "Got it", "Agree", "Close"]


def dismiss(page):
    for label in DISMISS:
        try:
            loc = page.get_by_role("button", name=label, exact=True).first
            if loc.is_visible(timeout=600):
                loc.click(timeout=1500)
                page.wait_for_timeout(400)
                return
        except Exception:
            pass


def visible_facts(page):
    return page.evaluate(r"""() => {
      const vis = el => { const r = el.getBoundingClientRect();
        const s = getComputedStyle(el);
        return r.width > 0 && r.height > 0 && r.bottom > 0 && r.top < innerHeight &&
               s.visibility !== 'hidden' && s.display !== 'none' && +s.opacity > 0; };
      const txt = el => (el.innerText || el.getAttribute('aria-label') || el.value || '').trim().replace(/\s+/g,' ').slice(0,60);
      const buttons = [...document.querySelectorAll('button,a[role=button],[class*=btn],a[class*=button]')]
        .filter(vis).map(txt).filter(Boolean);
      const inputs = [...document.querySelectorAll('input[type=search],input[type=text],input:not([type]),input[type=email]')]
        .filter(vis).map(i => ({placeholder: i.placeholder || '', aria: i.getAttribute('aria-label') || ''}));
      const h1 = [...document.querySelectorAll('h1')].filter(vis).map(txt);
      const headerSticky = [...document.querySelectorAll('header,[role=banner]')].some(h => getComputedStyle(h).position === 'sticky' || getComputedStyle(h).position === 'fixed');
      const bottomNav = [...document.querySelectorAll('nav,footer,div')].some(n => { const r = n.getBoundingClientRect(); const s = getComputedStyle(n); return s.position === 'fixed' && r.bottom >= innerHeight - 2 && r.height > 40 && r.height < 140 && r.width >= innerWidth - 2; });
      const imgs = [...document.images].filter(vis).length;
      return { title: document.title, h1: h1.slice(0,4), buttons: [...new Set(buttons)].slice(0,25),
               inputs: inputs.slice(0,5), headerSticky, bottomFixedBar: bottomNav, visibleImages: imgs,
               viewport: [innerWidth, innerHeight] };
    }""")


def _links_from(page, url):
    page.goto(url, wait_until="domcontentloaded", timeout=45000)
    page.wait_for_timeout(4000)
    dismiss(page)
    for _ in range(3):  # lazy-loaded tiles need a scroll
        page.mouse.wheel(0, 1200)
        page.wait_for_timeout(1200)
    return page.evaluate("() => [...document.querySelectorAll('a[href]')].map(a => a.href)")


def find_product(page, listing_url, product_re, home_url=None):
    # Try the listing first, then the home page. Some sites serve a bot
    # challenge on one page but not the other.
    for url in [listing_url, home_url]:
        if not url:
            continue
        try:
            hrefs = _links_from(page, url)
        except Exception:
            continue
        for h in hrefs:
            if h and re.search(product_re, h):
                return h
    return None


def main(key):
    cfg = SITES[key]
    folder = os.path.join(ROOT, key)
    shots_dir = os.path.join(folder, "screens")
    os.makedirs(shots_dir, exist_ok=True)
    facts = {"site": cfg["name"], "folder": key, "captured": [], "errors": []}
    prev = os.path.join(folder, "facts.json")
    if EXTRA and os.path.exists(prev):
        facts = json.load(open(prev))
        facts["errors"] = []
    with sync_playwright() as p:
        browser = p.chromium.launch(args=["--no-sandbox", "--disable-blink-features=AutomationControlled"],
                                    ignore_default_args=["--enable-automation"])
        # Resolve the product URL once, on desktop.
        product = None
        if EXTRA:
            product = facts.get("product_url")
        else:
            dctx = browser.new_context(viewport={"width": 1440, "height": 900}, user_agent=UA, locale="en-US")
            dpage = dctx.new_page()
            try:
                product = find_product(dpage, cfg["listing"], cfg["product_re"], cfg["home"])
            except Exception as e:
                facts["errors"].append(f"product lookup: {str(e)[:120]}")
            dctx.close()
        facts["product_url"] = product
        facts["listing_url"] = cfg["listing"]
        urls = {"home": cfg["home"], "listing": cfg["listing"], "product": product}

        run_shots = [x for x in SHOTS if (x[0].startswith("extra-") if EXTRA else True)]
        for idx, (name, w, h, mobile, kind) in enumerate(run_shots, start=1):
            url = urls.get(kind)
            if not url:
                facts["errors"].append(f"{name}: no url for {kind}")
                continue
            kwargs = dict(viewport={"width": w, "height": h}, locale="en-US")
            kwargs["user_agent"] = MOBILE_UA if mobile else UA
            if mobile:
                kwargs.update(is_mobile=True, has_touch=True, device_scale_factor=1)
            ctx = browser.new_context(**kwargs)
            ctx.add_init_script("Object.defineProperty(navigator,'webdriver',{get:()=>undefined})")
            page = ctx.new_page()
            try:
                page.goto(url, wait_until="domcontentloaded", timeout=45000)
                page.wait_for_timeout(4000)
                dismiss(page)
                page.wait_for_timeout(800)
                out = os.path.join(shots_dir, f"{idx:02d}-{name}.png" if not EXTRA else f"{name}.png")
                page.screenshot(path=out, full_page=False)
                f = visible_facts(page)
                body = page.evaluate("() => (document.body ? document.body.innerText : '').slice(0, 400)")
                f['body'] = body
                f['blocked'] = bool(BLOCK_RE.search(body + ' ' + f.get('title', '')))
                f.update(file=os.path.relpath(out, folder), url=page.url, kind=kind, width=w, height=h, mobile=mobile)
                facts["captured"].append(f)
                print(key, name, "ok", flush=True)
            except Exception as e:
                facts["errors"].append(f"{name}: {str(e)[:140]}")
                print(key, name, "ERR", str(e)[:100], flush=True)
            finally:
                ctx.close()
        browser.close()

    if EXTRA:
        facts["captured"] = [c for c in facts["captured"]]  # appended per run
    with open(os.path.join(folder, "facts.json"), "w") as fh:
        json.dump(facts, fh, indent=1, ensure_ascii=False)
    print(key, "done:", len(facts["captured"]), "screens,", len(facts["errors"]), "errors", flush=True)


if __name__ == "__main__":
    main(sys.argv[1])
