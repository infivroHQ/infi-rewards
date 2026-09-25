# infiRewards release checklist

Use this checklist for every release. Check an item only after recording the
version, tester, date, environment, and result in the release PR or ticket.
Fix every failed required check before packaging.

## 1. Define the release

- [ ] Bump the version everywhere it appears: `infirewards.php`,
  `INFIREWARDS_VERSION`, and `readme.txt` Stable tag/changelog.
- [ ] Confirm the plugin header and readme agree on name, slug/text domain,
  minimum WordPress/PHP versions, WooCommerce dependency, license, and version.
- [ ] Set `Tested up to` and WooCommerce compatibility headers only to versions
  tested for this release.
- [ ] Review the changelog, readme description, screenshots, tags (five or
  fewer), and upgrade notes. Remove placeholders and unsupported claims.
- [ ] Confirm the release is complete and usable without a paid license,
  telemetry, remote code, or remote assets.

## 2. Review high-risk code changes

- [ ] Every state-changing admin, AJAX, REST, and `admin-post` action has an
  appropriate capability check. Nonces are used for CSRF protection, never as
  authorization.
- [ ] Request data is allow-listed, validated, and sanitized before use. The
  browser never supplies an authoritative points balance or redemption outcome.
- [ ] Output is escaped for its context when rendered, including database values
  and translated strings.
- [ ] Dynamic SQL uses `$wpdb->prepare()`; table names use the WordPress prefix;
  identifiers/order clauses come from strict allow-lists.
- [ ] Balance-changing operations are server-side, atomic, idempotent, and leave
  an auditable ledger entry. Repeat order hooks and repeat redemption requests do
  not duplicate work.
- [ ] Private REST routes have `permission_callback`; public routes are genuinely
  safe to expose.
- [ ] No request-controlled file operations, unsafe `unserialize()`, `eval()`,
  obfuscated/packed code, or downloaded executable code was introduced.
- [ ] New hooks, functions, options, tables, cron events, and cache keys use the
  `infir_`/`infirewards_` prefix or the `InfiRewards` namespace.
- [ ] No HEREDOC or NOWDOC was introduced.

## 3. Check WordPress/WooCommerce integration

- [ ] Plugin assets are local and enqueue only on relevant screens. Use
  WordPress-provided dependencies instead of bundling or loading CDN copies.
- [ ] User-facing strings use literal gettext calls with the exact `infirewards`
  text domain.
- [ ] Admin and customer views work by keyboard, keep visible focus, have labels
  and semantic controls, announce errors/statuses, and do not rely on colour
  alone.
- [ ] Activation, upgrade, deactivation, and uninstall work as designed.
  Deactivation clears scheduled work but preserves data; uninstall cleanup is
  intentional and protected by `WP_UNINSTALL_PLUGIN` if `uninstall.php` is used.
- [ ] Personal data is limited to what the feature needs. Check access control,
  REST exposure, export/erasure needs, and deletion/uninstall behaviour.
- [ ] Test the declared WordPress, PHP, and WooCommerce versions. If order code
  changed, test WooCommerce HPOS as well.

## 4. Run automated checks

- [ ] Run `composer lint` (or `vendor/bin/phpcs`) and resolve new violations.
- [ ] Run PHP syntax checks for changed PHP files.
- [ ] On a disposable WordPress + WooCommerce site, run:

  ```sh
  IR_TEST_DISPOSABLE=1 IR_TEST_WP_ROOT=/path/to/disposable/site php tests/integration.php
  IR_TEST_DISPOSABLE=1 IR_TEST_SCHEMA_RESET=1 IR_TEST_WP_ROOT=/path/to/disposable/site php tests/migration.php
  ```

- [ ] Run the official Plugin Check against the packaged plugin, including
  `plugin_repo`, security, performance, accessibility, and general checks.
- [ ] Review all suppressions/ignores added or touched in this release; each must
  have a precise, still-valid reason.

## 5. Perform browser acceptance checks

- [ ] Fresh install: activate, configure an earning rate and reward, then verify
  the admin screens save and display correctly.
- [ ] Existing install: upgrade from the previous version and verify migrations
  preserve balances, ledger history, rewards, and settings.
- [ ] Customer flow: a logged-in customer completes an eligible order, earns the
  exact rounded points once, sees the balance, redeems once, and receives a valid
  customer-specific single-use coupon.
- [ ] Edge cases: guest order, repeated order status events, cancellation/full
  refund, insufficient balance, disabled reward, double-click/retry, and two
  concurrent redemptions.
- [ ] Check the My Account endpoint and `[infirewards]` shortcode with the
  account entry enabled and disabled.
- [ ] With `WP_DEBUG` enabled, complete the key flows with no PHP warnings,
  notices, or JavaScript console errors.

## 6. Package and submit

- [ ] Build the exact ZIP to release, install that ZIP on a clean site, and
  repeat the smoke test.
- [ ] Inspect the ZIP: include plugin code, required local assets, licenses, and
  readable source/build instructions for compiled assets; exclude `.git`,
  `node_modules`, development fixtures, credentials, logs, and other junk.
- [ ] Confirm every bundled library, image, font, and package has a compatible
  GPL license and retained notices where required.
- [ ] Confirm no admin advertising is global or persistent; any required notice
  is contextual and dismissible or disappears once resolved. Frontend credits
  are opt-in and off by default.
- [ ] For WordPress.org: verify account 2FA, validate the final `readme.txt`,
  submit the complete ZIP, and allow for the automated release security-review
  cooldown before announcing availability.

Build the release candidate with `scripts/build-release.sh` (requires `xgettext`, `rsync`, and `zip`). It creates
`dist/infirewards-VERSION.zip` with a lowercase `infirewards/` root and a
generated `languages/infirewards.pot`. Run `scripts/generate-pot.sh` to
refresh the repository POT separately. Install and check the final ZIP on the
release test site before completing sign-off.

## Release sign-off

| Release | Date | Tester | Plugin Check | Disposable tests | Browser checks | Approved by |
| --- | --- | --- | --- | --- | --- | --- |
| 0.1.0 | 2026-09-25 | Store owner (reported) | Pending final ZIP | Prior disposable run: 2026-09-24 | Passed on WP 7.1 (user reported) | Pending |

## Reference standards

- [WordPress Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [WordPress security APIs](https://developer.wordpress.org/apis/security/)
- [Plugin Check](https://wordpress.org/plugins/plugin-check/)
- [WooCommerce version support policy](https://developer.woocommerce.com/docs/contribution/contributing/version-support-policy/)
