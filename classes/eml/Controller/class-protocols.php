<?php
/**
 * File which handle different protocols.
 *
 * @package thread\eml
 */

namespace threadi\eml\Controller;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Object to handle different protocols.
 */
class Protocols {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Protocols
	 */
	private static ?Protocols $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Protocols
	 */
	public static function get_instance(): Protocols {
		if ( is_null( self::$instance ) ) {
			self::$instance = new Protocols();
		}

		return self::$instance;
	}

	/**
	 * Return list of supported protocols.
	 *
	 * @return array
	 */
	private function get_protocols(): array {
		$list = array(
			'threadi\eml\Controller\Protocols\Ftp',
			'threadi\eml\Controller\Protocols\Http'
		);

		/**
		 * Filter the list of available protocols.
		 *
		 * @since 1.4.0 Available since 1.4.0.
		 * @param array $list List of protocol handler.
		 */
		return apply_filters( 'eml_protocols', $list );
	}

	/**
	 * Return the protocol handler for the given URL.
	 *
	 * @param string $url The url to check.
	 *
	 * @return Protocol_Base|false
	 */
	public function get_protocol_object_for_url( string $url ): Protocol_Base|false {
		foreach( $this->get_protocols() as $protocol_name ) {
			if ( is_string( $protocol_name ) && class_exists( $protocol_name ) ) {
				$obj = new $protocol_name( $url );
				if( $obj instanceof Protocol_Base && $obj->is_url_compatible() ) {
					return $obj;
				}
			}
		}

		// return false if no protocol could be found.
		return false;
	}
}
