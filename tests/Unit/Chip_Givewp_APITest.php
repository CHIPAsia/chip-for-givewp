<?php
/**
 * Unit tests for Chip_Givewp_API.
 *
 * @package GiveWPCHIP
 */

namespace GiveWPCHIP\Tests\Unit;

use Chip_Givewp_API;
use WP_Mock;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Chip_Givewp_API
 */
class Chip_Givewp_APITest extends TestCase {

	/**
	 * Set up WP_Mock before each test.
	 */
	public function setUp(): void {
		WP_Mock::setUp();
		$ref  = new \ReflectionClass( Chip_Givewp_API::class );
		$prop = $ref->getProperty( 'instances' );
		$prop->setAccessible( true );
		$prop->setValue( null, array() );
	}

	/**
	 * Tear down WP_Mock after each test.
	 */
	public function tearDown(): void {
		WP_Mock::tearDown();
	}


	/**
	 * get_public_key returns decoded string when API returns JSON string body.
	 */
	public function test_get_public_key_returns_string_from_json_body(): void {
		$response_body = json_encode( 'simple-key-string' );

		WP_Mock::userFunction( 'wp_remote_request' )
			->once()
			->andReturn( array( 'body' => $response_body ) );

		WP_Mock::userFunction( 'wp_remote_retrieve_body' )
			->andReturnUsing( function ( $response ) {
				return is_array( $response ) && array_key_exists( 'body', $response ) ? $response['body'] : '';
			} );

		WP_Mock::userFunction( 'apply_filters' )
			->with( 'gwp_chip_sslverify', true )
			->andReturn( true );

		$api = Chip_Givewp_API::get_instance( 'test_secret', 'test_brand' );
		$key = $api->get_public_key();

		$this->assertIsString( $key );
		$this->assertSame( 'simple-key-string', $key );
	}

	/**
	 * get_public_key returns null when API returns invalid JSON.
	 */
	public function test_get_public_key_returns_null_for_invalid_json(): void {
		WP_Mock::userFunction( 'wp_remote_request' )
			->once()
			->andReturn( array( 'body' => 'invalid json' ) );

		WP_Mock::userFunction( 'wp_remote_retrieve_body' )
			->andReturnUsing( function ( $response ) {
				return is_array( $response ) && array_key_exists( 'body', $response ) ? $response['body'] : '';
			} );

		WP_Mock::userFunction( 'apply_filters' )
			->with( 'gwp_chip_sslverify', true )
			->andReturn( true );

		$api = Chip_Givewp_API::get_instance( 'test_secret', 'test_brand' );
		$this->assertNull( $api->get_public_key() );
	}

	/**
	 * get_public_key returns null when API response has errors key.
	 */
	public function test_get_public_key_returns_null_when_response_has_errors(): void {
		$response_body = json_encode( array( 'errors' => array( 'Something went wrong' ) ) );

		WP_Mock::userFunction( 'wp_remote_request' )
			->once()
			->andReturn( array( 'body' => $response_body ) );

		WP_Mock::userFunction( 'wp_remote_retrieve_body' )
			->andReturnUsing( function ( $response ) {
				return is_array( $response ) && array_key_exists( 'body', $response ) ? $response['body'] : '';
			} );

		WP_Mock::userFunction( 'apply_filters' )
			->with( 'gwp_chip_sslverify', true )
			->andReturn( true );

		$api  = Chip_Givewp_API::get_instance( 'test_secret', 'test_brand' );
		$key  = $api->get_public_key();
		$this->assertNull( $key );
	}

	/**
	 * get_payment returns decoded array when API returns valid JSON.
	 */
	public function test_get_payment_returns_array_for_valid_response(): void {
		$response_body = json_encode( array( 'id' => 'pay_1', 'status' => 'paid' ) );

		WP_Mock::userFunction( 'wp_remote_request' )
			->once()
			->andReturn( array( 'body' => $response_body ) );

		WP_Mock::userFunction( 'wp_remote_retrieve_body' )
			->andReturnUsing( function ( $response ) {
				return is_array( $response ) && array_key_exists( 'body', $response ) ? $response['body'] : '';
			} );

		WP_Mock::userFunction( 'apply_filters' )
			->with( 'gwp_chip_sslverify', true )
			->andReturn( true );

		$api    = Chip_Givewp_API::get_instance( 'test_secret', 'test_brand' );
		$result = $api->get_payment( 'pay_1' );
		$this->assertIsArray( $result );
		$this->assertSame( 'pay_1', $result['id'] );
		$this->assertSame( 'paid', $result['status'] );
	}

