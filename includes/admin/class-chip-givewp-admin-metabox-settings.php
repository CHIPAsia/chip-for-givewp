<?php
/**
 * Per-form CHIP settings tab in the form editor.
 *
 * @package GiveWPCHIP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Metabox settings for per-form CHIP configuration.
 */
class Chip_Givewp_Admin_Metabox_Settings extends Chip_Givewp_Admin_Settings {

	/**
	 * Single instance of the class.
	 *
	 * @var Chip_Givewp_Admin_Metabox_Settings|null
	 */
	private static $instance;

	/**
	 * Gets the single instance of the class.
	 *
	 * @return Chip_Givewp_Admin_Metabox_Settings
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

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_js' ) );

		add_filter( 'give_metabox_form_data_settings', array( $this, 'add_tab' ) );
		add_filter( 'gwp_chip_metabox_fields', array( $this, 'metabox_fields' ) );
	}

	/**
	 * Enqueues the metabox JavaScript.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_js( $hook ) {

		if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
			wp_enqueue_script( 'gwp_chip_metabox', plugins_url( 'includes/js/metabox.js', GWP_CHIP_FILE ), array(), GWP_CHIP_MODULE_VERSION, true );
		}
	}

	/**
	 * Adds the CHIP tab to the form editor.
	 *
	 * @param array $settings Form settings.
	 * @return array
	 */
	public function add_tab( $settings ) {
		if ( give_is_gateway_active( 'chip' ) ) {
			$settings['chip_metabox_options'] = apply_filters(
				'gwp_chip_metabox_options',
				array(
					'id'        => 'chip_metabox_options',
					'title'     => __( 'CHIP', 'chip-for-givewp' ),
					'icon-html' => '<object data=" ' . esc_url( plugins_url( 'assets/logo.svg', GWP_CHIP_FILE ) ) . '" width="13" height="13.18"></object>',
					'fields'    => apply_filters( 'gwp_chip_metabox_fields', array() ),
				)
			);
		}

		return $settings;
	}

	/**
	 * Adds per-form CHIP fields to the metabox.
	 *
	 * @param array $settings Form settings.
	 * @return array
	 */
	public function metabox_fields( $settings ) {
		if ( in_array( 'chip', (array) give_get_option( 'gateways' ), true ) ) {
			return $settings;
		}

		$is_gateway_active = give_is_gateway_active( 'chip' );

		if ( ! $is_gateway_active ) {
			return $settings;
		}

		$check_settings = array(
			array(
				'name'    => __( 'CHIP', 'chip-for-givewp' ),
				'desc'    => __( 'Do you want to customize the CHIP configuration for this form?', 'chip-for-givewp' ),
				'id'      => '_give_customize_chip_donations',
				'type'    => 'radio_inline',
				'default' => 'global',
				'options' => apply_filters(
					'give_forms_content_options_select',
					array(
						'global'   => __( 'Global Option', 'chip-for-givewp' ),
						'enabled'  => __( 'Customize', 'chip-for-givewp' ),
						'disabled' => __( 'Disable', 'chip-for-givewp' ),
					)
				),
			),
		);

		$check_settings = array_merge( $check_settings, $this->setting_fields( '_give_' ) );

		return array_merge( $settings, $check_settings );
	}
}

Chip_Givewp_Admin_Metabox_Settings::get_instance();
