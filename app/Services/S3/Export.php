<?php
/**
 * File to handle export tasks for AWS S3.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\S3;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Aws\Exception\AwsException;
use Aws\Result;
use Exception;
use ExternalFilesInMediaLibrary\ExternalFiles\Export_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\S3;

/**
 * Object for export files to AWS S3.
 */
class Export extends Export_Base {
	/**
	 * Instance of actual object.
	 *
	 * @var Export|null
	 */
	private static ?Export $instance = null;

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
	 * @return Export
	 */
	public static function get_instance(): Export {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Export a file to this service. Returns the external URL if it was successfully and false if not.
	 *
	 * @param int                 $attachment_id The attachment ID.
	 * @param string              $target The target.
	 * @param array<string,mixed> $credentials The credentials.
	 * @return string|bool
	 */
	public function export_file( int $attachment_id, string $target, array $credentials ): string|bool {
		// get the main object.
		$s3_obj = S3::get_instance();

		// set the credentials.
		$s3_obj->set_fields( isset( $credentials['fields'] ) ? $credentials['fields'] : array() );

		// bail if the given URL is not an AWS S3 URL.
		if ( ! str_starts_with( $target, $s3_obj->get_directory() ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Given path is not a AWS S3-URL.', 'external-files-in-media-library' ), $target, 'error' );

			// do nothing more.
			return false;
		}

		// get the file path.
		$file_path = get_attached_file( $attachment_id, true );

		// bail if no file could be found.
		if ( ! is_string( $file_path ) ) {
			return false;
		}

		// get the local WP_Filesystem.
		$wp_filesystem_local = Helper::get_wp_filesystem();

		// bail if source file does not exist.
		if ( ! $wp_filesystem_local->exists( $file_path ) ) {
			return false;
		}

		// set the key for the file to its filename.
		$key = basename( $file_path );

		// prepend the directory from credentials to the key.
		$key = str_replace( $s3_obj->get_directory(), '', $credentials['directory'] ) . $key;

		// get the S3Client.
		$s3_client = $s3_obj->get_s3_client();

		// log this event.
		/* translators: %1$s will be replaced by the attachment ID, %2$s by the bucket name. */
		Log::get_instance()->create( sprintf( __( 'Exporting attachment ID %1$s to AWS S3 bucket %2$s.', 'external-files-in-media-library' ), '<em>' . $attachment_id . '</em>', '<em>' . $s3_obj->get_bucket_name() . '</em>' ), $target, 'info', 2 );

		// upload the file.
		$result = $s3_client->upload( $s3_obj->get_bucket_name(), $key, $wp_filesystem_local->get_contents( $file_path ) );

		// bail if result is not the expected type.
		if ( ! $result instanceof Result ) {
			return false;
		}

		// bail if the file is not public available.
		if ( ! $s3_obj->is_file_public_available( $key, $s3_client ) ) {
			// log this event.
			Log::get_instance()->create( __( 'File would not be public available on your AWS S3 bucket. Check the settings in the bucket to use the export of files.', 'external-files-in-media-library' ), $target, 'error' );

			// return false to prevent to save this file as external file.
			return false;
		}

		// save the used key.
		update_post_meta( $attachment_id, 'efml_aws_s3_key', $key );

		// return the resulting public name of this file.
		return $s3_obj->get_public_url_of_file( $key );
	}

	/**
	 * Delete an exported file.
	 *
	 * @param string              $url           The URL to delete.
	 * @param array<string,mixed> $credentials   The credentials to use.
	 * @param int                 $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function delete_exported_file( string $url, array $credentials, int $attachment_id ): bool {
		// get the main object.
		$s3_obj = S3::get_instance();

		// set the credentials.
		$s3_obj->set_fields( isset( $credentials['fields'] ) ? $credentials['fields'] : array() );

		// bail if the given URL is not an AWS S3 URL.
		if ( ! str_starts_with( $url, $s3_obj->get_directory() ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Given path is not a AWS S3-URL.', 'external-files-in-media-library' ), $url, 'error' );

			// do nothing more.
			return false;
		}

		// get the key for the file.
		$key = get_post_meta( $attachment_id, 'efml_aws_s3_key', true );

		// bail if key is not set.
		if ( empty( $key ) ) {
			return false;
		}

		// get the S3Client.
		$s3_client = $s3_obj->get_s3_client();

		// delete the file.
		try {
			$s3_client->deleteObject(
				array(
					'Bucket' => $s3_obj->get_bucket_name(),
					'Key'    => $key,
				)
			);
		} catch ( AwsException | Exception $e ) {
			// add log entry.
			Log::get_instance()->create( __( 'File could not be deleted! Error:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', '', 'error' );

			// do nothing more.
			return false;
		}

		// return true as file has been deleted.
		return true;
	}
}
