# infiRewards v1 architecture plan

## Goal

infiRewards lets store owners create earning rules and rewards. Customers earn points from eligible actions and spend them on rewards. Store owners can also adjust points or gift a reward directly to a customer.

This document describes the proposed **v1 target**. The repository now places its active classes under `src/` using the minimal v1 layout below. The layout defines responsibilities; it does not imply that every planned v1 feature is implemented. A separate layout shows how the project could grow later.

## Core lifecycle

1. The store owner creates an earning rule and a reward.
2. A customer completes an eligible action.
3. The plugin evaluates active rules and records awarded points.
4. The customer sees their balance and available rewards.
5. The customer redeems a reward. The plugin validates eligibility, records the points deduction, and issues the reward.
6. An administrator can adjust a customer's points or gift a reward directly.

## Functional scope

### Earning rules

Start with a small set of explicit rule types:

| Type | Behavior |
| --- | --- |
| Completed purchase | Award a fixed number of points per eligible order. |
| Amount spent | Award points according to an amount-to-points ratio. |
| Account registration | Award a fixed number of points once per customer. |

Keep a stable `type` field and type-specific configuration so more rule types can be added later. A conceptual rule record is:

```php
[
    'id'     => 12,
    'type'   => 'purchase_amount',
    'points' => 1,
    'amount' => 1,
    'status' => 'active',
]
```

### Rewards

Support fixed discounts, percentage discounts, coupons, and manual or custom rewards. Each reward has a points cost, status, and type-specific value or configuration. For example:

```php
[
    'id'          => 8,
    'name'        => '10% Off',
    'type'        => 'percentage_discount',
    'points_cost' => 500,
    'value'       => 10,
    'status'      => 'active',
]
```

Redemption must check that the reward is active, the customer is eligible, and the customer has enough points before deducting points and issuing the reward. A gifted reward follows its own admin action and does not require the customer to redeem points.

## Points ledger and data

Every balance change must create a points transaction. The ledger is the source of truth for a customer's balance and history. A transaction should contain at least:

```text
id
user_id
points             signed amount: positive for credit, negative for debit
type               e.g. earn, redeem, admin
reference_type     e.g. order, signup, redemption, manual
reference_id
description
created_at
```

Example activity:

```text
101 | 42 | +100 | earn   | order      | 5821 | Order #5821
102 | 42 | +50  | earn   | signup     | 42   | Registration bonus
103 | 42 | -500 | redeem | redemption | 77   | 10% discount
104 | 42 | +200 | admin  | manual     | NULL | Admin adjustment
```

Use custom tables, with the WordPress table prefix applied at runtime:

```text
{prefix}infirewards_rules
{prefix}infirewards_rewards
{prefix}infirewards_points
{prefix}infirewards_redemptions
```

The current code already defines wallet, transaction, and rule tables. The proposed schema should be reconciled with those tables before migration or new table creation. A cached balance, whether in the existing wallet table or user meta such as `_infirewards_points_balance`, is optional; it must be recoverable from the ledger.

## Component responsibilities

Keep the implementation small and domain-focused:

| Component | Responsibility |
| --- | --- |
| Plugin bootstrap (`Plugin.php`) | Load components and register WordPress hooks. |
| Points | Add, subtract, query, and adjust points through the ledger. |
| Rules | Determine whether an action earns points and how many. |
| Rewards | Create, update, list, and check reward eligibility; validate redemptions, deduct points, and issue rewards. |
| Database | Own table setup, migrations, and data access for rules, rewards, points, and redemptions. |
| Admin | Menus, forms, and administrator actions. |
| WooCommerce integration | Connect store events, customer account views, and reward delivery to the domain components. |

The proposed minimal `src/` layout is:

```text
src/
├── Plugin.php
├── Admin/
├── Points/
├── Rules/
├── Rewards/
├── Database/
└── Integrations/
    └── WooCommerce/
```

Customer balance and redemption interfaces can live with their owning domain components; WooCommerce-specific account integration belongs in `Integrations/WooCommerce/`. The target is a simple domain split. Empty sections, such as `Rewards/`, can be filled as their v1 features are implemented.

## Later expansion layout

When the project grows, the repository can move toward this fuller layout:

```text
infirewards/
├── infirewards.php        # Main plugin bootstrap
├── uninstall.php          # Permanent cleanup
├── readme.txt             # WordPress.org readme
├── composer.json
│
├── assets/
│   ├── css/
│   └── js/
│
├── languages/             # Translations
│
├── src/
│   ├── Plugin.php         # Starts/registers plugin services
│   │
│   ├── Domain/            # Core business logic
│   │   ├── Points/
│   │   ├── Rules/
│   │   └── Rewards/
│   │
│   ├── Admin/             # wp-admin screens/actions
│   │   ├── Screens/
│   │   └── Actions/
│   │
│   ├── Infrastructure/    # Database + WordPress plumbing
│   │   ├── Database/
│   │   └── Persistence/
│   │
│   ├── Integrations/      # WooCommerce etc.
│   │   └── WooCommerce/
│   │
│   ├── Frontend/          # Customer-facing UI
│   ├── REST/              # API endpoints if needed
│   └── Privacy/
│
└── tests/
```

This is a later organization option, not a v1 requirement. Move the v1 `Points/`, `Rules/`, and `Rewards/` components under `Domain/` and `Database/` under `Infrastructure/` only when their size warrants it. Add `Frontend/`, `REST/`, and `Privacy/` as those responsibilities need dedicated code; a public REST API remains outside v1.

## User interfaces

### Store owner

The v1 menu has four pages:

- **Dashboard:** basic totals.
- **Earning Rules:** create and manage supported earning rules.
- **Rewards:** create and manage redeemable rewards.
- **Customers:** show each customer's balance, lifetime earned and redeemed totals, and recent activity; provide **Adjust Points** and **Gift Reward** actions.

### Customer

A single shortcode or block can show the current balance and available rewards with a **Redeem** action. Recent points activity can be added to the same view if practical for v1. A separate customer dashboard is unnecessary for this release.

## Outside v1

Defer referral programs, VIP tiers, points expiry, birthday rewards, badges, a public REST API, a generic conditional rule builder, email automation, POS integration, multisite support, import/export, and analytics beyond basic totals.

## Release checklist

- Supported actions award points once and write ledger entries.
- Redemptions validate eligibility, deduct points once, and issue the selected reward.
- Manual adjustments and gifted rewards appear in customer activity.
- Admin pages cover rules, rewards, customers, and basic totals.
- Customers can see their balance and redeem available rewards.
- Cached balances can be rebuilt from the ledger.
