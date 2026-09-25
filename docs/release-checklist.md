# infiRewards release checklist

Use this checklist for every release. Check an item only after recording the
version, tester, date, environment, and result in the release PR or ticket.
Fix every failed required check before packaging.

## 1. Define the release

- [ ] Bump the version everywhere it appears: `infi-rewards.php`,
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
- [ ] User-facing strings use literal gettext calls with the exact `infi-rewards`
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

- [ ] Run `composer lint` and `composer lint:report`. Review warnings as well as
  errors: the lint gate ignores warnings, and the project ruleset disables some
  sniffs that Plugin Check enables.
- [ ] Preflight translated strings with placeholders. Put a `translators:` note
  immediately above each gettext call and explain every placeholder. Check
  strings embedded in HTML/PHP output too. Run
  `vendor/bin/phpcs --standard=WordPress --sniffs=WordPress.WP.I18n src/`.
- [ ] Review each `$_GET`/`$_POST` read. State-changing handlers must verify a
  nonce and capability before acting. For read-only navigation, notices, and
  filters, sanitize and allow-list values; use a narrow, reasoned PHPCS ignore
  only where the nonce warning is a false positive. Run
  `vendor/bin/phpcs --standard=WordPress --sniffs=WordPress.Security.NonceVerification src/`.
- [ ] Audit custom-table SQL: prepare request-controlled values, derive table
  names from the WordPress prefix, and allow-list dynamic identifiers and sort
  clauses. Review direct-query, caching, and schema warnings against the actual
  operation; avoid caching transaction/locking reads or hiding warnings broadly.
- [ ] Keep the `readme.txt` short description at 150 characters or fewer and
  validate the readme before Plugin Check.
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
  submit the complete ZIP, and confirm the assigned slug is `infi-rewards`
  before approval. Adjust it through the submission process if needed; the
  public slug determines the text domain and cannot be renamed after approval.
  Allow for the automated release security-review cooldown before announcing
  availability.

Build the release candidate with `scripts/build-release.sh` (requires `xgettext`, `rsync`, and `zip`). It creates
`dist/infi-rewards-VERSION.zip` with a lowercase `infi-rewards/` root and a
generated `languages/infi-rewards.pot`. Run `scripts/generate-pot.sh` to
refresh the repository POT separately. Install and check the final ZIP on the
release test site before completing sign-off.

## 0.1.0 audit snapshot (2026-09-25)

Tester: Codex. Environment: local source and the rebuilt
`dist/infi-rewards-0.1.0.zip`; no disposable WordPress site was used in this
pass. This is a checkpoint, not release sign-off.

- The supplied ZIP report listed 267 warnings (the request said 257). The
  matching WordPress database sniffs now report 245 on the rebuilt ZIP:
  105 direct-query, 98 no-caching, 40 table-name interpolation, and 2 schema
  change warnings. The 22 `PluginCheck.Security.DirectDB.UnescapedDBParameter`
  warnings were resolved by preparing the customer search queries and explicitly
  escaping internal migration table names.
- Project PHPCS warnings fell from 291 to 205. PHPCBF fixed 84 alignment
  warnings; the other two non-database warnings were fixed manually. The
  remaining 205 are the direct-query, no-caching, and schema warnings above.
- Three CSS files initially failed Plugin Check's bundled PHPCS rules as
  minified/unreadable. All four packaged CSS files were formatted. The rebuilt
  ZIP passes those bundled Plugin Check PHPCS rules with zero findings.
- `composer lint`, PHP syntax checks for packaged source, the WordPress CSS
  PHPCS check, and `git diff --check` passed. The ZIP was rebuilt after the
  CSS changes. Its SHA-256 is
  `3cf006a79ec1b998111536aec18e966aa715259282def3fb57a6a07ab9cfc9fc`.
- WordPress 6.0 remains the declared minimum. `%i` identifier placeholders
  require WordPress 6.2, so the 40 table-name interpolation advisories remain.
  Review the minimum-version choice before changing these queries. Do not cache
  wallet locks, balance reads, or other transactional operations solely to
  silence the 98 no-caching warnings.
- Next: run the full Plugin Check application on the exact ZIP, then install it
  on a disposable WordPress/WooCommerce site and rerun the integration,
  migration, and browser checks. The bundled PHPCS pass does not replace these
  checks or WordPress.org review.

## Release sign-off

| Release | Date | Tester | Plugin Check | Disposable tests | Browser checks | Approved by |
| --- | --- | --- | --- | --- | --- | --- |
| 0.1.0 | 2026-09-25 | Store owner (reported); Codex audit | Bundled PHPCS rules passed on rebuilt ZIP; full app pending | Prior disposable run: 2026-09-24; rerun pending | Passed on WP 7.1 (user reported); rerun pending | Pending |

## Reference standards

- [WordPress Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [WordPress security APIs](https://developer.wordpress.org/apis/security/)
- [Plugin Check](https://wordpress.org/plugins/plugin-check/)
- [WooCommerce version support policy](https://developer.woocommerce.com/docs/contribution/contributing/version-support-policy/)
