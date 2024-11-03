<?php
/**
 * File to handle sodium-tasks.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Crypt;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Exception;
use ExternalFilesInMediaLibrary\Plugin\Crypt_Base;
use ExternalFilesInMediaLibrary\Plugin\Log;
use SodiumException;

/**
 * Object to handle crypt tasks with Sodium.
 */
class Sodium extends Crypt_Base {
	/**
	 * Name of the method.
	 *
	 * @var string
	 */
	protected string $name = 'sodium';

	/**
	 * Coding-ID to use.
	 *
	 * @var int
	 */
	private int $coding_id = SODIUM_BASE64_VARIANT_ORIGINAL;

	/**
	 * Constructor for this object.
	 *
	 * @throws SodiumException Possible exception.
	 * @throws Exception Possible exception.
	 */
	protected function __construct() {
		$this->set_hash( sodium_base642bin( get_option( EFML_SODIUM_HASH, '' ), $this->get_coding_id() ) );

		// initially generate a hash if it is empty.
		if ( empty( $this->get_hash() ) ) {
			$hash = sodium_crypto_aead_xchacha20poly1305_ietf_keygen();
			$this->set_hash( $hash );
			update_option( EFML_SODIUM_HASH, sodium_bin2base64( $this->get_hash(), $this->get_coding_id() ) );
		}

		parent::__construct();
	}

	/**
	 * Get encrypted text.
	 *
	 * @param string $plain_text The text to encrypt.
	 *
	 * @return string
	 */
	public function encrypt( string $plain_text ): string {
		try {
			// generate a nonce.
			$nonce = random_bytes( SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES );

			// return encrypted text as base64.
			return sodium_bin2base64( $nonce . ':' . sodium_crypto_aead_aes256gcm_encrypt( $plain_text, '', $nonce, $this->get_hash() ), $this->get_coding_id() );
		} catch ( Exception $e ) {
			// log this event.
			/* translators: %1$s will nbe replaced by our support-URL. */
			Log::get_instance()->create( sprintf( __( 'Error on encrypting with PHP-sodium. Please contact <a href="%1$s">our support forum</a> about this problem.', 'external-files-in-media-library' ), esc_url( Helper::get_plugin_support_url() ) ), '', 'error' );

			// return nothing.
			return '';
		}
	}

	/**
	 * Get decrypted text.
	 *
	 * @param string $encrypted_text Text to encrypt.
	 *
	 * @return string
	 */
	public function decrypt( string $encrypted_text ): string {
		try {
			// split into the parts after converting from base64- to binary-string.
			$parts = explode( ':', sodium_base642bin( $encrypted_text, $this->get_coding_id() ) );

			// bail if array is empty or does not have 2 entries.
			if ( empty( $parts ) || count( $parts ) !== 2 ) {
				return '';
			}

			// return decrypted text.
			return sodium_crypto_aead_aes256gcm_decrypt( $parts[1], '', $parts[0], $this->get_hash() );
		} catch ( Exception $e ) {
			// log this event.
			/* translators: %1$s will nbe replaced by our support-URL. */
			Log::get_instance()->create( sprintf( __( 'Error on decrypting with PHP-sodium. Please contact <a href="%1$s">our support forum</a> about this problem.', 'external-files-in-media-library' ), esc_url( Helper::get_plugin_support_url() ) ), '', 'error' );

			// return nothing.
			return '';
		}
	}

	/**
	 * Return the used coding ID.
	 *
	 * @return int
	 */
	private function get_coding_id(): int {
		return $this->coding_id;
	}
}