	/**
	 * create_payment sends POST and returns decoded response.
	 */
	public function test_create_payment_returns_decoded_response(): void {
		$response_body = json_encode( array(
			'id'           => 'pay_new',
			'status'      => 'created',
			'checkout_url' => 'https://example.com/checkout',
		) );

		WP_Mock::userFunction( 'wp_remote_request' )
			->once()
			->andReturn( array( 'body' => $response_body ) );

		WP_Mock::userFunction( 'wp_remote_retrieve_body' )
			->andReturnUsing( function ( $response ) {
				return is_array( $response ) && array_key_exists( 'body', $response ) ? $response['body'] : '';
			} );

		WP_Mock::userFunction( 'apply_filters' )
			->with( 'gwp_chip_sslverify', true )
			->andReturn( true );

		$api    = Chip_Givewp_API::get_instance( 'test_secret', 'test_brand' );
		$params = array( 'purchase' => array( 'products' => array() ) );
		$result = $api->create_payment( $params );
		$this->assertIsArray( $result );
		$this->assertSame( 'pay_new', $result['id'] );
		$this->assertSame( 'created', $result['status'] );
		$this->assertSame( 'https://example.com/checkout', $result['checkout_url'] );
	}

	/**
	 * refund_payment sends POST and returns response.
	 */
	public function test_refund_payment_returns_response(): void {
		$response_body = json_encode( array(
			'refund_id' => 'ref_1',
			'status'    => 'pending_refund',
		) );

		WP_Mock::userFunction( 'wp_remote_request' )
			->once()
			->andReturn( array( 'body' => $response_body ) );

		WP_Mock::userFunction( 'wp_remote_retrieve_body' )
			->andReturnUsing( function ( $response ) {
				return is_array( $response ) && array_key_exists( 'body', $response ) ? $response['body'] : '';
			} );

		WP_Mock::userFunction( 'apply_filters' )
			->with( 'gwp_chip_sslverify', true )
			->andReturn( true );

		$api    = Chip_Givewp_API::get_instance( 'test_secret', 'test_brand' );
		$params = array( 'amount' => 1000 );
		$result = $api->refund_payment( 'pay_123', $params );
		$this->assertIsArray( $result );
		$this->assertSame( 'ref_1', $result['refund_id'] );
		$this->assertSame( 'pending_refund', $result['status'] );
	}

	/**
	 * get_instance returns same instance for identical credentials.
	 */
	public function test_get_instance_returns_same_for_identical_credentials(): void {
		WP_Mock::userFunction( 'wp_remote_request' )->andReturn( array( 'body' => 'null' ) );
		WP_Mock::userFunction( 'wp_remote_retrieve_body' )->andReturn( 'null' );
		WP_Mock::userFunction( 'apply_filters' )->andReturn( true );

		$a = Chip_Givewp_API::get_instance( 'sk1', 'brand1' );
		$b = Chip_Givewp_API::get_instance( 'sk1', 'brand1' );

		$this->assertSame( $a, $b );
	}

	/**
	 * get_instance returns different instances for different credentials.
	 */
	public function test_get_instance_returns_different_for_different_credentials(): void {
		WP_Mock::userFunction( 'wp_remote_request' )->andReturn( array( 'body' => 'null' ) );
		WP_Mock::userFunction( 'wp_remote_retrieve_body' )->andReturn( 'null' );
		WP_Mock::userFunction( 'apply_filters' )->andReturn( true );

		$a = Chip_Givewp_API::get_instance( 'sk1', 'brand1' );
		$b = Chip_Givewp_API::get_instance( 'sk2', 'brand2' );

		$this->assertNotSame( $a, $b );
	}
}
