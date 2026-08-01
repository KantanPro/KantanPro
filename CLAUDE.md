# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

**KantanPro (WP)** — the **free** edition of the KTPWP WordPress plugin (business management: clients, orders/invoices, services, suppliers, staff chat). Installed on a page via the `[ktpwp_all_tab]` shortcode.

This is one of **three separate products** in this workspace — never conflate them:

| Name | Type | Local path | GitHub |
|---|---|---|---|
| **KantanPro (WP)** — this repo | WordPress plugin, **free** | `wordpress/wp-content/plugins/KantanPro` | `KantanPro/KantanPro` |
| **KantanProEX (WP)** | WordPress plugin, **paid** (4 editions: pro/business/team/solo) | `wordpress/wp-content/plugins/KantanProEX` | `KantanPro/KantanProEx` |
| **KantanBiz** | Laravel SaaS | `kantanpro-saas/` | separate product, not WordPress |

Version numbers are on a separate track from the paid version (free `1.2.x` vs paid `1.3.x`+ in past releases). Always say "無料版" explicitly when discussing this repo — "KantanPro" alone is ambiguous with the paid product's shared naming.

## This repo shares a codebase with KantanProEX — know the difference

KantanPro and KantanProEX are near-identical codebases (same class naming, same architecture) forked by feature availability. When porting a fix between them:

- **Feature gating is centralized in `includes/class-ktpwp-edition.php`** — `KTPWP_Edition::get_free_disabled_features()` lists feature keys disabled in this repo: `report`, `backup`, `order_auxiliary`, `stripe_billing`, `contract_invoice_auto_mail`, `public_products`, `contracts`. Check `KTPWP_Edition::is_feature_enabled('...')` before assuming a feature exists here — don't add new UI/AJAX/cron surface for a disabled feature.
- **`staff_limit` semantics differ from paid**: here `staff_limit => 0` means *no additional staff can be added at all* (admin only). In KantanProEX's `pro` edition, `staff_limit => 0` means *unlimited*. Don't copy paid-edition staff-limit logic here assuming `0` means the same thing.
- This repo is missing several classes that exist in KantanProEX's `includes/`: `class-ktpwp-cache.php`, `class-ktpwp-inquiry-block*.php`, `class-ktpwp-list-warning-counts.php`, `class-ktpwp-order-duplicate.php`, `class-ktpwp-public-purchase-thank-you.php`, `class-ktpwp-schema-cache.php`, `class-ktpwp-service-import-export.php`, `class-ktpwp-work-list-schedule.php`. If you're looking for one of those, it's paid-only — don't try to port it in without being asked.
- Bug fixes that apply to shared/core logic (not gated features) often need to land in **both** repos — check whether the user wants a bundled fix across both before assuming this repo alone is in scope.

## Commands

No automated PHPUnit/test suite — verify changes against a local WordPress + WP-CLI environment (`wp-cli-aliases.sh`, see `QUICK-START.md`; `wp-cli.yml` may reference a stale local path, check before relying on it). `create_dummy_data.php` / `wp-cli-create-dummy-data.php` seed sample data for manual testing.

```bash
composer install                 # dev deps: PHPCS + WPCS
composer phpcs                   # WordPress Coding Standards check (phpcs --standard=./.phpcs.xml)

./create_release_zip.sh          # builds release ZIP → ~/Desktop/KantanPro_TEST_UP/
./create_release_zip.sh {version}
```

`.phpcs.xml` excludes `vendor/`, `js/`, `css/`, docs, and various debug/fix scripts from linting.

## Architecture

- **`ktpwp.php`** (~7000+ lines) is the plugin bootstrap: header/version, edition constant definitions (`KTPWP_EDITION`, `KANTANPRO_PLUGIN_NAME` = `KantanPro`), activation/upgrade hooks, admin notices, plus a fair amount of business logic. Check `includes/` too before assuming behavior only lives here.
- **`includes/class-ktpwp-*.php`** (~98 files) holds the feature classes, mostly singletons, one per concern — naming maps directly to feature area (client, order, service, supplier, PDF/print, settings, etc.).
- **`includes/ajax-*.php`** + `class-ktpwp-ajax.php` back the tab UI's dynamic interactions (admin-ajax based, not REST).
- **DB / migrations**: custom lightweight migration system, not a framework ORM. `includes/migrations/*.php` are dated one-off migration scripts run via WP-CLI (`wp ktpwp migrate_all`, registered in `includes/ktp-migration-cli.php`), executed in filename (date) order. Table creation lives in `class-ktpwp-database.php`. New schema changes: add a new dated file under `includes/migrations/`, don't edit old ones.
- **Frontend**: plain per-feature JS in `js/ktp-*.js` (no bundler — enqueued directly via `class-ktpwp-assets.php`, conditionally per-screen). CSS similarly per-feature under `css/`.

## Conventions

**Trimmed decimal display** — never show a trailing `.00`/`.50` unless the user actually entered a fraction (`50000.00` → `50000`, `10.5` stays `10.5`):
- PHP: `KTPWP_Settings::format_money()` (money), `format_decimal_trimmed()` (rates/quantities/unit prices), `format_number_field_value()` (`<input type="number">` value). Never use raw `number_format($x, 2)` or cast a DB `DECIMAL` straight to string for display.
- JS: `KTPNumberFormat.decimal(value)` / `formatDecimalDisplay(value)` from `js/ktp-number-format.js`. Never hardcode `.toFixed(2)` for display.
- Exceptions: file sizes, chart percentages, other deliberately-fixed-decimal UI. Internal calculations/DB storage stay float/decimal — only the display layer trims.

**WordPress conventions**: favor hooks (actions/filters) over touching core; sanitize input / escape output; use `$wpdb->prepare()` for queries; nonce-verify form submissions and AJAX handlers; use `wp_enqueue_script`/`wp_enqueue_style` (never inline `<script>`/`<link>` for plugin assets); schema changes go through the migration system above, not ad-hoc `dbDelta()` calls scattered around.

**Docker/local env**: don't modify Docker, MySQL, or `wp-config.php` settings as a side effect of a feature change.

## Releases

Ships as a **single ZIP** via `./create_release_zip.sh`, released to GitHub `KantanPro/KantanPro`. Full step-by-step is in `.cursor/rules/release-workflow.mdc` — read it before improvising. Key points:
- Version bump locations: `ktpwp.php` `Version` header, `readme.txt` `Stable tag` + Japanese changelog, **and `plugin_config.json`'s `default_version`** (easy to miss — the ZIP script reads it).
- ZIP **must include** `create_dummy_data.php` (opposite of the paid repo, which excludes it) and must be under 3MB with a single root folder named `KantanPro`.
- A release request often covers **both products** (this repo's single ZIP + KantanProEX's 4 ZIPs) unless the user explicitly says only one side — see KantanProEX's `.cursor/rules/release-workflow-bundle.mdc` for the combined flow. Don't silently finish only the paid side of a bundled request.
- Report back: version (old → new), GitHub Release URL, ZIP path, extracted folder name, commit hash.

## Commit messages

Always write commit messages in Japanese, concise form like `〇〇を追加` / `〇〇を修正` / `〇〇のバグを修正` — never English one-liners (`fix: foo`). Applies to Cursor SCM/agent-generated messages too.

## Workflow

For multi-step implementation work (a roadmap, continuing a previously discussed design, through tests/docs/commit), keep going through the reasonable full sequence without stopping to ask "should I continue?" — only pause to confirm on a genuine fork (mutually exclusive choices, or a large design change).
