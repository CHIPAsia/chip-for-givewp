<?php
/**
 * Regression tests for the purchase-creation failure paths.
 *
 * Two defects were reproduced end to end on GiveWP 4.16.9 (both form
 * generations: the v3 block gateway and the v2 legacy gateway):
 *
 * 1. `due` was always sent as `time() + ( absint( $timing ) * 60 )`. With the
 *    timing field empty - which is exactly what a fresh install stores, and
 *    what the admin UI writes when a merchant clears the field - absint()
 *    yields 0, so `due` equalled the current time and CHIP rejected every
 *    purchase with 400 `due_not_greater_than_now`.
 *
 * 2. Chip_Givewp_API::create_payment() returns null for every failure shape
 *    (transport error, non-2xx, unparseable body, error payload). Callers then
 *    called array_key_exists( 'id', null ), which on PHP 8 is a TypeError -
 *    not an Exception - so the gateway's catch ( \Exception ) did not catch it
 *    and the donor got an uncaught fatal (HTTP 500) instead of a payment error.
 *
 * @package GiveWPCHIP
 */

namespace GiveWPCHIP\Tests\Unit;

use Chip_Givewp_API;
use Chip_Givewp_Helper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * @covers \Chip_Givewp_Helper::resolve_due_timestamp
 * @covers \Chip_Givewp_Helper::get_payment_field
 */
class Chip_Givewp_PurchaseFailureTest extends TestCase {

	/**
	 * Set up WP_Mock before each test.
	 */
	public function setUp(): void {
		WP_Mock::setUp();
	}

	/**
	 * Tear down WP_Mock after each test.
	 */
	public function tearDown(): void {
		WP_Mock::tearDown();
	}

	/**
	 * A configured timing produces a future timestamp, so `due` is still sent.
	 *
	 * @dataProvider configured_timing_provider
	 *
	 * @param mixed $timing Raw timing value.
	 * @param int   $minutes Expected minutes ahead.
	 */
	#[DataProvider( 'configured_timing_provider' )]
	public function test_resolve_due_timestamp_returns_future_timestamp_for_configured_timing( $timing, int $minutes ): void {
		$before = time();
		$due    = Chip_Givewp_Helper::resolve_due_timestamp( $timing );
		$after  = time();

		$this->assertIsInt( $due, 'a configured timing must still send `due`' );
		$this->assertGreaterThan( $after, $due, '`due` must be strictly in the future' );
		$this->assertGreaterThanOrEqual( $before + ( $minutes * 60 ), $due );
		$this->assertLessThanOrEqual( $after + ( $minutes * 60 ), $due );
	}

	/**
	 * Configured timing values, including the string the admin field submits.
	 *
	 * @return array<string, array{0: mixed, 1: int}>
	 */
	public static function configured_timing_provider(): array {
		return array(
			'integer 60'         => array( 60, 60 ),
			'numeric string 60'  => array( '60', 60 ),
			'integer 15'         => array( 15, 15 ),
			'numeric string 240' => array( '240', 240 ),
		);
	}

	/**
	 * An empty timing disables `due` entirely.
	 *
	 * This is the fresh-install and cleared-field shape. Returning null is what
	 * stops the caller from sending `time() + 0`, which CHIP rejects as a past
	 * timestamp (400 due_not_greater_than_now).
	 *
	 * @dataProvider disabled_timing_provider
	 *
	 * @param mixed $timing Raw timing value meaning "no limit".
	 */
	#[DataProvider( 'disabled_timing_provider' )]
	public function test_resolve_due_timestamp_returns_null_when_timing_is_disabled( $timing ): void {
		$this->assertNull(
			Chip_Givewp_Helper::resolve_due_timestamp( $timing ),
			'a disabled timing must return null so `due` is omitted from the request'
		);
	}

	/**
	 * Shapes the timing setting takes when the merchant disables it.
	 *
	 * give_get_option() returns false for an option that was never saved, and
	 * an empty string for a field the merchant cleared.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public static function disabled_timing_provider(): array {
		return array(
			'unsaved option (false)' => array( false ),
			'cleared field (empty)'  => array( '' ),
			'null'                   => array( null ),
			'string "0"'             => array( '0' ),
			'integer 0'              => array( 0 ),
			'non numeric junk'       => array( 'abc' ),
		);
	}

	/**
	 * A payment field is read from a well-formed API response.
	 */
	public function test_get_payment_field_returns_value_from_array_response(): void {
		$payment = array(
			'id'     => 'pay_abc123',
			'status' => 'paid',
		);

		$this->assertSame( 'pay_abc123', Chip_Givewp_Helper::get_payment_field( $payment, 'id' ) );
		$this->assertSame( 'paid', Chip_Givewp_Helper::get_payment_field( $payment, 'status' ) );
	}

