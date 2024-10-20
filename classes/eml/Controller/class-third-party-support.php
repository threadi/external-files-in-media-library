<?php
/**
 * This file contains the handling of third party support we provide.
 *
 * This could be another WP-plugin or an external file platform we adapt.
 *
 * @package thread\eml
 */

namespace threadi\eml\Controller;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle third party support.
 */
class Third_Party_Support {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Third_Party_Support
	 */
	private static ?Third_Party_Support $instance = null;

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
	 * @return Third_Party_Support
	 */
	public static function get_instance(): Third_Party_Support {
		if ( is_null( self::$instance ) ) {
			self::$instance = new Third_Party_Support();
		}

		return self::$instance;
	}

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		foreach ( $this->get_third_party_support() as $class_name ) {
			if ( class_exists( $class_name ) ) {
				$obj = call_user_func( $class_name . '::get_instance' );
				$obj->init();
			}
		}
	}

	/**
	 * Return list of third party support we implement.
	 *
	 * @return array
	 */
	private function get_third_party_support(): array {
		$list = array(
			'threadi\eml\Controller\ThirdPartySupport\Downloadlist',
			'threadi\eml\Controller\ThirdPartySupport\Exmage',
			'threadi\eml\Controller\ThirdPartySupport\GoogleDrive',
			'threadi\eml\Controller\ThirdPartySupport\Imgur',
			'threadi\eml\Controller\ThirdPartySupport\Massedge',
		);

		/**
		 * Filter the list of third party support.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $list List of third party support.
		 */
		return apply_filters( 'eml_third_party_support', $list );
	}
}
