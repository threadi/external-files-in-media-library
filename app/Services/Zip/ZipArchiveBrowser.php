<?php
/**
 * File to handle an object which returns an array for the contents of a ZIP file.
 *
 * @source https://www.the-art-of-web.com/php/zip-archive-browser/
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\Zip;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use WP_Error;
use ZipArchive;

/**
 * Object to return an array for the contents of a ZIP file.
 */
class ZipArchiveBrowser {
	/**
	 * List of files.
	 *
	 * @var array
	 */
	private static array $files = array();

	/**
	 * Add a directory.
	 *
	 * @param array $files The base directory.
	 * @param array $parts The parts to add.
	 *
	 * @return void
	 */
	private static function add_directory( array &$files, array $parts ): void {
		// get the directory name from first entry of the parts array.
		$dir = array_shift( $parts );

		// add to list if it does not already exist.
		if ( ! isset( $files[ $dir ] ) ) {
			$files[ $dir ] = array();
		}

		// if we have sub-parts, add them as directories.
		if ( isset( $parts[0] ) && $parts[0] ) {
			self::add_directory( $files[ $dir ], $parts );
		}
	}

	/**
	 * Add a directory.
	 *
	 * @param array $files The base directory.
	 * @param array $parts The parts to add.
	 * @param array $file_stat List of file stats.
	 *
	 * @return void
	 */
	private static function add_file( array &$files, array $parts, array $file_stat ): void {
		if ( isset( $parts[1] ) && $parts[1] ) {
			self::add_file( $files[ array_shift( $parts ) ], $parts, $file_stat );
		} else {
			$files[ end( $parts ) ] = $file_stat;
		}
	}

	/**
	 * Return the contents of a file as array.
	 *
	 * @param Directory_Listing_Base $obj The directory listing object used for this listing.
	 * @param string                 $zip_file The ZIP file to use.
	 *
	 * @return array
	 */
	public static function get_contents( Directory_Listing_Base $obj, string $zip_file ): array {
		if ( ! file_exists( $zip_file ) ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_zip', __( 'Given file does not exist!', 'external-files-in-media-library' ) );

			// add it to the list.
			$obj->add_error( $error );

			// return an empty list as we could not analyse the file.
			return array();
		}

		// open zip file using ZipArchive as readonly.
		$zip    = new ZipArchive();
		$opened = $zip->open( $zip_file, ZipArchive::RDONLY );

		// bail if ZIP could not be opened.
		if ( ! $opened ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_zip', __( 'Given file is not a valid ZIP-file!', 'external-files-in-media-library' ) );

			// add it to the list.
			$obj->add_error( $error );

			// return empty array.
			return array();
		}

		// get count of files.
		$file_count = $zip->count();

		// loop through the files and create the list.
		for ( $i = 0; $i < $file_count; $i++ ) {
			// get parts of the path.
			$parts = explode( DIRECTORY_SEPARATOR, $zip->getNameIndex( $i ) );

			// get entry data.
			$file_stat = $zip->statIndex( $i );

			// if we have multiple parts, it is a file.
			if ( end( $parts ) ) {
				self::add_file( self::$files, $parts, $file_stat );
			} else {
				self::add_directory( self::$files, $parts );
			}
		}

		// close the zip handle.
		$zip->close();

		// convert this list to our target list for directory listing and return it.
		return self::get_directory_recursively( '/', self::$files );
	}

	/**
	 * Get the directory recursively.
	 *
	 * @param string $parent_dir     The parent directory path.
	 * @param array  $directory_list The list we want to add to the resulting list.
	 *
	 * @return array
	 */
	private static function get_directory_recursively( string $parent_dir, array $directory_list ): array {
		$file_list = array();
		// loop through the list, add each file to the list and loop through each subdirectory.
		foreach ( $directory_list as $item_name => $item_settings ) {
			// get path for item.
			$item_path = $parent_dir . $item_name;

			// collect the entry.
			$entry = array(
				'title' => $item_name,
			);

			// if item is a directory, check its files.
			if ( ! isset( $item_settings['name'] ) ) {
				$subs           = self::get_directory_recursively( trailingslashit( $item_path ), $item_settings );
				$entry['dir']   = $item_path;
				$entry['sub']   = $subs;
				$entry['count'] = count( $subs );
			} else {
				// get content type of this file.
				$mime_type = wp_check_filetype( $item_name );

				// bail if file is not allowed.
				if ( empty( $mime_type['type'] ) ) {
					continue;
				}

				// add settings for entry.
				$entry['file']          = $item_path;
				$entry['filesize']      = absint( $item_settings['size'] );
				$entry['mime-type']     = $mime_type['type'];
				$entry['icon']          = '<span class="dashicons dashicons-media-default"></span>';
				$entry['last-modified'] = Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', $item_settings['time'] ) );
			}

			// add the entry to the list.
			$file_list[] = $entry;
		}

		// return resulting list.
		return $file_list;
	}
}
