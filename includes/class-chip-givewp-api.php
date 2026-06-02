<?php
/**
 * CHIP API client.
 *
 * @package GiveWPCHIP
 */

defined( 'ABSPATH' ) || exit;

// This is the CHIP API URL endpoint as documented in https://docs.chip-in.asia.
define( 'GWP_CHIP_ROOT_URL', 'https://gate.chip-in.asia' );

/**
 * CHIP API client class.
 */
class Chip_Givewp_API {

	/**
	 * Instances keyed by credential hash.
	 *
	 * @var array<string, Chip_Givewp_API>
	 */
	private static $instances = array();

	/**
	 * Secret key for API auth.
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * Brand ID.
	 *
	 * @var string
	 */
	private $brand_id;

	/**
	 * Gets an instance for the given credentials.
	 *
	 * @param string $secret_key Secret key.
	 * @param string $brand_id   Brand ID.
	 * @return Chip_Givewp_API
	 */
	public static function get_instance( $secret_key, $brand_id ) {
		$key = md5( (string) $secret_key . '|' . (string) $brand_id );
		if ( ! isset( self::$instances[ $key ] ) ) {
			self::$instances[ $key ] = new self( $secret_key, $brand_id );
		}

		return self::$instances[ $key ];
	}

	/**
	 * Constructor.
	 *
	 * @param string $secret_key Secret key.
	 * @param string $brand_id   Brand ID.
	 */
	public function __construct( $secret_key, $brand_id ) {
		$this->secret_key = $secret_key;
		$this->brand_id   = $brand_id;
	}

	/**
	 * Creates a payment (purchase).
	 *
	 * @param array $params Purchase params.
	 * @return array|null
	 */
	public function create_payment( $params ) {
		// time() is to force fresh instead of cache.
		return $this->call( 'POST', '/purchases/?time=' . time(), $params );
	}

	/**
	 * Gets available payment methods.
	 *
	 * @param string $currency Currency code.
	 * @param string $language Language code.
	 * @return array|null
	 */
	public function payment_methods( $currency, $language ) {
		return $this->call(
			'GET',
			"/payment_methods/?brand_id={$this->brand_id}&currency={$currency}&language={$language}"
		);
	}

	/**
	 * Gets a single payment (purchase).
	 *
	 * @param string $payment_id Purchase ID.
	 * @return array|null
	 */
	public function get_payment( $payment_id ) {
		// time() is to force fresh instead of cache.
		return $this->call( 'GET', "/purchases/{$payment_id}/?time=" . time() );
	}

	/**
	 * Checks whether a payment is successful.
	 *
	 * @param string $payment_id Purchase ID.
	 * @return bool
	 */
	public function was_payment_successful( $payment_id ) {
		$result = $this->get_payment( $payment_id );
		return $result && 'paid' === $result['status'];
	}

	/**
	 * Gets the public key (validates credentials).
	 *
	 * @return array|string|null
	 */
	public function get_public_key() {
		$result = $this->call( 'GET', '/public_key/' );
		if ( is_string( $result ) ) {
			$result = str_replace( '\n', "\n", $result );
		}
		return $result;
	}

	/**
	 * Gets the company UID for the current account (for storing public key by company).
	 *
	 * @return string|null Company UID or null on failure.
	 */
	public function get_company_uid() {
		$result = $this->call( 'GET', '/company_statements/' );
		if ( is_array( $result ) && ! empty( $result['results'] ) && is_array( $result['results'] ) ) {
			$first = reset( $result['results'] );
			return isset( $first['company_uid'] ) ? (string) $first['company_uid'] : null;
		}
		if ( is_array( $result ) && ( empty( $result['results'] ) || ! isset( $result['results'] ) ) ) {
			$post_result = $this->call(
				'POST',
				'/company_statements/',
				array(
					'format'   => 'csv',
					'timezone' => 'UTC',
				)
			);
			if ( is_array( $post_result ) && isset( $post_result['company_uid'] ) ) {
				return (string) $post_result['company_uid'];
			}
		}
		return null;
	}

	/**
	 * Cancels a payment.
	 *
	 * @param string $payment_id Purchase ID.
	 * @return array|null
	 */
	public function cancel_payment( $payment_id ) {
		return $this->call( 'POST', "/purchases/{$payment_id}/cancel/" );
	}

	/**
	 * Refunds a payment.
	 *
	 * @param string $payment_id Purchase ID.
	 * @param array  $params     Refund params.
	 * @return array|null
	 */
	public function refund_payment( $payment_id, $params = array() ) {
		return $this->call( 'POST', "/purchases/{$payment_id}/refund/", $params );
	}

	/**
	 * Makes an API call.
	 *
	 * @param string $method  HTTP method.
	 * @param string $route   API route.
	 * @param array  $params  Request body (encoded to JSON).
	 * @return array|null
	 */
	private function call( $method, $route, $params = array() ) {
		$secret_key = $this->secret_key;
		if ( ! empty( $params ) ) {
			$params = wp_json_encode( $params );
		}

		$response = $this->request(
			$method,
			sprintf( '%s/api/v1%s', GWP_CHIP_ROOT_URL, $route ),
			$params,
			array(
				'Content-type'  => 'application/json',
				'Authorization' => 'Bearer ' . $secret_key,
			)
		);

		if ( null === $response || '' === $response ) {
			return null;
		}

		$result = json_decode( $response, true );
		if ( null === $result ) {
			return null;
		}

		if ( ! empty( $result['errors'] ) ) {
			return null;
		}

		return $result;
	}

	/**
	 * Sends an HTTP request.
	 *
	 * @param string $method  HTTP method.
	 * @param string $url     Full URL.
	 * @param array  $params  Body.
	 * @param array  $headers Headers.
	 * @return string|null Response body or null on failure.
	 */
	private function request( $method, $url, $params = array(), $headers = array() ) {
		$wp_request = wp_remote_request(
			$url,
			array(
				'method'    => $method,
				'sslverify' => apply_filters( 'gwp_chip_sslverify', true ),
				'headers'   => $headers,
				'body'      => $params,
			)
		);

		if ( is_wp_error( $wp_request ) ) {
			return null;
		}

		$response_code = wp_remote_retrieve_response_code( $wp_request );
		if ( $response_code < 200 || $response_code >= 300 ) {
			return null;
		}

		$response = wp_remote_retrieve_body( $wp_request );

		return $response;
	}
}
