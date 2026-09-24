# infiRewards: smallest v1 progress and checklist

_Code review: 24 September 2026. This records what is present in the repository, not a verified WordPress/WooCommerce run._

## The v1 target

The store owner can create an earning rule that says **how many points a customer earns per $1 spent**, and can create a reward with a points cost. A logged-in customer earns points on an eligible purchase, sees their balance and available rewards, and redeems a reward when they have enough points.

To keep this first release small, use one earning rule type and one reward type. A **fixed cart discount WooCommerce coupon** is a practical first reward: the owner sets its discount amount and points cost; redemption issues a customer-specific, single-use coupon. This reward choice is a proposed v1 decision, not functionality already implemented. The existing [architecture plan](v1-architecture-plan.md) describes a wider future scope; registration points, fixed points per order, product rules, percentage/custom rewards, manual adjustments, gifting, and dashboard analytics are not required for this smallest v1.

Define the earning calculation before implementation: use the order's eligible amount in store currency, exclude shipping and taxes, and round the result down to whole points. For example, at 2 points per $1, a $12.75 eligible amount earns 25 points. Stores using a currency other than USD need the label and meaning of “per $1” adapted to **one unit of the store currency**. Award once when an order becomes completed; decide and implement a consistent reversal for cancelled or refunded orders.

## How much is done?

| Requested customer/store-owner flow | Status | Evidence |
| --- | --- | --- |
| Store owner creates a points-per-currency-unit rule | **Implemented in code; unverified in WooCommerce** | The Earning Rule screen saves one storewide rate. Completed logged-in orders earn floor(eligible amount × rate). |
| Store owner creates a reward | **Implemented in code; unverified in WooCommerce** | Rewards are stored in a versioned table and can be created, edited, disabled, and listed in wp-admin. |
| Customer earns points for a purchase | **Implemented in code; unverified in WooCommerce** | Completed logged-in orders use the active rate, with unique earn and reversal event keys. |
| Customer sees points and redeems a reward | **Implemented in code; unverified in WooCommerce** | The [infirewards] shortcode shows balance, active rewards, points activity, and redemption history. Redemption reserves points and records a pending coupon for retry. |

**Progress:** 0 of the 4 requested end-to-end flows are verified; all four flows are implemented in code. The plugin bootstrap, four table definitions, admin screens, and order event hooks are in place. The remaining work is end-to-end verification. This is a feature count, not an estimate of time or effort.

## Implementation checklist

Checkboxes marked complete describe code that exists, even if the surrounding flow is incomplete. Work through the remaining items in roughly this order.

### 1. Make points storage trustworthy

- [x] Load plugin classes, create initial wallet/transaction/rule tables on activation, and register WooCommerce order hooks.
- [x] Reconcile the installed schema with the runtime code: use one wallet balance column name and one transaction shape everywhere. Add a migration for sites that already activated the plugin.
- [x] Make every credit/debit and its ledger entry succeed or fail together. Prevent a negative balance and make the ledger sufficient to explain/rebuild a balance.
- [x] Record enough transaction context to distinguish order earns, reversals, and redemptions. Add uniqueness/idempotency protection for an order earn and a redemption.

### 2. Let the owner configure the single earning rule

- [x] Create a rules table and a class that loads rules.
- [x] Choose one stored rule format (rate, active status), validate a nonnegative rate, and implement create/edit/disable in an admin **Earning Rule** screen. For the smallest v1, one active rate for the store is sufficient.
- [x] Replace the current fixed per-order/per-product calculation with `floor(eligible order amount × points per currency unit)`. Read the saved active rule and document the amount and rounding policy in the UI.
- [x] Award only for a logged-in customer's completed order, and only once even if an order changes status repeatedly. Define the treatment of guest orders.
- [x] Make cancellation/refund reversals happen only once and never deduct unrelated credits. Handle a customer who has already spent the earned points with a documented policy.

### 3. Let the owner create a redeemable reward

- [x] Add reward persistence with at least name, fixed discount amount, points cost, and active status. Include creation/update handling for existing installations.
- [x] Add an admin **Rewards** screen to create, edit, disable, and list rewards. Validate amounts and costs; use capability checks, nonces, sanitization, and escaped output.

### 4. Let the customer redeem

- [x] Show a logged-in customer their current points balance and active rewards, including each reward's cost and whether they can redeem it. A WooCommerce My Account section or a shortcode is enough.
- [x] On redemption, verify login, reward status, and sufficient points on the server. Ensure concurrent/repeated requests cannot spend the same points twice.
- [x] Create a redemption record linked to the customer, reward, points debit, and issued coupon. Issue a customer-specific, single-use fixed cart discount coupon; make a retry safe if coupon creation fails after a debit.
- [x] Show the issued coupon and a clear success/error message. Keep a basic points/redemption history so the customer can see what happened.

Place `[infirewards]` on a customer-facing page to show the balance, available rewards, recent points activity, and coupons. Pending coupon issuance can be retried from that page without another debit.

### 5. Verify the complete path

- [ ] On a fresh WordPress + WooCommerce site, activate the plugin, create a rate and reward, complete an order, confirm the exact points calculation, and redeem the reward for a working coupon.
- [ ] Check existing-install migration, repeat order hooks, refund/cancellation, insufficient points, disabled rewards, guest orders, double clicks, and two simultaneous redemption attempts.
- [ ] Run PHP syntax/style checks and add focused automated tests for earning arithmetic, once-only awarding, ledger/balance consistency, and redemption/retry behavior.

The reversal policy is conservative: if any positive point debit occurs after an order earn, its cancellation or full refund records a zero-point waived reversal. This prevents later credits from being taken for spent order points. Guest orders never earn points, including after account creation. Only completed orders earn; only cancelled or fully refunded order statuses reverse.

## Release is done when

A store owner can set a rate and create a fixed discount reward through wp-admin. A logged-in customer earns the correct whole points once from a completed order, sees their balance, and spends points once to receive a usable coupon. Every balance change has a matching transaction, repeat events do not duplicate it, and the fresh-install and existing-install paths both work.
