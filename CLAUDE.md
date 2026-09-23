# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is **CHIP for GiveWP**, a WordPress plugin that adds CHIP (Malaysian payment gateway) as a donation payment method for the GiveWP plugin. It is a pure PHP plugin with no frontend build system, but it now includes Composer-based dev tooling, PHPUnit tests, and GitHub CI/CD.

## Architecture

### Dual Gateway Support
The plugin supports both **GiveWP legacy forms** (pre-v3) and **GiveWP 3.0 Visual Form Builder** (block-based) through two separate gateway implementations:

- **Legacy gateway**: `chip` — handled by `includes/class-purchase.php` (action `give_gateway_chip`)
- **Block gateway**: `chip_block` — handled by `includes/block/class-chip-gateway.php` (extends `Give\Framework\PaymentGateways\PaymentGateway`)

Both gateways share the same backend logic (API calls, listener, settings) but have separate frontend registration flows.

### Core Components

- **`chip-for-givewp.php`** — Main plugin file. Bootstraps all includes and registers the legacy gateway via `give_payment_gateways` filter.
- **`includes/class-chip-givewp-api.php`** — `Chip_Givewp_API`. Wraps CHIP REST API (`https://gate.chip-in.asia/api/v1`). **Important**: `get_instance()` is a **keyed singleton** — instances are cached by `md5($secret_key . '|' . $brand_id)` so the same credentials reuse one instance, but different credentials get separate instances. Added methods: `get_company_uid()`, `cancel_payment()`. `request()` returns `null` on `WP_Error` or non-2xx status codes.
- **`includes/class-chip-givewp-purchase.php`** — `Chip_Givewp_Purchase`. Handles legacy form donation creation and redirect to CHIP checkout.
- **`includes/block/class-chipgateway.php`** — `ChipGateway`. Handles block-form donation creation. Also implements `refundDonation()` for block-form refunds.
- **`includes/class-chip-givewp-listener.php`** — `Chip_Givewp_Listener`. Handles CHIP callback/webhook (`handle_callback`) and customer redirect (`handle_redirect`). Verifies webhook signatures via `openssl_verify` using a **company-UID-based cached public key** (`gwp_chip_public_key_{company_uid}`). On verification failure, falls back to fetching payment status via the API. Uses MySQL `GET_LOCK`/`RELEASE_LOCK` (verified with `get_var()`) to prevent duplicate payment processing.
- **`includes/class-chip-givewp-helper.php`** — `Chip_Givewp_Helper`. Static utilities for form settings and logging via `Give\Log\LogFactory`.
- **`includes/admin/`** — Settings UI:
  - `class-chip-givewp-admin-settings.php` — Base settings field definitions (shared by global and per-form settings).
  - `class-chip-givewp-admin-global-settings.php` — Global CHIP settings under GiveWP Settings → Gateways → CHIP.
  - `class-chip-givewp-admin-metabox-settings.php` — Per-form CHIP settings tab in the form editor.
  - `class-chip-givewp-refund-button.php` — Adds a manual refund button to the donation details admin page.
- **`includes/block/chip-givewp-block.php`** — `Chip_Givewp_Block`. Bootstraps the block gateway by registering `ChipGateway` with GiveWP's `PaymentGatewayRegister`, and enqueues the form-builder sidebar script.
- **`includes/block/js/chip-gateway.js`** — Minimal React-based frontend for block forms. Registers `window.givewp.gateways`.
- **`includes/block/Actions/class-chip-givewp-enqueue-form-builder-scripts.php`** — `Chip_Givewp_Enqueue_Form_Builder_Scripts`. Enqueues a sidebar-link script in the GiveWP Visual Form Builder pointing to per-form CHIP settings.
- **`includes/block/resources/form-builder/chip-gateway-form-builder.js`** — Form-builder sidebar script that adds a "CHIP Settings" link to the per-form settings tab.
- **`includes/js/metabox.js`** — Toggles per-form CHIP settings visibility in the legacy form editor.
- **`includes/js/refund.js`** — AJAX handler for the admin refund button.
- **`.wp-env.json`** — Local WordPress environment config (GiveWP + plugin-check).
- **`.wordpress-org/`** — WordPress.org plugin directory assets (banners, icons, screenshots).
- **`.github/workflows/`** — GitHub Actions CI/CD:
  - `plugin-check.yml` — Runs the WordPress.org plugin check (PHP 8.2 + plugin-check-action@v1.1.7), PHPCS, PHPCompatibility, PHPUnit, and the plugin build. All four test jobs (`php-compatibility`, `phpcs`, `phpunit`, `plugin-check`) gate on the `build` job's artifact. The `plugin-check` job passes `ignore-codes: trademarked_term` and `…NonPrefixedHooknameFound` (both are documented false positives for this plugin — see the comments in that file).
  - `prepare-release.yml` — Manual dispatch (`workflow_dispatch`, input: `version`). Validates the version, generates an AI changelog from the diff since the last tag (uses `AI_API_KEY` / `AI_MODEL` / `AI_API_URL`), runs `scripts/bump-version.sh`, then opens a `release/vX.Y.Z` PR to `main`. Tag creation is still manual after the PR merges.
  - `release-zip.yml` — Fires on `release: created`. Builds a clean `dist/chip-for-givewp/` snapshot and uploads `chip-for-givewp.zip` to the GitHub release as an asset. The WordPress.org deploy workflow is responsible for the trunk/tag push.
  - `deploy.yml` — Fires on `v*.*.*` or `*.*.*` tag push, or manual dispatch with `trunk`/`release` stage. Runs in the `wordpress-org` environment using `SVN_USERNAME` / `SVN_PASSWORD` secrets. For `trunk`, reverts `Stable tag` to whatever is currently live on WordPress.org so the testing build doesn't auto-update users; for `release`, commits to trunk, copies trunk → `tags/X.Y.Z`, and creates/updates the matching GitHub release. Excludes `.git*`, `.github`, `.vscode`, `ci-build`, `dist`, `node_modules`, `.wordpress-org` from the deploy.
  - `pr-summary.yml` — Fires on PR `opened`/`synchronize`. Generates an AI summary of the PR diff and overwrites the PR description (idempotent — re-runs replace the previous summary).

