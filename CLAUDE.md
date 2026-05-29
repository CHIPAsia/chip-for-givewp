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
- **`includes/class-api.php`** — `Chip_Givewp_API`. Wraps CHIP REST API (`https://gate.chip-in.asia/api/v1`). **Important**: `get_instance()` is a **keyed singleton** — instances are cached by `md5($secret_key . '|' . $brand_id)` so the same credentials reuse one instance, but different credentials get separate instances. Added methods: `get_company_uid()`, `cancel_payment()`. `request()` returns `null` on `WP_Error` or non-2xx status codes.
- **`includes/class-purchase.php`** — `Chip_Givewp_Purchase`. Handles legacy form donation creation and redirect to CHIP checkout.
- **`includes/block/class-chip-gateway.php`** — `ChipGateway`. Handles block-form donation creation. Also implements `refundDonation()` for block-form refunds.
- **`includes/class-listener.php`** — `Chip_Givewp_Listener`. Handles CHIP callback/webhook (`handle_callback`) and customer redirect (`handle_redirect`). Verifies webhook signatures via `openssl_verify` using a **company-UID-based cached public key** (`gwp_chip_public_key_{company_uid}`). On verification failure, falls back to fetching payment status via the API. Uses MySQL `GET_LOCK`/`RELEASE_LOCK` (verified with `get_var()`) to prevent duplicate payment processing.
- **`includes/class-helper.php`** — `Chip_Givewp_Helper`. Static utilities for form settings and logging via `Give\Log\LogFactory`.
- **`includes/admin/`** — Settings UI:
  - `class-settings.php` — Base settings field definitions (shared by global and per-form settings).
  - `class-global-settings.php` — Global CHIP settings under GiveWP Settings → Gateways → CHIP.
  - `class-metabox-settings.php` — Per-form CHIP settings tab in the form editor.
  - `class-refund-button.php` — Adds a manual refund button to the donation details admin page.
- **`includes/block/chip-givewp-block.php`** — Registers the `ChipGateway` class with GiveWP's `PaymentGatewayRegister`.
- **`includes/block/js/chip-gateway.js`** — Minimal React-based frontend for block forms. Registers `window.givewp.gateways`.
- **`includes/js/metabox.js`** — Toggles per-form CHIP settings visibility in the legacy form editor.
- **`includes/js/refund.js`** — AJAX handler for the admin refund button.
- **`.wp-env.json`** — Local WordPress environment config (GiveWP + plugin-check).
- **`.wordpress-org/`** — WordPress.org plugin directory assets (banners, icons, screenshots).

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
- **Composer dev dependencies**: `phpunit/phpunit`, `10up/wp_mock`, `yoast/phpunit-polyfills`.
- **Formatting**: VS Code workspace setting uses `"php.format.codeStyle": "WordPress"`. PHPCS is configured for WordPress standards via `phpcs.xml`.
- **Minimum PHP**: 7.4 (as of v1.3.0).
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

- To modify gateway settings fields: edit `includes/admin/class-settings.php`.
- To change checkout redirect logic: edit `includes/class-purchase.php::create()` or `includes/block/class-chip-gateway.php::createPayment()`.
- To adjust webhook verification or payment locking: edit `includes/class-listener.php::handle_processing()`.
- To update version: bump in `chip-for-givewp.php` (header + `GWP_CHIP_MODULE_VERSION`), `readme.txt` (`Stable tag`), and `changelog.txt`. Alternatively, use `./scripts/bump-version.sh X.Y.Z`.
- To update WordPress.org assets: place images in `.wordpress-org/` (banners, icons, screenshots). Deployed to SVN by `deploy.yml`.
- To run local WordPress environment: `wp-env` reads `.wp-env.json` (GiveWP + plugin-check pre-installed).

## External Dependencies

- GiveWP plugin (both legacy and v3 APIs).
- CHIP API endpoint: `https://gate.chip-in.asia/api/v1`.
- jQuery (for admin JS).
- WordPress React/`wp-element` (for block-form frontend JS).
