<?php
/**
 * File which handles the DropBox support as own protocol.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\DropBox;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\DropBox;
use GuzzleHttp\Exception\ClientException;
use Spatie\Dropbox\Client;

/**
 * Object to handle different protocols.
 */
class Protocol extends Protocol_Base {
	/**
	 * Return whether the file using this protocol is available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return true;
	}

	/**
	 * Check if URL is compatible with the given protocol.
	 *
	 * @return bool
	 */
	public function is_url_compatible(): bool {
		// bail if this is not a DropBox URL.
		if ( ! str_starts_with( $this->get_url(), DropBox::get_instance()->get_name() ) ) {
			return false;
		}

		// return true to use this protocol.
		return true;
	}

	/**
	 * Check format of given URL.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool
	 */
	public function check_url( string $url ): bool {
		// bail if empty URL is given.
		if ( empty( $url ) ) {
			return false;
		}

		// return true as DropBox URLs are available.
		return true;
	}

	/**
	 * Return infos to each given URL.
	 *
	 * @return array<int,array<string,mixed>> List of files with its infos.
	 */
	public function get_url_infos(): array {
		// remove our marker from the URL.
		$url = str_replace( DropBox::get_instance()->get_name(), '', $this->get_url() );

		// get the client with the given token.
		$client = new Client( DropBox::get_instance()->get_access_token() );

		// get the file data.
		$file_data = array();
		try {
			$file_data = $client->getMetadata( $url );
		} catch ( ClientException $e ) {
			Log::get_instance()->create( __( 'Error during request of DropBox file:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', '', 'error', 1 );
		}

		// bail if file_data is empty (e.g. if error occurred).
		if ( empty( $file_data ) ) {
			return array();
		}

		// initialize basic array for file data.
		$results = array(
			'title'         => $file_data['name'],
			'local'         => true,
			'url'           => $this->get_url(),
			'last-modified' => absint( strtotime( $file_data['client_modified'] ) ),
		);

		// get mime type.
		$mime_type = wp_check_filetype( $results['title'] );

		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

		// set the file as tmp-file for import.
		$results['tmp-file'] = str_replace( '.tmp', '', wp_tempnam() . '.' . $mime_type['ext'] );

		// get the file from DropBox.
		$content = stream_get_contents( $client->download( $url ) );

		// bail if content could not be loaded.
		if ( ! is_string( $content ) ) { // @phpstan-ignore function.alreadyNarrowedType
			return array();
		}

		// and save this content als tmp-file.
		$wp_filesystem->put_contents( $results['tmp-file'], $content );

		// set the file size.
		$results['filesize'] = absint( $file_data['size'] );

		// set the mime type.
		$results['mime-type'] = $mime_type['type'];

		// return the resulting array as list of files (although it is only one).
		return array(
			$results,
		);
	}

	/**
	 * Return whether the file should be saved local (true) or not (false).
	 *
	 * @return bool
	 */
	public function should_be_saved_local(): bool {
		return true;
	}

	/**
	 * Return whether this URL could change its hosting.
	 *
	 * @return bool
	 */
	public function can_change_hosting(): bool {
		return false;
	}

	/**
	 * Return the title of this protocol object.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return DropBox::get_instance()->get_label();
	}
}