### Settings Model
CHIP settings exist at two levels:
1. **Global**: Stored via `give_update_option()` / `give_get_option()`.
2. **Per-form**: Stored via `give_update_meta()` / `give_get_meta()` with `_give_` prefix when customization is enabled.

The `Chip_Givewp_Helper::get_fields()` / `update_fields()` methods abstract this dual storage. Additional fields added in v1.3.0: `chip-due-strict`, `chip-due-strict-timing` (default 60), `chip-cancel-url`.

## Key Code Patterns

- All PHP files begin with `defined( 'ABSPATH' ) || exit;`.
- Classes use a pseudo-singleton pattern (`get_instance()`) but the API class intentionally breaks this.
- GiveWP functions are used extensively: `give_get_option`, `give_get_meta`, `give_is_setting_enabled`, `give_insert_payment`, `give_update_payment_status`.
- Currency is hardcoded to **MYR** only.

## Development Notes

- **No frontend build step**: Edit PHP/JS files directly.
- **Composer dev dependencies**: `phpunit/phpunit`, `10up/wp_mock`, `yoast/phpunit-polyfills`, `squizlabs/php_codesniffer`, `dealerdirect/phpcodesniffer-composer-installer`, `wp-coding-standards/wpcs`.
- **Formatting**: VS Code workspace setting uses `"php.format.codeStyle": "WordPress"`. PHPCS is configured for WordPress standards via `phpcs.xml`.
- **Minimum PHP**: 7.4 (as of v1.3.0).
- **Minimum WordPress**: 6.3 (as of v1.3.0).
- **Tested up to**: WordPress 7.0 (as of v1.3.0).

## Common Commands

```bash
# Install dependencies
composer install --no-interaction --prefer-dist

# Run all tests
./vendor/bin/phpunit

# Run a single test class
./vendor/bin/phpunit --filter Chip_Givewp_APITest

# Run a single test method
./vendor/bin/phpunit --filter test_create_payment_returns_decoded_response

# PHPCS (WordPress standards)
phpcs --standard=phpcs.xml .

# PHP compatibility (single version)
phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 8.5 --extensions=php --ignore=vendor,node_modules,assets .
```

## Common Changes

- To modify gateway settings fields: edit `includes/admin/class-chip-givewp-admin-settings.php`.
- To change checkout redirect logic: edit `includes/class-chip-givewp-purchase.php::create()` or `includes/block/class-chipgateway.php::createPayment()`.
- To adjust webhook verification or payment locking: edit `includes/class-chip-givewp-listener.php::handle_processing()`.
- To update version: bump in `chip-for-givewp.php` (header + `GWP_CHIP_MODULE_VERSION`), `readme.txt` (`Stable tag`), and `changelog.txt`. Alternatively, use `./scripts/bump-version.sh X.Y.Z`.
- To update WordPress.org assets: place images in `.wordpress-org/` (banners, icons, screenshots). Deployed to SVN by `deploy.yml`.
- To run local WordPress environment: `wp-env` reads `.wp-env.json` (GiveWP + plugin-check pre-installed).
- To add or change CI `ignore-codes` for the WordPress.org plugin check: edit `.github/workflows/plugin-check.yml` (newline-separated list per the action's `action.yml`). Always include a comment explaining why each code is being ignored — the maintainers may ask.
- To reproduce the CI `plugin-check` job locally: `npx wp-env start && npx wp-env run cli wp plugin activate plugin-check && npx wp-env run cli wp plugin check chip-for-givewp --ignore-codes=trademarked_term,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound`.

## External Dependencies

- GiveWP plugin (both legacy and v3 APIs).
- CHIP API endpoint: `https://gate.chip-in.asia/api/v1`.
- jQuery (for admin JS).
- WordPress React/`wp-element` (for block-form frontend JS).
