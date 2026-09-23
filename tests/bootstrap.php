<?php
/**
 * PHPUnit bootstrap for CHIP for GiveWP.
 *
 * @package GiveWPCHIP
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../vendor/wordpress/wordpress/' );
}

if ( ! defined( 'GWP_CHIP_PLUGIN_PATH' ) ) {
	define( 'GWP_CHIP_PLUGIN_PATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'GWP_CHIP_MODULE_VERSION' ) ) {
	define( 'GWP_CHIP_MODULE_VERSION', 'v1.4.0' );
}

$autoload = GWP_CHIP_PLUGIN_PATH . 'vendor/autoload.php';
if ( ! file_exists( $autoload ) ) {
	echo "Run composer install to install test dependencies.\n";
	exit( 1 );
}
require_once $autoload;

\WP_Mock::bootstrap();

// Stub WordPress functions used by the API but not provided by WP_Mock.
if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $data   Data to encode.
	 * @param int   $options Optional.
	 * @param int   $depth   Optional.
	 * @return string|false
	 */
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

// WordPress HTTP API stubs used by class-chip-givewp-api.php.
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public function __construct( $code = '', $message = '', $data = '' ) {
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		return isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 200;
	}
}

// WordPress time constants. WordPress is not loaded in the test environment,
// and the resolver passes MINUTE_IN_SECONDS to set_transient().
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

// Stubs for GiveWP's logging namespace. GiveWP is not installed in the test
// environment, and Chip_Givewp_Helper::log() calls these statics on every
// resolver invocation, so they must exist for the helper to be loadable.
//
// WP_Mock::userFunction() cannot stand in for them: it mocks namespaced
// FUNCTIONS, and a "Class::method" name is rejected as a parse error.
if ( ! class_exists( 'Give\Log\ValueObjects\LogType' ) ) {
	namespace_stub_logtype();
}

/**
 * Declares the LogType / LogCategory / LogFactory stubs.
 *
 * Wrapped in a function so the namespace blocks below are only ever declared
 * once per PHPUnit process.
 *
 * @return void
 */
function namespace_stub_logtype() {
	if ( ! class_exists( 'Give\Log\ValueObjects\LogType' ) ) {
		eval(
			'namespace Give\Log\ValueObjects; class LogType { const HTTP = "http"; const ERROR = "error"; const INFO = "info"; const WARNING = "warning"; const SUCCESS = "success"; }'
		);
	}

	if ( ! class_exists( 'Give\Log\ValueObjects\LogCategory' ) ) {
		eval(
			'namespace Give\Log\ValueObjects; class LogCategory { const PAYMENT = "payment"; }'
		);
	}

	if ( ! class_exists( 'Give\Log\LogFactory' ) ) {
		eval(
			'namespace Give\Log; class LogFactory {
				public static $entries = array();
				public static function makeFromArray( $args = array() ) {
					self::$entries[] = $args;
					return new class() {
						public function save() { return true; }
						public function getId() { return 1; }
					};
				}
			}'
		);
	}
}

// Load plugin files
require_once GWP_CHIP_PLUGIN_PATH . 'includes/class-chip-givewp-api.php';
require_once GWP_CHIP_PLUGIN_PATH . 'includes/class-chip-givewp-helper.php';
require_once GWP_CHIP_PLUGIN_PATH . 'includes/class-chip-givewp-listener.php';
require_once GWP_CHIP_PLUGIN_PATH . 'includes/class-chip-givewp-purchase.php';
