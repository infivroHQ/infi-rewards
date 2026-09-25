# Competitor Design Findings

Comparison of six loyalty plugins (WPLoyalty, WP Swings, LoyaltyX, Simple Points & Rewards, XT Points & Rewards, GamiPress/myCred) against infiRewards v1.

## Key takeaway

The best customer page (Simple P&R) and the best admin dashboard (LoyaltyX) share one lesson infiRewards is missing: show what points are worth in money, what the customer can redeem right now, and let them apply it to the cart in one click. Most competitors just show a balance with no next step.

## Best ideas worth copying

- **Simple P&R:** redeemed reward becomes a card with coupon code + Copy + "Apply to cart" button — best single idea in the set.
- **Simple P&R:** hero balance + stat tiles + a progress bar toward the next unlockable reward.
- **LoyaltyX (admin):** KPI tiles + a points-earned-vs-redeemed line chart with date range filter.
- **WPLoyalty:** "Ways to earn" cards built from active earning rules.

## Weak points to avoid

- WP Swings / XT: plain text balance, no visual hierarchy, no clear CTA.
- Nobody but LoyaltyX has an admin dashboard — infiRewards has none either, just raw settings forms.
- Nobody exposes an "adjust customer points manually" admin UI, despite it being a common ask.

## Gaps in infiRewards v1

- No My Account balance hero, no rewards grid, no points-to-money conversion shown to the customer.
- No admin dashboard (KPI tiles, charts, recent activity).
- No per-customer admin view (balance, manual adjust, gift reward, history).

## Revised direction for v1 (scope check on the ideas above)

Competitor ideas are useful, but v1 should look like a **native WooCommerce feature**, not a SaaS dashboard. Concretely:

- **Admin nav:** 5 sections max — `Overview · Earning Rules · Rewards · Customers · Settings`. No custom sidebar.
- **Overview:** no chart yet. 3–4 stat cards (points issued/redeemed/outstanding, rewards redeemed) + a "Getting started" checklist when setup is incomplete + last 5 transactions. The LoyaltyX chart idea above is good but is a v2 candidate once there's enough data/usage to justify it — don't build it first.
- **Rules & Rewards:** same pattern for both — native WP table (list) → white-card form (add/edit), only relevant fields shown per type.
- **Customers:** searchable table (balance, total earned, last activity) → detail view with balance, **Adjust points** (reason required) and **Gift reward**, then history.
- **Settings:** 2–3 cards (General / Customer display / Advanced), one persistent Save button — not one giant form table.
- **Visual rules (admin):** normal wp-admin background + white cards, 1px `#dcdcde` border, 6–8px radius, no heavy shadows. Primary actions use `.button-primary` / `var(--wp-admin-theme-color, #2271b1)` — no hard-coded plugin brand color in wp-admin. Status as small pills, not color alone. No logo/banner/promo blocks in the UI.
- **My Account:** exactly **one endpoint** ("Points & Rewards"), no nested tabs. Order: balance hero (points + $ value + progress bar to next reward) → Rewards grid (3/2/1 columns; unavailable rewards stay readable, only the CTA disables) → Ways to earn (compact icon+sentence rows) → Recent activity table (~5 rows + "View all" if needed). Redeemed coupon reward shows code + Copy + Apply to cart, matching the Simple P&R idea above.
- **Visual rules (customer):** blend into the active theme — theme fonts/buttons, store's primary color for Redeem, not wp-admin blue. Balance ~32–40px, headings ~20–24px, body 14–16px, buttons ≥40px, mobile-responsive.
- **Explicitly dropped from earlier proposal:** no admin accent-color setting (would fight the shop theme), no admin chart dashboard in v1. Effort goes to Rules/Rewards/Customer adjustment/redemption UX/empty states/validation/responsiveness first — that's what makes v1 feel finished.

Full page-by-page design spec (exact copy, field lists, form sections) to be written up separately when implementation starts; this file captures findings + the agreed v1 scope, not the full spec.
