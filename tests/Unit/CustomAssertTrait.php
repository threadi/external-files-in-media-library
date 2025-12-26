<?php
/**
 * Helper for unit tests.
 *
 * @package external-files-in-media-library
 */

namespace Unit\Plugin;

/**
 * Add helper functions.
 */
trait CustomAssertTrait {
	public function assertArrayHasObjectOfType( $type, $array, $message = '' ): void {

		$found = false;

		foreach( $array as $obj ) {
			if( get_class( $obj ) === $type ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found, $message );
	}
}
