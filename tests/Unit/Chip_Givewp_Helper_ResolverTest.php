<?php
/**
 * Unit tests for the payment-method group resolver in Chip_Givewp_Helper.
 *
 * The resolver is responsible for turning a saved whitelist into the methods
 * actually sent to the gateway. The cases below pin the behaviours that a
 * saved legacy identifier used to break.
 *
 * @package GiveWPCHIP
 */

namespace GiveWPCHIP\Tests\Unit;

use Chip_Givewp_API;
use Chip_Givewp_Helper;
use WP_Mock;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Chip_Givewp_Helper::resolve_duitnow_methods
 */
class Chip_Givewp_Helper_ResolverTest extends TestCase {

	/**
	 * Reset WP_Mock and the API class's instance cache before each test.
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
	 * Stubs /payment_methods/ to report the given available methods.
	 *
	 * @param array $available Methods the brand exposes.
	 * @return void
	 */
	private function mock_available_methods( array $available ) {
		WP_Mock::userFunction(
			'get_transient',
			array(
				'return' => false,
			)
		);

		WP_Mock::userFunction(
			'set_transient',
			array(
				'return' => true,
			)
		);

		$body = json_encode( array( 'available_payment_methods' => $available ) );

		WP_Mock::userFunction(
			'wp_remote_request',
			array(
				'return' => array(
					'body'     => $body,
					'response' => array( 'code' => 200 ),
				),
			)
		);

		WP_Mock::userFunction(
			'wp_remote_retrieve_body',
			array(
				'return' => $body,
			)
		);

		WP_Mock::userFunction(
			'wp_remote_retrieve_response_code',
			array(
				'return' => 200,
			)
		);

		WP_Mock::userFunction(
			'apply_filters',
			array(
				'return' => true,
			)
		);
	}

	/**
	 * Calls the resolver with the given whitelist.
	 *
	 * @param array $whitelist Saved whitelist.
	 * @return array
	 */
	private function resolve( array $whitelist ) {
		return Chip_Givewp_Helper::resolve_duitnow_methods(
			$whitelist,
			'MYR',
			1000,
			'test_secret',
			'test_brand',
			1
		);
	}

	/**
	 * A legacy 'duitnow_qr' maps to the modern 'dnqr' when the brand has both.
	 *
	 * This is the regression this file exists for: 'duitnow_qr' is the legacy
	 * identifier, and a brand that has migrated to 'dnqr' must not be left
	 * sending 'duitnow_qr'.
	 */
	public function test_legacy_duitnow_qr_resolves_to_modern_dnqr(): void {
		$this->mock_available_methods( array( 'dnqr', 'duitnow_qr', 'fpx' ) );

		$result = $this->resolve( array( 'duitnow_qr', 'fpx' ) );

		$this->assertContains( 'dnqr', $result );
		$this->assertNotContains( 'duitnow_qr', $result );
	}

	/**
	 * A legacy 'razer_shopeepay' maps to the modern 'shopee_pay' when both exist.
	 */
	public function test_legacy_razer_shopeepay_resolves_to_modern_shopee_pay(): void {
		$this->mock_available_methods( array( 'shopee_pay', 'razer_shopeepay', 'fpx' ) );

		$result = $this->resolve( array( 'razer_shopeepay', 'fpx' ) );

		$this->assertContains( 'shopee_pay', $result );
		$this->assertNotContains( 'razer_shopeepay', $result );
	}

	/**
	 * A legacy 'razer_shopeepay' still resolves when the brand only reports
	 * the modern 'shopee_pay'.
	 *
	 * Renaming the legacy key in place would leave this case dependent on an
	 * extra API round trip or on the caller having expanded the group first.
	 * Resolving it inside the resolver is what makes both pairs behave the
	 * same way.
	 */
	public function test_legacy_razer_shopeepay_resolves_when_brand_only_reports_modern(): void {
		$this->mock_available_methods( array( 'shopee_pay', 'fpx' ) );

		$result = $this->resolve( array( 'razer_shopeepay' ) );

		$this->assertContains( 'shopee_pay', $result );
		$this->assertNotContains( 'razer_shopeepay', $result );
	}

	/**
	 * A legacy 'duitnow_qr' still resolves when the brand only reports the
	 * modern 'dnqr'.
	 *
	 * The shape worth pinning: 'duitnow_qr' configured, brand reporting only
	 * 'dnqr'. The configured group is widened before the intersection, so the
	 * pair still resolves instead of the intersection coming back empty.
	 */
	public function test_legacy_duitnow_qr_resolves_when_brand_only_reports_modern(): void {
		$this->mock_available_methods( array( 'dnqr', 'fpx' ) );

		$result = $this->resolve( array( 'duitnow_qr' ) );

		$this->assertContains( 'dnqr', $result );
		$this->assertNotContains( 'duitnow_qr', $result );
	}

	/**
	 * A brand that only exposes the legacy identifier keeps working.
	 *
	 * The pair must not be resolved towards a method the brand does not have.
	 */
	public function test_legacy_identifier_is_kept_when_brand_only_reports_legacy(): void {
		$this->mock_available_methods( array( 'duitnow_qr', 'fpx' ) );

		$result = $this->resolve( array( 'dnqr' ) );

		$this->assertContains( 'duitnow_qr', $result );
		$this->assertNotContains( 'dnqr', $result );
	}

	/**
	 * A whitelist holding neither group never reaches the API.
	 */
	public function test_whitelist_without_groups_short_circuits(): void {
		// No WP_Mock HTTP stubs: any API call would fail the test.
		$result = $this->resolve( array( 'fpx', 'cards' ) );

		$this->assertSame( array( 'fpx', 'cards' ), $result );
	}

	/**
	 * Both groups resolve independently in one call.
	 */
	public function test_both_groups_resolve_together(): void {
		$this->mock_available_methods( array( 'dnqr', 'duitnow_qr', 'shopee_pay', 'razer_shopeepay', 'fpx' ) );

		$result = $this->resolve( array( 'duitnow_qr', 'razer_shopeepay', 'fpx' ) );

		$this->assertContains( 'dnqr', $result );
		$this->assertContains( 'shopee_pay', $result );
		$this->assertContains( 'fpx', $result );
		$this->assertNotContains( 'duitnow_qr', $result );
		$this->assertNotContains( 'razer_shopeepay', $result );
	}

	/**
	 * Non-group methods pass through untouched alongside a resolved group.
	 */
	public function test_non_group_methods_are_preserved(): void {
		$this->mock_available_methods( array( 'dnqr', 'fpx', 'razer_grabpay' ) );

		$result = $this->resolve( array( 'duitnow_qr', 'fpx', 'razer_grabpay', 'mpgs_apple_pay' ) );

		$this->assertContains( 'fpx', $result );
		$this->assertContains( 'razer_grabpay', $result );
		$this->assertContains( 'mpgs_apple_pay', $result );
		$this->assertContains( 'dnqr', $result );
	}
}
