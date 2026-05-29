<?php
/**
 * Global CHIP settings under GiveWP Settings → Gateways → CHIP.
 *
 * @package GiveWPCHIP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Global settings registration.
 */
class Chip_Givewp_Admin_Global_Settings extends Chip_Givewp_Admin_Settings {

	/**
	 * Single instance of the class.
	 *
	 * @var Chip_Givewp_Admin_Global_Settings|null
	 */
	private static $instance;

	/**
	 * Gets the single instance of the class.
	 *
	 * @return Chip_Givewp_Admin_Global_Settings
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {

		add_filter( 'give_get_settings_gateways', array( $this, 'register_setting_fields' ) );
	}

	/**
	 * Registers the CHIP settings section fields.
	 *
	 * @param array $settings Gateway settings.
	 * @return array
	 */
	public function register_setting_fields( $settings ) {

		switch ( give_get_current_setting_section() ) {

			case 'chip-settings':
				$settings = array(
					array(
						'id'   => 'give_title_chip',
						'type' => 'title',
					),
				);

				$settings = array_merge( $settings, $this->setting_fields() );

				$settings[] = array(
					'id'   => 'give_title_chip',
					'type' => 'sectionend',
				);

				break;
		}

		return $settings;
	}
}

Chip_Givewp_Admin_Global_Settings::get_instance();
