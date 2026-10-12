# Costco: UX analysis

**Evidence basis:** screenshots 01–15 (viewed: home phone 390). DOM facts from `facts.json`.

## 1. Context first: "Set My Warehouse" and "Set Delivery Location"
- Under the header, the phone home page shows two labelled prompts side by side: "My Warehouse" and "Delivery Location". Both have a clear action.
- **Why:** Costco prices, stock, and pickup depend on the warehouse. Asking for location before showing products avoids showing items the shopper cannot buy.

## 2. Search field beside the Shop button
- The phone header has a labelled "Shop" menu button on the left and a full-width "Search Costco" field, plus Account and Cart icons in the top row.
- **Why:** members who know the product go straight to search. "Shop" gives a category route for browsing. Both are visible at once.

## 3. Promo carousel with numbered dots
- Promotions sit in a carousel with five dots ("Promo 1 of 5" in the accessible names). A "Feedback" tab stays on the right edge.
- **Why:** dots show how many slides exist, which reduces the fear of missing something. The feedback tab is always reachable, which keeps the site feeling responsive.

## 4. Skeleton loading
- The member-deal area shows grey placeholder blocks while data loads.
- **Why:** skeletons keep the layout stable, so the page doesn't jump, and they signal that content is on its way.

## 5. Membership as the value proposition
- The "Member-Only Savings" tile is the largest element in the feature area.
- **Why:** Costco's model depends on membership fees. Making the member benefit prominent supports the business model without a pop-up.

## Lessons for a re-skin
- If stock or price depends on a location, ask for it near the top.
- Keep search full width with a category menu next to it.
- Use skeletons for lazy-loaded blocks.

## What not to copy
- The header is busy on phones: two prompts, a banner, a carousel, and a tab before the first product. Keep the first screen to one prompt and one search.
