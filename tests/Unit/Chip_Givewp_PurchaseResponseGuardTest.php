<?php
/**
 * Class guard for the purchase-response handling pattern.
 *
 * The two defects fixed alongside this test were not isolated mistakes: each was
 * a PATTERN that had been copied to more than one call site.
 *
 * 1. `due` was built inline as `time() + ( absint( $timing ) * 60 )` at BOTH
 *    gateways (the v3 block gateway and the v2 legacy gateway). A behaviour test
 *    can only cover the sites that exist today; it cannot refuse the next copy.
 *
 * 2. Every API response was read with `array_key_exists( 'id', $payment )`
 *    without first checking it is an array. Chip_Givewp_API::call() returns null
 *    for every failure shape, so this pattern is an uncaught TypeError on PHP 8
 *    wherever it appears.
 *
 * A test of the helper alone is blind to both: the helpers can be perfect while
 * nothing calls them. This guard asserts the call sites themselves, so
 * reintroducing either shape anywhere under includes/ fails the suite.
 *
 * @package GiveWPCHIP
 */

namespace GiveWPCHIP\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class Chip_Givewp_PurchaseResponseGuardTest extends TestCase {

	/**
	 * Plugin files that may build purchase requests or read API responses.
	 *
	 * @return array<string, string> Absolute path keyed by a short label.
	 */
	private function sources(): array {
		$root  = dirname( __DIR__, 2 );
		$files = array(
			'block gateway'  => $root . '/includes/block/class-chipgateway.php',
			'legacy gateway' => $root . '/includes/class-chip-givewp-purchase.php',
			'listener'       => $root . '/includes/class-chip-givewp-listener.php',
			'refund button'  => $root . '/includes/admin/class-chip-givewp-refund-button.php',
		);

		foreach ( $files as $label => $path ) {
			$this->assertFileExists( $path, "source not found: {$label}" );
		}

		return $files;
	}

	/**
	 * No call site may build `due` inline; it must go through the resolver.
	 *
	 * The inline form sends `time() + 0` when the timing is unset, which CHIP
	 * rejects with 400 due_not_greater_than_now - so every purchase fails for a
	 * merchant who never touched the timing field.
	 */
	public function test_no_gateway_builds_due_inline(): void {
		foreach ( $this->sources() as $label => $path ) {
			$contents = file_get_contents( $path );

			$this->assertDoesNotMatchRegularExpression(
				'/time\(\)\s*\+\s*\(\s*absint\(/',
				$contents,
				"{$label} builds `due` inline instead of using Chip_Givewp_Helper::resolve_due_timestamp(); " .
				'an unset timing would be sent as a past timestamp'
			);
		}
	}

	/**
	 * Both purchase gateways must route the timing through the resolver.
	 */
	public function test_purchase_gateways_call_the_due_resolver(): void {
		$root = dirname( __DIR__, 2 );

		foreach ( array( 'block' => '/includes/block/class-chipgateway.php', 'legacy' => '/includes/class-chip-givewp-purchase.php' ) as $label => $rel ) {
			$contents = file_get_contents( $root . $rel );

			$this->assertStringContainsString(
				'Chip_Givewp_Helper::resolve_due_timestamp( $due_strict_timing )',
				$contents,
				"the {$label} gateway must resolve `due` through the helper"
			);
		}
	}

	/**
	 * No API response may be read with array_key_exists() before an is_array() check.
	 *
	 * On PHP 8 `array_key_exists( 'id', null )` raises a TypeError, which is not an
	 * Exception, so it escapes `catch ( \Exception )` and surfaces to the donor as
	 * an uncaught fatal (HTTP 500) rather than a payment error.
	 */
	public function test_no_call_site_reads_a_response_without_an_is_array_check(): void {
		$offenders = array();

		foreach ( $this->sources() as $label => $path ) {
			foreach ( file( $path ) as $number => $line ) {
				if ( ! str_contains( $line, "array_key_exists( 'id', \$payment )" ) ) {
					continue;
				}

				if ( ! str_contains( $line, 'is_array( $payment )' ) ) {
					$offenders[] = sprintf( '%s:%d', $label, $number + 1 );
				}
			}
		}

		$this->assertSame(
			array(),
			$offenders,
			'these lines read an API response without first checking it is an array: ' . implode( ', ', $offenders )
		);
	}

	/**
	 * The guard above must be able to see every site it claims to cover.
	 *
	 * A guard whose scan silently matches nothing passes vacuously, so pin the
	 * sites that are expected to carry the is_array() check today. If a site is
	 * added or removed, this fails and the list gets re-derived rather than
	 * quietly shrinking the guard's reach.
	 */
	public function test_the_is_array_guard_is_present_at_every_known_read_site(): void {
		$guarded  = 0;
		$expected = 5;

		foreach ( $this->sources() as $path ) {
			foreach ( file( $path ) as $line ) {
				if ( str_contains( $line, "array_key_exists( 'id', \$payment )" )
					&& str_contains( $line, 'is_array( $payment )' ) ) {
					++$guarded;
				}
			}
		}

		$this->assertSame(
			$expected,
			$guarded,
			'expected the is_array() check at five response read sites '
			. '(v3 create, v3 refund, v2 create, listener callback, refund button)'
		);
	}
}
