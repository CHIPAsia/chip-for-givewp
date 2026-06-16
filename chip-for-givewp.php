<?php
/**
 * Plugin Name: CHIP for GiveWP
 * Plugin URI: https://wordpress.org/plugins/chip-for-givewp/
 * Description: CHIP - Digital Finance Platform
 * Version: 1.3.0
 * Author: Chip In Sdn Bhd
 * Author URI: https://www.chip-in.asia
 *
 * Copyright: © 2026 CHIP
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package GiveWPCHIP
 */

defined( 'ABSPATH' ) || exit;

define( 'GWP_CHIP_MODULE_VERSION', 'v1.3.0' );

/**
 * Main plugin class.
 */
class Chip_Givewp {

	/**
	 * Single instance of the class.
	 *
	 * @var Chip_Givewp|null
	 */
	private static $instance;

	/**
	 * Gets the single instance of the class.
	 *
	 * @return Chip_Givewp
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
		$this->define();
		$this->includes();
		$this->add_filters();
		$this->add_actions();
	}

	/**
	 * Defines plugin constants.
	 */
	public function define() {
		define( 'GWP_CHIP_FILE', __FILE__ );
		define( 'GWP_CHIP_BASENAME', plugin_basename( GWP_CHIP_FILE ) );
	}

	/**
	 * Includes plugin files.
	 */
	public function includes() {
		$includes_dir = plugin_dir_path( GWP_CHIP_FILE ) . 'includes/';
		include $includes_dir . 'class-chip-givewp-api.php';
		include $includes_dir . 'class-chip-givewp-helper.php';

		if ( is_admin() ) {
			include $includes_dir . 'admin/class-chip-givewp-admin-settings.php';
			include $includes_dir . 'admin/class-chip-givewp-admin-global-settings.php';
			include $includes_dir . 'admin/class-chip-givewp-admin-metabox-settings.php';
			include $includes_dir . 'admin/class-chip-givewp-refund-button.php';
		}

		include $includes_dir . 'class-chip-givewp-listener.php';
		include $includes_dir . 'class-chip-givewp-purchase.php';

		// Add block support.
		include $includes_dir . 'block/class-chip-givewp-block.php';
	}

	/**
	 * Adds WordPress filters.
	 */
	public function add_filters() {
		add_filter( 'plugin_action_links_' . GWP_CHIP_BASENAME, array( $this, 'setting_link' ) );
		add_filter( 'give_payment_gateways', array( $this, 'register_payment_method' ) );
		add_filter( 'give_get_sections_gateways', array( $this, 'register_payment_gateway_sections' ) );
		add_filter( 'give_enabled_payment_gateways', array( $this, 'filter_gateway' ), 10, 2 );
	}

	/**
	 * Adds WordPress actions.
	 */
	public function add_actions() {
		// Preferred (prefixed) hook. Billing fields render on this hook.
		add_action( 'gwp_chip_before_info_fields', array( $this, 'billing_fields' ) );

		// Legacy hook — kept registered so any pre-1.4.0 listener still
		// fires. Our own billing_fields callback detaches itself the
		// first time the legacy hook runs (see billing_fields() below),
		// so billing_fields is invoked exactly once per request even
		// when both hooks fire.
		add_action( 'give_before_chip_info_fields', array( $this, 'billing_fields' ) );
	}

	/**
	 * Registers the CHIP payment method.
	 *
	 * @param array $gateways Available gateways.
	 * @return array
	 */
	public function register_payment_method( $gateways ) {

		$gateways['chip'] = array(
			'admin_label'    => __( 'CHIP', 'chip-for-givewp' ),
			'checkout_label' => __( 'Online Banking/Credit Card', 'chip-for-givewp' ),
		);

		return apply_filters( 'gwp_chip_register_payment_method', $gateways );
	}

	/**
	 * Registers the CHIP settings section.
	 *
	 * @param array $sections Gateway sections.
	 * @return array
	 */
	public function register_payment_gateway_sections( $sections ) {

		$sections['chip-settings'] = __( 'CHIP', 'chip-for-givewp' );

		return $sections;
	}

	/**
	 * Filters the gateway list based on form settings.
	 *
	 * @param array $gateway_list Available gateways.
	 * @param int   $form_id      Form ID.
	 * @return array
	 */
	public function filter_gateway( $gateway_list, $form_id ) {
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			if (
				( false === strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '/wp-admin/post-new.php?post_type=give_forms' ) )
				&& $form_id
				&& ! give_is_setting_enabled( give_get_meta( $form_id, '_give_customize_chip_donations', true, 'global' ), array( 'enabled', 'global' ) )
			) {
				unset( $gateway_list['chip'] );
			}
		}

		return $gateway_list;
	}

	/**
	 * Adds billing fields when enabled.
	 *
	 * @param int $form_id Form ID.
	 */
	public function billing_fields( $form_id ) {
		// If this is being invoked via the legacy give_before_chip_info_fields
		// hook, detach ourselves from it now that the new prefixed hook
		// is wired up. We remove only our own callback so any other
		// listeners on the legacy hook still run.
		if ( doing_action( 'give_before_chip_info_fields' ) ) {
			remove_action( 'give_before_chip_info_fields', array( $this, 'billing_fields' ) );
		}

		$chip_customization = give_get_meta( $form_id, '_give_customize_chip_donations', true, 'global' );
		$billing_fields     = give_get_meta( $form_id, '_give_chip-enable-billing-fields', true );

		$global_billing_fields = give_get_option( 'chip-enable-billing-fields' );

		if (
			( give_is_setting_enabled( $chip_customization, 'global' ) && give_is_setting_enabled( $global_billing_fields ) )
			|| ( give_is_setting_enabled( $chip_customization, 'enabled' ) && give_is_setting_enabled( $billing_fields ) )
		) {
			give_default_cc_address_fields( $form_id );
		}
	}

	/**
	 * Adds a settings link on the plugins page.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function setting_link( $links ) {
		$new_links = array(
			'settings' => sprintf(
				'<a href="%1$s">%2$s</a>',
				admin_url( 'edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=chip-settings' ),
				esc_html__( 'Settings', 'chip-for-givewp' )
			),
		);

		return array_merge( $new_links, $links );
	}
}

Chip_Givewp::get_instance();
