# CHIP Recurring / Subscription Feasibility Assessment

> **Date:** 2026-06-02  
> **CHIP API Version:** v1  
> **GiveWP Version Target:** Legacy (pre-v3) + v3 Visual Form Builder  
> **Plugin Version at time of assessment:** 1.3.0

---

## Executive Summary

**It is technically possible to add recurring donations, but it is not trivial.**

CHIP's "subscription" model is not a platform-managed recurring billing system. It is a **saved-card tokenization layer** that lets merchants charge a card again using a previously generated token. The merchant (this plugin) must build and maintain:

- The renewal scheduler
- The dunning (failure retry) logic
- Subscription status tracking
- Donor-facing subscription management UI

---

## How CHIP Subscriptions Work

Reference: https://docs.chip-in.asia/chip-collect/overview/online-purchases/subscription

### Three-Step Flow

1. **Create a token** via an initial purchase:
   - Option A: Zero-amount card validation using `skip_capture`
   - Option B: Paid setup charge using `force_recurring`
   - The resulting purchase `id` is the **recurring token**

2. **Create the subscription purchase** (the first actual donation)

3. **Charge the token** for renewals via the **Charge Token API**, passing the `recurring_token`

### Key Limitations from CHIP

| Limitation | Impact |
|---|---|
| **CHIP does not handle automatic renewal** | Plugin must build a cron job / scheduler |
| **Supported brands: `visa`, `mastercard`, `maestro` only** | FPX, DuitNow, etc. cannot be used for recurring. The gateway must restrict payment methods or show an error |
| **Once a payment link is paid, subsequent attempts are blocked** | Prevents accidental duplicate debits, but means token-based charges must be explicit API calls |
| **Token is linked to `brand_id` and customer email** | Changing either may invalidate the token |

### Required CHIP API Endpoints (not yet in plugin)

- `POST /purchases/{id}/charge/` — Charge a saved token
- `GET /purchases/?recurring_token=...` — List tokens
- `DELETE /purchases/{id}/` — Delete (revoke) a token

---

## GiveWP Recurring Donations Add-On Context

GiveWP has a **Recurring Donations** premium add-on that provides:

- Subscription records (`Give_Recurring_Subscription` or similar)
- Donor subscription management UI
- Admin subscription management
- Renewal payment recording hooks
- Email notifications for renewals, failures, cancellations

Third-party gateways integrate via hooks such as:

- `give_recurring_available_gateways` — Register the gateway as recurring-capable
- `give_recurring_record_payment` — Record a renewal payment against a subscription
- Various `Give_Recurring_Gateway` base-class methods (exact interface depends on add-on version)

> **Important:** The add-on is **closed-source / premium**. You need a license to inspect its exact gateway API. The snippets below are inferred from public GitHub mirrors of older add-on versions and third-party gateway implementations.

---

## What Would Need to Be Built

### 1. API Client Extensions (`includes/class-api.php`)

Add three new methods to `Chip_Givewp_API`:

```php
public function charge_token( $token, $params );
public function list_tokens( $email );
public function delete_token( $token );
```

### 2. Recurring Gateway Integration

For **legacy forms**, create a class that integrates with the Recurring add-on:

- Hook `give_recurring_available_gateways` to add `'chip'`
- Implement subscription creation after the initial payment succeeds
- Store `recurring_token` in `give_recurring_subscription_meta` or custom meta

For **block forms (v3)**, investigate whether the Recurring add-on exposes a `PaymentGateway` interface for recurring. As of this assessment, the v3 block gateway (`ChipGateway`) only implements `PaymentGatewayRefundable`. Recurring support for v3 may require a separate interface or may not yet be supported by the add-on.

### 3. Renewal Scheduler

You **cannot** rely on donor visits or webhooks for renewals. Implement one of:

- **WordPress Cron** (`wp_schedule_event`) — simple but unreliable on low-traffic sites
- **Action Scheduler** (if GiveWP bundles it) — more robust, supports retries

The scheduler must:

1. Query subscriptions due for renewal
2. Call `charge_token()` with the correct amount, currency, and reference
3. On success: create a new GiveWP payment linked to the subscription
4. On failure: retry per dunning rules, then mark subscription as `expired` / `cancelled`

