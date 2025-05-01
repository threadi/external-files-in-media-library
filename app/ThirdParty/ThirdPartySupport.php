<?php
/**
 * This file contains the handling of third party support we provide.
 *
 * This could be another WP-plugin we adapt.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle third party support.
 */
class ThirdPartySupport {

	/**
	 * Instance of actual object.
	 *
	 * @var ?ThirdPartySupport
	 */
	private static ?ThirdPartySupport $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() { }

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return ThirdPartySupport
	 */
	public static function get_instance(): ThirdPartySupport {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Run activation tasks on each supported ThirdParty-plugin.
	 *
	 * @return void
	 */
	public function activation(): void {
		foreach ( $this->get_third_party_support() as $class_name ) {
			// bail if class does not exist.
			if ( ! class_exists( $class_name ) ) {
				continue;
			}

			// extend the class name to match callable.
			$class_name .= '::get_instance';

			// bail if it is not callable.
			if ( ! is_callable( $class_name ) ) {
				continue;
			}

			// initiate object.
			$obj = $class_name();

			// bail if object is not a third party object.
			if ( ! $obj instanceof ThirdParty_Base ) {
				continue;
			}

			// activate this object.
			$obj->activation();
		}
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		foreach ( $this->get_third_party_support() as $class_name ) {
			// bail if class does not exist.
			if ( ! class_exists( $class_name ) ) {
				continue;
			}

			// extend the class name to match callable.
			$class_name .= '::get_instance';

			// bail if it is not callable.
			if ( ! is_callable( $class_name ) ) {
				continue;
			}

			// initiate object.
			$obj = $class_name();

			// bail if object is not a third party object.
			if ( ! $obj instanceof ThirdParty_Base ) {
				continue;
			}

			// initialize this object.
			$obj->init();
		}
	}

	/**
	 * Return list of third party support we implement.
	 *
	 * @return array<string>
	 */
	private function get_third_party_support(): array {
		$list = array(
			'ExternalFilesInMediaLibrary\ThirdParty\BlockEditor',
			'ExternalFilesInMediaLibrary\ThirdParty\Downloadlist',
			'ExternalFilesInMediaLibrary\ThirdParty\Elementor',
			'ExternalFilesInMediaLibrary\ThirdParty\EnableMediaReplace',
			'ExternalFilesInMediaLibrary\ThirdParty\Exmage',
			'ExternalFilesInMediaLibrary\ThirdParty\Massedge',
			'ExternalFilesInMediaLibrary\ThirdParty\PreventDirectAccess',
			'ExternalFilesInMediaLibrary\ThirdParty\RankMath',
			'ExternalFilesInMediaLibrary\ThirdParty\WooCommerce',
			'ExternalFilesInMediaLibrary\ThirdParty\Yoast',
		);

		/**
		 * Filter the list of third party support.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array<string> $list List of third party support.
		 */
		return apply_filters( 'eml_third_party_support', $list );
	}
}
