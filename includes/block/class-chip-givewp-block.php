<?php
/**
 * Registers the ChipGateway class with GiveWP's PaymentGatewayRegister.
 *
 * @package GiveWPCHIP
 */

defined( 'ABSPATH' ) || exit;

use Give\Framework\PaymentGateways\PaymentGatewayRegister;

/**
 * Block gateway bootstrap.
 */
class Chip_Givewp_Block {

	/**
	 * Single instance of the class.
	 *
	 * @var Chip_Givewp_Block|null
	 */
	private static $instance;

	/**
	 * Gets the single instance of the class.
	 *
	 * @return Chip_Givewp_Block
	 */
	public static function get_instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->add_actions();
		$this->add_filters();
	}

	/**
	 * Adds WordPress actions.
	 */
	public function add_actions() {
		add_action(
			'givewp_register_payment_gateway',
			static function ( PaymentGatewayRegister $registrar ) {

				include plugin_dir_path( GWP_CHIP_FILE ) . 'includes/block/class-chipgateway.php';
				$registrar->registerGateway( ChipGateway::class );
			}
		);

		include plugin_dir_path( GWP_CHIP_FILE ) . 'includes/block/Actions/class-chip-givewp-enqueue-form-builder-scripts.php';
		add_action( 'givewp_form_builder_enqueue_scripts', new Chip_Givewp_Enqueue_Form_Builder_Scripts() );
	}

	/**
	 * Adds WordPress filters.
	 */
	public function add_filters() {
		// Reserved for future filter hooks.
	}
}

Chip_Givewp_Block::get_instance();
