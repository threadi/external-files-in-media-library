<?php
/**
 * File which handles the AWS S3 support as own protocol.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\S3;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Aws\S3\Exception\S3Exception;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\S3;

/**
 * Object to handle different protocols.
 */
class Protocol extends Protocol_Base {
	/**
	 * Return whether this protocol could be used for the given URL.
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
		// bail if this is not an AWS S3 URL.
		if ( ! str_starts_with( $this->get_url(), "/" . S3::get_instance()->get_label() ) ) {
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
		$url = str_replace( "/" . S3::get_instance()->get_label() . '/' . $this->get_api_key() . '/', '', $this->get_url() );

		// get our own S3 object.
		$s3 = S3::get_instance();
		$s3->set_login( $this->get_login() );
		$s3->set_password( $this->get_password() );
		$s3->set_api_key( $this->get_api_key() );

		// get the S3Client.
		$s3_client = $s3->get_s3_client();

		// get list of directories and files in given bucket.
		try {
			// get mime type.
			$mime_type = wp_check_filetype( basename( $url ) );

			// generate tmp file path.
			$tmp_file = str_replace( '.tmp', '', wp_tempnam() . '.' . $mime_type['ext'] );

			// set query for the file and save it in tmp dir.
			$query = array(
				'Bucket' => $this->get_api_key(),
				'Key' => $url,
				'SaveAs' => $tmp_file
			);

			// try to load the requested bucket.
			$result = $s3_client->getObject( $query );

			// create the array for the file data.
			$file_data = array(
				'title'         => basename( $url ),
				'local'         => true, // TODO abhängig von Erreichbarkeit des Bucket.
				'url'           => $this->get_url(),
				'last-modified' => Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', absint( $result->get( 'LastModified')->format( 'U' ) ) ) ),
				'filesize' => $result->get( 'ContentLength' ),
				'mime-type' => $result->get( 'ContentType' ),
				'tmp-file' => $tmp_file
			);
			return array( $file_data );
		} catch( S3Exception $e ) {
			Log::get_instance()->create( __( 'Error during request of AWS S3 file:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', '', 'error' );
			return array();
		}
	}

	/**
	 * Return whether the file should be saved local (true) or not (false).
	 *
	 * @return bool
	 */
	public function should_be_saved_local(): bool {
		// TODO abhängig von Erreichbarkeit des Bucket.
		return true;
	}

	/**
	 * Return whether this URL could change its hosting.
	 *
	 * @return bool
	 */
	public function can_change_hosting(): bool {
		// TODO abhängig von Erreichbarkeit des Bucket.
		return false;
	}

	/**
	 * Return the title of this protocol object.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return S3::get_instance()->get_label();
	}
}