### 4. Payment Method Restriction

Because recurring only works with cards, when a form has recurring enabled:

- The gateway must **whitelist only `visa`, `mastercard`, `maestro`**
- Or show a clear message: *"Recurring donations are only available via credit / debit card."*

This affects:
- `includes/class-purchase.php` (legacy)
- `includes/block/class-chipgateway.php` (v3)

### 5. Subscription Management

- **Cancellation**: Call CHIP's delete-token API and update GiveWP subscription status
- **Admin UI**: Hook into GiveWP's donation details page (similar to existing refund button)
- **Donor UI**: GiveWP Recurring add-on provides this; gateway only needs to support the cancel hook

### 6. Webhook / Listener Updates (`includes/class-listener.php`)

The existing listener handles one-time `success_callback`. For recurring:

- Initial payment callback still creates the donation and generates the token
- Renewal charges are **API-driven**, not redirect-driven, so no `success_redirect` is involved
- However, CHIP may still send webhooks for token status changes; decide if these need handling

---

## Files That Would Be Touched

| File | Change |
|---|---|
| `includes/class-api.php` | Add `charge_token`, `list_tokens`, `delete_token` |
| `includes/class-purchase.php` | Detect recurring; pass `force_recurring` / `skip_capture`; store token |
| `includes/class-listener.php` | Possibly handle token-lifecycle webhooks |
| `includes/class-chip-givewp-helper.php` | Add recurring-specific logging / meta helpers |
| `includes/admin/class-refund-button.php` | Add cancel-subscription button |
| **New file** `includes/class-recurring.php` | Core recurring logic: scheduler, renewal processing |
| **New file** `includes/class-recurring-legacy.php` | Legacy-form recurring gateway adapter |
| **New file** `includes/block/class-chip-recurring.php` | Block-form recurring gateway adapter (if v3 add-on supports it) |
| `chip-for-givewp.php` | Load new recurring classes; check for `GIVE_RECURRING_VERSION` |
| `readme.txt` | Document recurring requirements and limitations |

---

## Risk & Complexity Assessment

| Risk | Severity | Mitigation |
|---|---|---|
| CHIP does not auto-renew; cron must be 100% reliable | **High** | Use Action Scheduler; add monitoring / logging |
| Cards-only restriction may confuse FPX donors | **Medium** | Clear UX copy; force whitelist on recurring forms |
| v3 block-form recurring add-on API is unclear | **Medium** | Verify with GiveWP support or licensed add-on code before building |
| Token invalidation (expired card, brand change) | **Medium** | Handle `charge_token` failures; email donor to update card |
| Premium add-on dependency | **Low** | Gate all recurring code behind `defined('GIVE_RECURRING_VERSION')` |

---

## Decision Checklist

Before starting implementation, verify:

- [ ] **Is the Recurring add-on installed and active?** All recurring code must be gated.
- [ ] **Does the add-on support v3 Visual Form Builder gateways?** If not, recurring may be legacy-only.
- [ ] **Which scheduler will be used?** Recommend Action Scheduler over WP Cron.
- [ ] **What is the dunning policy?** How many retry attempts? Over what interval?
- [ ] **How does the add-on expect renewal payments to be recorded?** Exact hook/filter names.

---

## Recommended Next Steps

1. **Acquire / inspect the GiveWP Recurring Donations add-on** to confirm its gateway integration API (exact base class, required methods, hooks).
2. **Prototype the token flow** in a sandbox:
   - Create a `skip_capture` purchase with test card `4444 3333 2222 1111`
   - Confirm the purchase `id` can be used as `recurring_token`
   - Call `charge_token` with a new amount and verify it succeeds
3. **Decide on scheduler** (Action Scheduler vs WP Cron) and build the renewal engine as a standalone module first.
4. **Integrate with GiveWP** only after the standalone renewal engine is proven in sandbox.

---

*This document was generated during a feasibility assessment on 2026-06-02. If CHIP's API or GiveWP's add-on have changed since then, re-verify all API endpoints and hooks before proceeding.*
