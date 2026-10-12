# Design references: top e-commerce UX/UI

This folder holds screenshots and UX analysis of ten e-commerce sites. It is for internal design research. Do not copy the designs. Use the analysis to decide what problem an element solves and whether your product needs it.

## Ranking method

The ranking uses customer satisfaction scores, not user count and not expert opinion alone.

- **Source:** American Customer Satisfaction Index (ACSI), Retail and Consumer Shipping Study, 27 January 2026. ACSI surveys about 200,000 consumers a year. Its online-retailer scores include website satisfaction and ease of navigation as customer experience drivers. ACSI measures overall satisfaction, not UX/UI alone, so treat the ranking as a proxy for user-rated experience.
- **Tie rule:** where sites share a score, they are listed in folder order. Ties at 73 (Apple, Gap, Walgreens) were all included, because the list has ten slots and all three were accessible.

| # | Site | ACSI 2026 | Folder | Clean screens |
|---|---|---|---|---|
| 1 | Amazon | 82 | `01-amazon/` | 15 |
| 2 | Costco | 79 | `02-costco/` | 15 |
| 3 | Walmart | 77 | `03-walmart/` | 12 |
| 4 | Best Buy | 77 | `04-best-buy/` | 12 |
| 5 | Nike | 76 | `05-nike/` | 15 |
| 6 | Target | 75 | `06-target/` | 12 |
| 7 | Home Depot | 75 | `07-home-depot/` | 12 |
| 8 | Apple Store | 73 | `08-apple/` | 15 |
| 9 | Gap | 73 | `09-gap/` | 15 |
| 10 | Walgreens | 73 | `10-walgreens/` | 14 |

## Sites in the top 10 that were not captured

| Site | ACSI 2026 | Reason |
|---|---|---|
| Chewy | 82 | Served a 403 page to the capture browser, even with a desktop and mobile profile. |
| Nordstrom | 82 | Served a block page: "We've noticed some unusual activity". The page says it does not allow automated traffic. Captured screens were removed. |
| Macy's | 79 | Access denied to the capture browser. |
| eBay | 78 | Error page for the capture browser. |
| Etsy | 77 | Blank or error page for the capture browser. |
| Wayfair | 76 | Access denied to the capture browser. |
| Bed Bath & Beyond | 74 | Captured screens were error pages. Removed. |

The sites above were not replaced by lower-ranked sites that also failed. Sites were added in score order, with Gap and Walgreens filling the last places.

## Screen coverage

Each folder holds 12 to 15 clean viewport screenshots (PNG), in these viewports:

| Viewport | Aspect ratio | Use |
|---|---|---|
| 1440×900 | 8:5 | Desktop home, listing, product |
| 1920×1080 | 16:9 | Wide desktop home |
| 2560×1080 | 64:27 (≈21:9) | Ultrawide home |
| 1366×768 | 683:384 (≈16:9) | Common laptop |
| 768×1024 | 3:4 | Tablet portrait |
| 1024×768 | 4:3 | Tablet landscape |
| 360×740 | 9:18.5 | Small Android phone |
| 390×844 | ≈9:19.5 | iPhone |
| 430×932 | ≈9:19.5 | Large phone |
| 412×915, 834×1194, 1280×800 | Various | Extra home views on some sites |

Each folder has `screens/`, `facts.json` (what was visible above the fold, such as button labels, search placeholders, sticky header, and fixed bottom bar), `README.md` (screen table), and `analysis.md` (UX analysis).

## Limits of this research

- Captures came from one sandbox IP, with a headless Chromium. Some sites served bot checks or error pages, and those screens were removed. Some product pages were not captured: Target, Gap, and Best Buy (intermittent), and some listing pages.
- No logged-in pages, carts, or checkouts were captured. Those are where a lot of UX differences show up, so the analysis is weaker on checkout.
- The analysis separates two kinds of evidence. Amazon, Nike, Apple, and Costco were reviewed visually. For Walmart, Best Buy, Target, Home Depot, Gap, and Walgreens, the notes are based on DOM facts, and their `analysis.md` says so.
- The "why" notes are interpretation based on common UX principles, such as Hick's law, F-pattern scanning, and thumb-zone reach. They are not statements from the companies.

## Re-running the capture

`_tools/capture.py <folder>` reruns one site. It only visits public pages and does not log in, add to a cart, or check out. The browser install lives in `_tools/lib`, and `_tools/libpath` holds system libraries. Both are recreated with `pip install --target` and `playwright install chromium`, as described in the storefront report.

## Licence and use

Screenshots show third-party sites. Keep them in this internal reference folder and do not publish or redistribute them.
