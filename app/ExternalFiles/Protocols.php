<?php
/**
 * File which handle different protocols.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Log;

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
			self::$instance = new self();
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
			'ExternalFilesInMediaLibrary\ExternalFiles\Protocols\File',
			'ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Ftp',
			'ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http',
			'ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Sftp',
		);

		/**
		 * Filter the list of available protocols.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $list List of protocol handler.
		 */
		return apply_filters( 'eml_protocols', $list );
	}

	/**
	 * Return the protocol handler for the given URL.
	 *
	 * This can be used before an external file object for this URL exist.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return Protocol_Base|false
	 */
	public function get_protocol_object_for_url( string $url ): Protocol_Base|false {
		// define variable for result.
		$result = false;

		foreach ( $this->get_protocols() as $protocol_name ) {
			// bail if name is not a string.
			if ( ! is_string( $protocol_name ) ) {
				continue;
			}

			// bail if name is not an existing class.
			if ( ! class_exists( $protocol_name ) ) {
				continue;
			}

			// create object with given URL.
			$obj = new $protocol_name( $url );

			// bail if protocol is not a Protocol_Base object.
			if ( ! $obj instanceof Protocol_Base ) {
				continue;
			}

			// bail if protocol could not be used.
			if ( ! $obj->is_available() ) {
				Log::get_instance()->create( __( 'Your hosting does not match the requirements to import the given URL. You will not be able to use this URL for external files in media library.', 'external-files-in-media-library' ), esc_html( $url ), 'error', 0 );
				continue;
			}

			// bail if URL is not compatible with this URL.
			if ( ! $obj->is_url_compatible() ) {
				continue;
			}

			// set as return value.
			$result = $obj;
		}

		// return the resulting value.
		return $result;
	}

	/**
	 * Return the protocol handler for a given external file object.
	 *
	 * @param File $external_file The object for the external file.
	 *
	 * @return Protocol_Base|false
	 */
	public function get_protocol_object_for_external_file( File $external_file ): Protocol_Base|false {
		foreach ( $this->get_protocols() as $protocol_name ) {
			// bail if name is not a string.
			if ( ! is_string( $protocol_name ) ) {
				continue;
			}

			// bail if class does not exist.
			if ( ! class_exists( $protocol_name ) ) {
				continue;
			}

			// create object.
			$obj = new $protocol_name( $external_file->get_url( true ) );

			// bail if object is not a Protocol_Base and not with the URL compatible.
			if ( ! ( $obj instanceof Protocol_Base && $obj->is_url_compatible() ) ) {
				continue;
			}

			// configure its credentials, even it nothing are set.
			$obj->set_login( $external_file->get_login() );
			$obj->set_password( $external_file->get_password() );

			// return resulting object.
			return $obj;
		}

		// return false if no protocol could be found.
		return false;
	}
}
