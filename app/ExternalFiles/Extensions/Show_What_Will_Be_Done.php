<?php
/**
 * This file contains an extension to show what will be done to import a file
 * in the import dialog.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Extensions;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Exception;
use ExternalFilesInMediaLibrary\ExternalFiles\Extension_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\File_Types;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\ImportDialog;
use ExternalFilesInMediaLibrary\ExternalFiles\Results;

/**
 * Controller for queue tasks.
 */
class Show_What_Will_Be_Done extends Extension_Base {
	/**
	 * The internal extension name.
	 *
	 * @var string
	 */
	protected string $name = 'show_what_will_be_done';

	/**
	 * Instance of actual object.
	 *
	 * @var Show_What_Will_Be_Done|null
	 */
	private static ?Show_What_Will_Be_Done $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {
	}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {
	}

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Show_What_Will_Be_Done
	 */
	public static function get_instance(): Show_What_Will_Be_Done {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// use our own hooks.
		add_filter( 'eml_add_dialog', array( $this, 'add_info_in_dialog' ), 5, 2 );
	}

	/**
	 * Return the object title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Show what will be done', 'external-files-in-media-library' );
	}

	/**
	 * Simulate the import and show the results in the import dialog.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 * @param array<string,mixed> $settings The settings.
	 *
	 * @return array<string,mixed>
	 */
	public function add_info_in_dialog( array $dialog, array $settings ): array {
		// only add if it is enabled in settings.
		if ( ! in_array( $this->get_name(), ImportDialog::get_instance()->get_enabled_extensions(), true ) ) {
			return $dialog;
		}

		// get the URLs.
		$urls = $settings['urls'];

		// bail if URLs is empty.
		if ( empty( $urls ) ) {
			return $dialog;
		}

		// get the fields.
		$fields = array();
		if( is_array( $settings['fields'] ) ) {
			$fields = $settings['fields'];
		}

		// add filter to prevent the import.
		add_filter( 'eml_prevent_file_import', '__return_true' );
		add_filter( 'eml_save_temp_file', '__return_false', PHP_INT_MAX );

		// get the import object.
		$import_obj = Import::get_instance();

		// add the credentials.
		$import_obj->set_fields( $fields );

		// simulate an import.
		$test_import_result = $import_obj->add_url( $settings['urls'] );

		// bail if test import ends in error.
		if ( ! $test_import_result ) {
			$dialog['texts'][] = __( 'Import will not be possible!', 'external-files-in-media-library' );
			return $dialog;
		}

		// collect the text.
		$text = '';

		// get the result.
		$results = Results::get_instance()->get_results();

		// add them to the list.
		foreach ( $results as $index => $result ) {
			// bail if object does not have "get_result_text".
			if ( ! method_exists( $result, 'get_result_text' ) ) {
				continue;
			}

			// bail if index is >= 10 to minimize the list in dialog.
			if ( $index >= 10 ) {
				continue;
			}

			// get the text.
			$file_data_string = $result->get_result_text();

			// get the array from this JSON.
			try {
				$file_data = json_decode( $file_data_string, true );

				// bail if URL is missing.
				if ( empty( $file_data['url'] ) ) {
					continue;
				}

				// add hints to the list.
				if ( $file_data['local'] ) {
					$text .= '<label><span class="dashicons dashicons-admin-generic dashicons-info-outline"></span> <em>' . esc_html( $file_data['url'] ) . '</em> ' . __( 'will be saved local', 'external-files-in-media-library' ) . '</label>';
				} else {
					// get the file type object.
					$file_type_obj = File_Types::get_instance()->get_type_object_by_mime_type( $file_data['mime-type'] );

					// add proxy hint.
					$proxy_info = __( 'and not use the proxy as its file type is not enabled for it', 'external-files-in-media-library' );
					if ( $file_type_obj->is_proxy_enabled() ) {
						$proxy_info = __( 'and use the proxy as its file type is enabled for it', 'external-files-in-media-library' );
					}

					// add the info.
					$text .= '<label><span class="dashicons dashicons-admin-generic dashicons-info-outline"></span> <em>' . esc_html( $file_data['url'] ) . '</em> ' . __( 'will stay external hosted', 'external-files-in-media-library' ) . ' ' . $proxy_info . '</label>';

				}
			} catch ( Exception $e ) {
				$text .= $e->getMessage();
			}
		}

		// add hint about more files.
		if ( count( $results ) >= 10 ) {
			$text .= '<label><span class="dashicons dashicons-admin-generic dashicons-info-outline"></span> ' . __( 'There are more files for which we do not provide any information about what exactly happens to them during import for performance reasons.', 'external-files-in-media-library' ) . '</label>';
		}

		// cleanup results.
		Results::get_instance()->prepare();

		// add the field.
		$dialog['texts'][] = $text;
		return $dialog;
	}
}
