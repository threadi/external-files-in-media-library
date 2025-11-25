<?php
/**
 * File which handle the support for different protocols (like HTTP, FTP ...).
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
	 * @return array<string>
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
		 * @param array<string> $list List of protocol handler.
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

		// loop through the supported protocols and check which one is supporting the given URL.
		foreach ( $this->get_protocols() as $protocol_name ) {
			// bail if result is already set.
			if ( $result ) {
				continue;
			}

			// bail if name is not an existing class.
			if ( ! class_exists( $protocol_name ) ) {
				/* translators: %1$s will be replaced by a name. */
				Log::get_instance()->create( sprintf( __( 'Protocol %1$s does not exist as object.', 'external-files-in-media-library' ), ' <code>' . esc_html( $protocol_name ) . '</code>' ), esc_html( $url ), 'error', 2 );
				continue;
			}

			// create object with given URL.
			$obj = new $protocol_name( $url );

			// bail if protocol is not a Protocol_Base object.
			if ( ! $obj instanceof Protocol_Base ) {
				/* translators: %1$s will be replaced by a name. */
				Log::get_instance()->create( sprintf( __( 'Protocol %1$s is not a Protocol_Base object.', 'external-files-in-media-library' ), ' <code>' . esc_html( $protocol_name ) . '</code>' ), esc_html( $url ), 'error', 2 );
				continue;
			}

			// bail if protocol could not be used.
			if ( ! $obj->is_available() ) {
				/* translators: %1$s will be replaced by a protocol name (like SFTP). */
				Log::get_instance()->create( sprintf( __( 'The protocol %1$s is not usable in this hosting.', 'external-files-in-media-library' ), ' <code>' . esc_html( $protocol_name ) . '</code>' ), esc_html( $url ), 'info', 2 );
				continue;
			}

			// bail if URL is not compatible with this URL.
			if ( ! $obj->is_url_compatible() ) {
				/* translators: %1$s will be replaced by a protocol name (like SFTP). */
				Log::get_instance()->create( sprintf( __( 'The given URL is not compatible with the protocol %1$s. Further tests for other protocols will follow.', 'external-files-in-media-library' ), ' <code>' . esc_html( $protocol_name ) . '</code>' ), esc_html( $url ), 'info', 2 );
				continue;
			}

			// set as return value.
			$result = $obj;
		}

		// bail if no supported protocol could be found for this URL.
		if ( ! $result ) {
			// log this event.
			Log::get_instance()->create( __( 'Specified URL is using a not supported TCP protocol. You will not be able to use this URL for external files in media library.', 'external-files-in-media-library' ), esc_html( $url ), 'error', 0, Import::get_instance()->get_identified() );

			// return false in this case.
			return false;
		}

		// log this event.
		/* translators: %1$s will be replaced by a protocol name (like SFTP). */
		Log::get_instance()->create( sprintf( __( 'Using protocol %1$s for this URL.', 'external-files-in-media-library' ), '<em>' . $result->get_title() . '</em>' ), esc_html( $url ), 'success', 0, Import::get_instance()->get_identified() );

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
			// bail if class does not exist.
			if ( ! class_exists( $protocol_name ) ) {
				continue;
			}

			// create object.
			$protocol_obj = new $protocol_name( $external_file->get_url( true ) );

			// bail if object is not a Protocol_Base.
			if ( ! $protocol_obj instanceof Protocol_Base ) {
				continue;
			}

			// bail if URL is compatible.
			if ( ! $protocol_obj->is_url_compatible() ) {
				continue;
			}

			// configure its fields, even it nothing are set.
			$protocol_obj->set_fields( $external_file->get_fields() );

			// return resulting object.
			return $protocol_obj;
		}

		// return false if no protocol could be found.
		return false;
	}
}
