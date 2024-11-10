<?php
/**
 * File to handle crypt-tasks.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle crypt tasks.
 */
class Crypt {
	/**
	 * Define the method for crypt-tasks.
	 *
	 * @var string
	 */
	private string $method = '';

	/**
	 * Instance of this object.
	 *
	 * @var ?Crypt
	 */
	private static ?Crypt $instance = null;

	/**
	 * Constructor which configure the active method.
	 */
	private function __construct() {
		if ( function_exists( 'openssl_encrypt' ) ) {
			$this->set_method_name( 'openssl' );
		} elseif ( sodium_crypto_aead_aes256gcm_is_available() ) {
			$this->set_method_name( 'sodium' );
		}
	}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Crypt {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Return the method object to use to encryption.
	 *
	 * @return false|Crypt_Base
	 */
	public function get_method(): false|Crypt_Base {
		return $this->get_method_by_name( $this->method );
	}

	/**
	 * Return encrypted string.
	 *
	 * @param string $encrypted_text Text to decrypt.
	 *
	 * @return string
	 */
	public function encrypt( string $encrypted_text ): string {
		// get the active method.
		$method_obj = $this->get_method();

		// bail if method could not be found.
		if ( false === $method_obj ) {
			return '';
		}

		return $method_obj->encrypt( $encrypted_text );
	}

	/**
	 * Return decrypted string.
	 *
	 * @param string $encrypted_text Text to decrypt.
	 *
	 * @return string
	 */
	public function decrypt( string $encrypted_text ): string {
		// get the active method.
		$method_obj = $this->get_method();

		// bail if method could not be found.
		if ( false === $method_obj ) {
			// log this event.
			/* translators: %1$s will be replaced by our support-URL. */
			Log::get_instance()->create( sprintf( __( 'No supported encryption method found. Please contact <a href="%1$s">our support forum</a> about this problem.', 'external-files-in-media-library' ), esc_url( Helper::get_plugin_support_url() ) ), '', 'error' );
			return '';
		}

		return $method_obj->decrypt( $encrypted_text );
	}

	/**
	 * Return list of supported methods.
	 *
	 * @return array
	 */
	private function get_available_methods(): array {
		$methods = array(
			'ExternalFilesInMediaLibrary\Plugin\Crypt\OpenSsl',
			'ExternalFilesInMediaLibrary\Plugin\Crypt\Sodium',
		);

		/**
		 * Filter the available crypt-methods.
		 *
		 * @since 1.0.0 Available since 1.0.0.
		 * @param array $methods List of methods.
		 */
		return apply_filters( 'eml_crypt_methods', $methods );
	}

	/**
	 * Set the method name.
	 *
	 * @param string $method_name Name of the method (like 'openssl').
	 *
	 * @return void
	 */
	private function set_method_name( string $method_name ): void {
		$this->method = $method_name;
	}

	/**
	 * Get method by name.
	 *
	 * @param string $method The name of the method.
	 *
	 * @return false|Crypt_Base
	 */
	private function get_method_by_name( string $method ): false|Crypt_Base {
		foreach ( $this->get_available_methods() as $method_class_name ) {
			$obj = call_user_func( $method_class_name . '::get_instance' );

			// bail if object is not Crypt_Base.
			if ( ! $obj instanceof Crypt_Base ) {
				continue;
			}

			// bail if name does not match.
			if ( $method !== $obj->get_name() ) {
				continue;
			}

			// return the object.
			return $obj;
		}

		// return false if no object could be found.
		return false;
	}
}
