<?php
/**
 * Helper class for CHIP for GiveWP.
 *
 * @package GiveWPCHIP
 */

defined( 'ABSPATH' ) || exit;

use Give\Log\LogFactory as Log;
use Give\Log\ValueObjects\LogCategory;

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