	/**
	 * The failure responses create_payment() can return never reach a caller.
	 *
	 * Each of these is what Chip_Givewp_API::call() returns instead of an array,
	 * and each one previously caused a PHP 8 TypeError when handed to
	 * array_key_exists(). The helper must answer null for all of them and never
	 * raise: a TypeError is not an Exception, so it would escape the gateway's
	 * catch ( \Exception ) block and surface as an uncaught fatal (HTTP 500).
	 *
	 * @dataProvider unusable_response_provider
	 *
	 * @param mixed $payment Response that is not a usable payment array.
	 */
	#[DataProvider( 'unusable_response_provider' )]
	public function test_get_payment_field_returns_null_for_unusable_response( $payment ): void {
		$this->assertNull( Chip_Givewp_Helper::get_payment_field( $payment, 'id' ) );
	}

	/**
	 * Every shape Chip_Givewp_API::call() returns on failure.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public static function unusable_response_provider(): array {
		return array(
			'transport error (null)'      => array( null ),
			'non 2xx / unparseable (null)' => array( null ),
			'boolean false'               => array( false ),
			'empty string'                => array( '' ),
			'string body'                 => array( 'not json' ),
			'integer'                     => array( 0 ),
		);
	}

	/**
	 * Reading a missing key from a valid response answers null.
	 */
	public function test_get_payment_field_returns_null_for_missing_key(): void {
		$this->assertNull( Chip_Givewp_Helper::get_payment_field( array( 'status' => 'paid' ), 'id' ) );
	}

	/**
	 * An API failure response from the real client is not an array.
	 *
	 * Drives Chip_Givewp_API::create_payment() with the exact wire shapes that
	 * produced the 500 in production: a 400 due_not_greater_than_now rejection
	 * and a 401 authentication failure. Both must come back null, which is what
	 * makes the guard around the call site load-bearing.
	 *
	 * @dataProvider failing_api_response_provider
	 *
	 * @param int    $status  HTTP status returned by CHIP.
	 * @param string $body    Response body returned by CHIP.
	 */
	#[DataProvider( 'failing_api_response_provider' )]
	public function test_create_payment_returns_null_on_failing_api_response( int $status, string $body ): void {
		$this->stub_api_transport( $status, $body );

		$api     = Chip_Givewp_API::get_instance( 'test_secret', 'test_brand' );
		$payment = $api->create_payment( array( 'brand_id' => 'test_brand' ) );

		$this->assertNull( $payment, "CHIP {$status} must not produce a usable payment array" );
		$this->assertNull( Chip_Givewp_Helper::get_payment_field( $payment, 'id' ) );
	}

	/**
	 * The two failing responses observed in the production reproduction.
	 *
	 * @return array<string, array{0: int, 1: string}>
	 */
	public static function failing_api_response_provider(): array {
		return array(
			'due in the past (400)' => array(
				400,
				'{"due":[{"message":"`due` cannot be in the past!","code":"due_not_greater_than_now"}]}',
			),
			'bad credentials (401)' => array(
				401,
				'{"__all__":[{"message":"Authorization header missing","code":"authentication_failed"}]}',
			),
		);
	}

	/**
	 * Stubs the WordPress HTTP transport for the API client.
	 *
	 * @param int    $status HTTP status.
	 * @param string $body   Response body.
	 */
	private function stub_api_transport( int $status, string $body ): void {
		WP_Mock::userFunction( 'wp_remote_request' )
			->andReturn(
				array(
					'body'     => $body,
					'response' => array( 'code' => $status ),
				)
			);

		WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )
			->andReturnUsing(
				function ( $response ) {
					return isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 200;
				}
			);

		WP_Mock::userFunction( 'wp_remote_retrieve_body' )
			->andReturnUsing(
				function ( $response ) {
					return is_array( $response ) && array_key_exists( 'body', $response ) ? $response['body'] : '';
				}
			);

		WP_Mock::userFunction( 'apply_filters' )
			->andReturnUsing(
				function ( $hook, $value ) {
					return $value;
				}
			);
	}
}
