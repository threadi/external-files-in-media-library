<?php
/**
 * File to handle export tasks for Google Cloud Storage.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\GoogleCloudStorage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Export_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Results;
use ExternalFilesInMediaLibrary\ExternalFiles\Results\Url_Result;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\GoogleCloudStorage;
use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;

/**
 * Object for export files to GoogleDrive.
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
		// get the fields.
		$fields = $credentials['fields'];

		// get main object.
		$google_cloud_storage_obj = GoogleCloudStorage::get_instance();

		// get the bucket.
		$bucket = $this->get_bucket_object( $fields );

		// bail if bucket could not be loaded.
		if ( ! $bucket instanceof Bucket ) {
			return false;
		}

		// bail if bucket does not exist.
		if ( ! $bucket->exists() ) {
			return false;
		}

		// bail if bucket is not public.
		if ( ! $google_cloud_storage_obj->is_bucket_public( $bucket ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Bucket seems not to be public. File will not be exported for your Google Cloud Storage.', 'external-files-in-media-library' ), $target, 'error' );

			// do nothing more.
			return false;
		}

		// get the file path.
		$file_path = get_attached_file( $attachment_id, true );

		// bail if no file could be found.
		if ( ! is_string( $file_path ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Could not load file path for given attachment id.', 'external-files-in-media-library' ), $target, 'error' );

			// do nothing more.
			return false;
		}

		// get the local WP_Filesystem.
		$wp_filesystem_local = Helper::get_wp_filesystem();

		// bail if source file does not exist.
		if ( ! $wp_filesystem_local->exists( $file_path ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Given file does not exist:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $file_path ) . '</code>', $target, 'error' );

			// do nothing more.
			return false;
		}

		// upload the file.
		$object = $bucket->upload(
			(string) $wp_filesystem_local->get_contents( $file_path ),
			array(
				'name' => basename( $file_path ),
			)
		);

		// save the given name.
		update_post_meta( $attachment_id, 'efml_google_cloud_storage', $object->name() );

		// return the public URL.
		return GoogleCloudStorage::get_instance()->get_public_url_for_file( $fields['bucket']['value'], $object->name() );
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
		// get the name.
		$name = get_post_meta( $attachment_id, 'efml_google_cloud_storage', true );

		// bail if no name is given.
		if ( empty( $name ) ) {
			return false;
		}

		// get the bucket.
		$bucket = $this->get_bucket_object( $credentials['fields'] );

		// bail if bucket could not be loaded.
		if ( ! $bucket instanceof Bucket ) {
			return false;
		}

		// get the file object.
		$object = $bucket->object( $name );

		// bail if object does not exist.
		if ( ! $object->exists() ) {
			return false;
		}

		// delete it.
		$object->delete();

		// return the check if object has been deleted.
		return ! $object->exists();
	}

	/**
	 * Return the bucket object by given field settings.
	 *
	 * @param array<string,mixed> $fields List of fields.
	 *
	 * @return Bucket|false
	 */
	private function get_bucket_object( array $fields ): Bucket|false {
		// get main object.
		$google_cloud_storage_obj = GoogleCloudStorage::get_instance();
		$google_cloud_storage_obj->set_fields( $fields );

		// get storage object.
		$storage = $google_cloud_storage_obj->get_storage_object();

		// bail if storage could not be loaded.
		if ( ! $storage instanceof StorageClient ) {
			return false;
		}

		// get our bucket as object.
		return $storage->bucket( $fields['bucket']['value'] );
	}
}
