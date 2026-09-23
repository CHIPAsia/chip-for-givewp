<?php
/**
 * Helper class for CHIP for GiveWP.
 *
 * @package GiveWPCHIP
 */

defined( 'ABSPATH' ) || exit;

use Give\Log\LogFactory as Log;
use Give\Log\ValueObjects\LogCategory;
use Give\Log\ValueObjects\LogType;

/**
 * Helper class for form settings and logging.
 */
class Chip_Givewp_Helper {

	/**
	 * Gets a field value from global or per-form settings.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $column  Setting column name.
	 * @param string $prefix  Optional meta prefix.
	 * @return mixed
	 */
	public static function get_fields( $form_id, $column, $prefix = '' ) {
		if ( empty( $prefix ) ) {
			return give_get_option( $column );
		}
		return give_get_meta( $form_id, $prefix . $column, true );
	}

	/**
	 * Returns the `due` timestamp for a purchase, or null when it is disabled.
	 *
	 * The merchant disables the due limit by clearing the timing field, which
	 * stores an empty value. Coercing that empty value with absint() yields 0,
	 * so `time() + 0` is already in the past by the time CHIP processes the
	 * request and every purchase is rejected with:
	 *
	 *   `due` cannot be in the past! (due_not_greater_than_now)
	 *
	 * Returning null lets the caller leave the parameter out entirely, which
	 * is what "no due limit" means to the API. Mirrors the WooCommerce
	 * gateway's get_due_timestamp().
	 *
	 * @param mixed $due_strict_timing Raw timing value in minutes.
	 * @return int|null Due timestamp, or null when disabled.
	 */
	public static function resolve_due_timestamp( $due_strict_timing ) {
		if ( '' === $due_strict_timing || null === $due_strict_timing || false === $due_strict_timing ) {
			return null;
		}

		$minutes = absint( $due_strict_timing );
		if ( 0 === $minutes ) {
			return null;
		}

		return time() + ( $minutes * 60 );
	}

	/**
	 * Returns a purchase field from an API response, or null when unavailable.
	 *
	 * Chip_Givewp_API::call() returns null for every failure shape (transport
	 * error, non-2xx status, unparseable body, error payload), so a response
	 * that reaches here is not guaranteed to be an array. Callers must never
	 * dereference it directly: on PHP 8 an array_key_exists() on null raises a
	 * TypeError, which is not an Exception and therefore escapes the gateway's
	 * catch (\Exception) handler, turning a failed purchase into an uncaught
	 * fatal (HTTP 500) instead of a reportable payment error.
	 *
	 * @param mixed  $payment API response.
	 * @param string $key     Key to read.
	 * @return mixed Field value, or null when the response is unusable.
	 */
	public static function get_payment_field( $payment, $key ) {
		if ( ! is_array( $payment ) ) {
			return null;
		}

		return array_key_exists( $key, $payment ) ? $payment[ $key ] : null;
	}

	/**
	 * Updates a field value in global or per-form settings.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $column  Setting column name.
	 * @param mixed  $value   Value to store.
	 * @param string $prefix  Optional meta prefix.
	 * @return mixed
	 */
	public static function update_fields( $form_id, $column, $value, $prefix = '' ) {
		if ( empty( $prefix ) ) {
			return give_update_option( $column, $value );
		}

		return give_update_meta( $form_id, $prefix . $column, $value );
	}

	/**
	 * DuitNow QR group: legacy (duitnow_qr) and modern (dnqr) identifiers.
	 * Exposed to the merchant as a single group; resolved at runtime.
	 *
	 * @var array<string>
	 */
	const DUITNOW_GROUP = array( 'duitnow_qr', 'dnqr' );

	/**
	 * Shopee Pay group: legacy (razer_shopeepay) and modern (shopee_pay)
	 * identifiers. Exposed to the merchant as a single group; resolved at
	 * runtime.
	 *
	 * @var array<string>
	 */
	const SHOPEE_GROUP = array( 'razer_shopeepay', 'shopee_pay' );

