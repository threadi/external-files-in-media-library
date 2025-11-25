<?php
/**
 * File to handle audio files.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\File_Types;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\File_Types_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Object to handle audios.
 */
class Audio extends File_Types_Base {
	/**
	 * Name of the file type.
	 *
	 * @var string
	 */
	protected string $name = 'Audio';

	/**
	 * Define mime types this object is used for.
	 *
	 * @var array|string[]
	 */
	protected array $mime_types = array(
		'audio/mpeg',
		'audio/aac',
		'audio/ogg',
		'audio/webm',
	);

	/**
	 * Return whether files of this type are proxied by default.
	 *
	 * @return bool
	 */
	public function is_proxy_default_enabled(): bool {
		return false;
	}

	/**
	 * Return the file type title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Audio', 'external-files-in-media-library' );
	}

	/**
	 * Output of proxied file.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function get_proxied_file(): void {
		// bail if no file is set.
		if ( ! $this->get_file() ) {
			exit;
		}

		// get the file object.
		$external_file_obj = $this->get_file();

		// set start byte.
		$start = 0;

		// set end byte to size - 1.
		$end = $external_file_obj->get_filesize() - 1;

		// set content type in header.
		header( 'Content-type: ' . $external_file_obj->get_mime_type() );

		// set ranges.
		header( 'Accept-Ranges: bytes' );

		// set bytes for response.
		header( 'Content-Range: bytes ' . ( $start - $end / $external_file_obj->get_filesize() ) );

		// set max length.
		header( 'Content-Length: ' . $external_file_obj->get_filesize() );

		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

		// return file content via WP filesystem.
		echo $wp_filesystem->get_contents( $external_file_obj->get_cache_file() ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * Return whether this file should be saved locally.
	 *
	 * @return bool
	 */
	public function is_local(): bool {
		return 'local' === get_option( 'eml_audio_mode' );
	}

	/**
	 * Return whether this file should be proxied.
	 *
	 * @return bool
	 */
	public function is_proxy_enabled(): bool {
		return 'external' === get_option( 'eml_audio_mode' ) && 1 === absint( get_option( 'eml_audio_proxy' ) );
	}

	/**
	 * Return true if cache age has been reached its expiration.
	 *
	 * @return bool
	 */
	public function is_cache_expired(): bool {
		// bail if no file is set.
		if ( ! $this->get_file() ) {
			return true;
		}

		// bail if no proxy age is set.
		if ( absint( get_option( 'eml_audio_proxy_max_age' ) ) <= 0 ) {
			return false;
		}

		// compare cache file date with max proxy age.
		return filemtime( $this->get_file()->get_cache_file() ) < ( time() - absint( get_option( 'eml_audio_proxy_max_age', 24 ) ) * 60 * 60 );
	}

	/**
	 * Set meta-data for the file if it is hosted extern and with proxy.
	 *
	 * @return void
	 */
	public function set_metadata(): void {
		// bail if no file is set.
		if ( ! $this->get_file() ) {
			return;
		}

		// get the file object.
		$external_file_obj = $this->get_file();

		// bail if file should be saved locally (then WP will handle this for us).
		if ( $external_file_obj->is_locally_saved() ) {
			return;
		}

		// bail if proxy is not enabled for images.
		if ( ! $this->is_proxy_enabled() ) {
			return;
		}

		// show deprecated hint for old hook.
		do_action_deprecated( 'eml_audio_meta_data', array( $external_file_obj ), '5.0.0', 'efml_audio_meta_data' );

		/**
		 * Run additional tasks to add custom meta data on external hostet files.
		 *
		 * @since 3.1.0 Available since 3.1.0.
		 * @param \ExternalFilesInMediaLibrary\ExternalFiles\File $external_file_obj The external files object.
		 */
		do_action( 'efml_audio_meta_data', $external_file_obj );
	}
}
