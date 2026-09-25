# infiRewards: smallest v1 progress and checklist

_Updated 25 September 2026. Automated checks ran on 24 September; the store owner reports browser testing on WordPress 7.1._

## The v1 target

The store owner can create an earning rule that says **how many points a customer earns per $1 spent**, and can create a reward with a points cost. A logged-in customer earns points on an eligible purchase, sees their balance and available rewards, and redeems a reward when they have enough points.

To keep this first release small, use one earning rule type and one reward type. A **fixed cart discount WooCommerce coupon** is a practical first reward: the owner sets its discount amount and points cost; redemption issues a customer-specific, single-use coupon. This is the implemented v1 reward type. The existing [architecture plan](v1-architecture-plan.md) describes a wider future scope; registration points, fixed points per order, product rules, percentage/custom rewards, manual adjustments, gifting, and dashboard analytics are not required for this smallest v1.

Define the earning calculation before implementation: use the order's eligible amount in store currency, exclude shipping and taxes, and round the result down to whole points. For example, at 2 points per $1, a $12.75 eligible amount earns 25 points. Stores using a currency other than USD need the label and meaning of “per $1” adapted to **one unit of the store currency**. Award once when an order becomes completed; decide and implement a consistent reversal for cancelled or refunded orders.

## How much is done?

| Requested customer/store-owner flow | Status | Evidence |
| --- | --- | --- |
| Store owner creates a points-per-currency-unit rule | **Automated integration verified; browser tested (user reported)** | The Earning Rule screen saves one storewide rate. Completed logged-in orders earn floor(eligible amount × rate). |
| Store owner creates a reward | **Automated integration verified; browser tested (user reported)** | Rewards are stored in a versioned table and can be created, edited, disabled, and listed in wp-admin. |
| Customer earns points for a purchase | **Automated integration verified; browser tested (user reported)** | Completed logged-in orders use the active rate, with unique earn and reversal event keys. |
| Customer sees points and redeems a reward | **Core redemption integration verified; browser tested (user reported)** | The My Account Points & Rewards tab is enabled by default, and the [infirewards] shortcode remains available anywhere. Both show balance, active rewards, points activity, and redemption history. Redemption reserves points and records a pending coupon for retry. |

**Progress:** The four flows now pass automated integration checks on a disposable WordPress + WooCommerce installation. The store owner reports testing the wp-admin forms and customer flow in a browser on WordPress 7.1. The plugin bootstrap, five table definitions, admin screens, and order event hooks are in place.

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

The customer view appears by default under **My Account → Points & Rewards**. The store owner can turn off that menu entry on the **infiRewards** admin page. The same page displays the optional `[infirewards]` shortcode, which works on any customer-facing page even when the My Account entry is off. Both locations show the balance, available rewards, recent points activity, and coupons. Pending coupon issuance can be retried from either location without another debit.

### 5. Verify the complete path

- [x] On a fresh WordPress + WooCommerce site, activate the plugin, create a rate and reward, complete an order, confirm the exact points calculation, and redeem the reward for a working coupon. Verified through plugin services and WooCommerce coupon validation on a disposable site; the store owner reports that the wp-admin and customer browser flow also passed on WordPress 7.1.
- [x] Check existing-install migration, repeat order hooks, refund/cancellation, insufficient points, disabled rewards, guest orders, double clicks, and two simultaneous redemption attempts.
- [x] Run PHP syntax/style checks and add focused automated tests for earning arithmetic, once-only awarding, ledger/balance consistency, and redemption/retry behavior. PHP syntax and the current composer lint gate pass.

### Verification record (24 September 2026)

The checks in `tests/integration.php` ran against a disposable WordPress database with WooCommerce and infiRewards activated. They covered a 12.75 eligible amount at 2 points per currency unit (25 points after rounding down), repeat completion, customer shortcode output, coupon validation for the owner and rejection for another account, one-use coupon properties, insufficient points, disabled rewards, repeat request keys, pending coupon retry, both reversal policies, guest account assignment, signed ledger reconciliation, and two simultaneous redemption attempts. `tests/migration.php` rebuilt a v1.2 schema in that disposable database and verified schema upgrade, signed debit normalization, balance reconciliation, historical order keys, and repeatable migration.

Run these tests only on a disposable WordPress installation with WooCommerce and infiRewards activated:

```sh
IR_TEST_DISPOSABLE=1 IR_TEST_WP_ROOT=/path/to/disposable/site php tests/integration.php
IR_TEST_DISPOSABLE=1 IR_TEST_SCHEMA_RESET=1 IR_TEST_WP_ROOT=/path/to/disposable/site php tests/migration.php
```

Run the migration script last: it drops and rebuilds the five infiRewards tables and requires a database named `ir_verify_*`. PHP syntax passed for plugin files and tests. The current `composer lint` gate passes. The CLI integration scripts are excluded from the production PHPCS ruleset because they intentionally use direct database and filesystem operations. The store owner reported successful browser checks of the admin forms and customer page on WordPress 7.1 on 25 September 2026. The browser result is user reported; no test log is attached.

The reversal policy is conservative: if any positive point debit occurs after an order earn, its cancellation or full refund records a zero-point waived reversal. This prevents later credits from being taken for spent order points. Guest orders never earn points, including after account creation. Only completed orders earn; only cancelled or fully refunded order statuses reverse.

## Release is done when

A store owner can set a rate and create a fixed discount reward through wp-admin. A logged-in customer earns the correct whole points once from a completed order, sees their balance, and spends points once to receive a usable coupon. Every balance change has a matching transaction, repeat events do not duplicate it, and the fresh-install and existing-install paths both work.