	/**
	 * Resolves the payment method whitelist, handling the DuitNow QR and
	 * Shopee Pay groups.
	 *
	 * When the configured whitelist contains a member of either group, the
	 * configured groups are expanded, intersected with the merchant's
	 * actually-available methods (fetched via a single /payment_methods/
	 * call, cached in a transient), and the preferred member wins when both
	 * are available (dnqr over duitnow_qr, shopee_pay over razer_shopeepay).
	 * Whitelists containing neither group short-circuit and return unchanged
	 * (no API call).
	 *
	 * @param array  $whitelist  Final whitelist (cards already expanded).
	 * @param string $currency   Currency code (MYR).
	 * @param int    $amount     Amount in minor units (sen).
	 * @param string $secret_key Secret key.
	 * @param string $brand_id   Brand ID.
	 * @param int    $form_id    Form ID.
	 * @return array
	 */
	public static function resolve_duitnow_methods( $whitelist, $currency, $amount, $secret_key, $brand_id, $form_id ) {
		$whitelist = (array) $whitelist;

		// In-memory migration: legacy razer_shopeepay -> modern shopee_pay.
		// Only when shopee_pay is not already present, so a merchant who
		// configured both keeps a single modern entry.
		if ( in_array( 'razer_shopeepay', $whitelist, true ) && ! in_array( 'shopee_pay', $whitelist, true ) ) {
			$whitelist = array_values(
				array_map(
					static function ( $method ) {
						return 'razer_shopeepay' === $method ? 'shopee_pay' : $method;
					},
					$whitelist
				)
			);
		}

		$groups = array(
			'dnqr'       => self::DUITNOW_GROUP,
			'shopee_pay' => self::SHOPEE_GROUP,
		);

		$all_members = array_values( array_unique( array_merge( ...array_values( $groups ) ) ) );

		// Short-circuit: whitelist containing neither group is returned untouched.
		if ( 0 === count( array_intersect( $whitelist, $all_members ) ) ) {
			return $whitelist;
		}

		$expanded = array_values( array_unique( array_merge( $whitelist, $all_members ) ) );

		// Cache key: brand + currency + amount-bucket (round to 100-sen steps).
		$cache_key = 'gwp_chip_pm_' . md5( $brand_id . '|' . $currency . '|' . intval( $amount / 100 ) );

		$available = get_transient( $cache_key );
		if ( false === $available ) {
			$chip     = Chip_Givewp_API::get_instance( $secret_key, $brand_id );
			$response = $chip->payment_methods( $currency, '', $amount );
			if ( ! is_array( $response ) || ! isset( $response['available_payment_methods'] ) ) {
				// API failed; fallback to the expanded whitelist.
				self::log( $form_id, LogType::HTTP, sprintf( 'group resolver: API failed, fallback to expanded whitelist=%s', implode( ',', $expanded ) ) );
				return $expanded;
			}
			$available = $response['available_payment_methods'];
			set_transient( $cache_key, $available, 30 * MINUTE_IN_SECONDS );
		}

		$available = (array) $available;

		// Resolve each configured group against the merchant's available methods.
		$resolved = array();
		foreach ( $groups as $preferred => $members ) {
			if ( 0 === count( array_intersect( $whitelist, $members ) ) ) {
				continue;
			}

			$resolved_group = array_values( array_intersect( $members, $available ) );

			// Priority: preferred member wins when both are present.
			if ( in_array( $preferred, $resolved_group, true ) ) {
				$resolved_group = array( $preferred );
			}

			$resolved = array_merge( $resolved, $resolved_group );
		}

		// Build final whitelist: original entries (group members stripped) + resolved groups.
		$final = array_values( array_diff( $expanded, $all_members ) );
		$final = array_merge( $final, $resolved );

		self::log(
			$form_id,
			LogType::HTTP,
			sprintf(
				'group resolver: configured=%s expanded=%s available=%s sent=%s',
				implode( ',', $whitelist ),
				implode( ',', $expanded ),
				implode( ',', $available ),
				implode( ',', $final )
			)
		);

		return $final;
	}

	/**
	 * Logs a message to GiveWP logs.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $type    Log type.
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 * @return int|null Log ID.
	 */
	public static function log( $form_id, $type, $message, $context = array() ) {
		$log = Log::makeFromArray(
			array(
				'type'     => $type,
				'message'  => $message,
				'category' => LogCategory::PAYMENT,
				'source'   => 'CHIP for GiveWP version ' . GWP_CHIP_MODULE_VERSION,
				'context'  => $context,
				'id'       => $form_id,
			)
		);

		$log->save();

		return $log->getId();
	}
}
