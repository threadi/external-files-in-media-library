<?php
/**
 * File to handle support for the plugin "NextGen Gallery".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\File_Types\Image;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use Imagely\NGG\DataStorage\Manager;

/**
 * Object to handle support for this plugin.
 */
class NextGen extends ThirdParty_Base implements ThirdParty {

	/**
	 * The term ID used in a sync.
	 *
	 * @var int
	 */
	private int $term_id = 0;

	/**
	 * Instance of actual object.
	 *
	 * @var ?NextGen
	 */
	private static ?NextGen $instance = null;

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
	 * @return NextGen
	 */
	public static function get_instance(): NextGen {
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
		// bail if plugin is not enabled.
		if ( ! Helper::is_plugin_active( 'nextgen-gallery/nggallery.php' ) ) {
			return;
		}

		// add hooks.
		add_filter( 'efml_sync_configure_form', array( $this, 'add_gallery_selection' ), 10, 2 );
		add_action( 'efml_sync_save_config', array( $this, 'save_sync_settings' ) );
		add_action( 'efml_before_sync', array( $this, 'add_action_before_sync' ), 10, 3 );
		add_filter( 'efml_add_dialog', array( $this, 'add_option_for_folder_import' ) );
		add_action( 'efml_after_file_save', array( $this, 'save_url_in_gallery' ) );
	}

	/**
	 * Return the term ID used in a sync.
	 *
	 * @return int
	 */
	private function get_term_id(): int {
		return $this->term_id;
	}

	/**
	 * Add gallery selection to external directory synchronization form.
	 *
	 * @param string $form The HTML-code of the form.
	 * @param int    $term_id The term ID.
	 *
	 * @return string
	 */
	public function add_gallery_selection( string $form, int $term_id ): string {
		// get all galleries.
		$galleries = $this->get_galleries();

		// bail if list is empty.
		if ( empty( $galleries ) ) {
			return $form;
		}

		// get the actual setting.
		$term_gallery = absint( get_term_meta( $term_id, 'nextgengallery', true ) );

		// add the HTML-code.
		$form .= '<div><label for="nextgengalleries">' . __( 'Choose a gallery as target:', 'external-files-in-media-library' ) . '</label>' . $this->get_gallery_selection( $term_gallery ) . '</div>';

		// return the resulting html-code for the form.
		return $form;
	}

	/**
	 * Return the HTML-code for a folder selection.
	 *
	 * @param int $mark The folder to mark in the selection.
	 *
	 * @return string
	 */
	private function get_gallery_selection( int $mark ): string {
		// get the galleries.
		$galleries = $this->get_galleries();

		// create the HTML-code.
		$form  = '<select id="nextgengalleries" name="nextgengallery" class="eml-use-for-import">';
		$form .= '<option value="0">' . __( 'None', 'external-files-in-media-library' ) . '</option>';
		foreach ( $galleries as $gallery ) {
			$form .= '<option value="' . absint( $gallery['gid'] ) . '"' . ( absint( $gallery['gid'] ) === $mark ? ' selected' : '' ) . '>' . esc_html( $gallery['title'] ) . '</option>';
		}
		$form .= '</select>';

		// return the resulting HTML-code.
		return $form;
	}

	/**
	 * Save the custom sync configuration for an external directory.
	 *
	 * @param array<string,string> $fields List of fields.
	 *
	 * @return void
	 */
	public function save_sync_settings( array $fields ): void {
		// bail if term ID or our own setting is not given.
		if ( ! isset( $fields['nextgengalleries'] ) || 0 === absint( $fields['term_id'] ) ) {
			return;
		}

		// get the term ID.
		$term_id = absint( $fields['term_id'] );

		// if "robogalleries" is 0, just remove the setting.
		if ( 0 === absint( $fields['nextgengalleries'] ) ) {
			delete_term_meta( $term_id, 'nextgengalleries' );
			return;
		}

		// save the setting.
		update_term_meta( $term_id, 'nextgengallery', absint( $fields['nextgengalleries'] ) );
	}

	/**
	 * Add action to move files in a gallery before sync is running.
	 *
	 * @param string               $url The used URL.
	 * @param array<string,string> $term_data The term data.
	 * @param int                  $term_id The term ID.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_action_before_sync( string $url, array $term_data, int $term_id ): void {
		// save term ID in object.
		$this->term_id = $term_id;

		// add hooks.
		add_action( 'efml_after_file_save', array( $this, 'move_file_to_gallery' ) );
	}

	/**
	 * Move the external file to a configured gallery after sync.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 */
	public function move_file_to_gallery( File $external_file_obj ): void {
		// bail if term ID is missing.
		if ( 0 === $this->get_term_id() ) {
			return;
		}

		// bail if a necessary class does not exist.
		if ( ! class_exists( '\Imagely\NGG\DataStorage\Manager' ) ) {
			return;
		}

		// bail if the file is not an image.
		if ( ! $external_file_obj->get_file_type_obj() instanceof Image ) {
			return;
		}

		// get the gallery setting for this term ID.
		$gallery_id = absint( get_term_meta( $this->get_term_id(), 'nextgengallery', true ) );

		// bail if no gallery are set.
		if ( 0 === $gallery_id ) {
			return;
		}

		try {
			// add the image.
			$storage = Manager::get_instance();
			if ( $storage instanceof Manager ) {
				// get the "WP_Filesystem" object.
				$wp_filesystem = Helper::get_wp_filesystem();

				// get the protocol handler.
				$protocol_handler = $external_file_obj->get_protocol_handler_obj();

				// bail if protocol handler could not be loaded.
				if ( ! $protocol_handler instanceof Protocol_Base ) {
					return;
				}

				// get a tmp file for local import in the gallery.
				$tmp_file = $protocol_handler->get_temp_file( $external_file_obj->get_url( true ), $wp_filesystem );

				// bail if tmp file could not be saved.
				if ( ! is_string( $tmp_file ) ) {
					return;
				}

				// add the image.
				$storage->import_image_file( $gallery_id, $tmp_file, basename( $external_file_obj->get_url( true ) ), false, true );

				// delete the tmp file.
				$wp_filesystem->delete( $tmp_file );
			}
		} catch ( \Exception $e ) {
			Log::get_instance()->create( __( 'Error during adding an image to NextGen Gallery:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', $external_file_obj->get_url( true ), 'error' );
		}
	}

	/**
	 * Save the external file to a configured gallery after import.
	 *
	 * @param File $external_file_obj The used URL as external file object.
	 *
	 * @return void
	 */
	public function save_url_in_gallery( File $external_file_obj ): void {
		// check nonce.
		if ( isset( $_POST['efml-nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['efml-nonce'] ) ), 'efml-nonce' ) ) {
			exit;
		}

		// bail if a necessary class does not exist.
		if ( ! class_exists( '\Imagely\NGG\DataStorage\Manager' ) ) {
			return;
		}

		// bail if the file is not an image.
		if ( ! $external_file_obj->get_file_type_obj() instanceof Image ) {
			return;
		}

		// bail if fields does not contain "robogallery".
		if ( ! isset( $_POST['nextgengallery'] ) ) {
			return;
		}

		// bail if given "robogallery" value is not > 0.
		if ( 0 === absint( $_POST['nextgengallery'] ) ) {
			return;
		}

		// add the image.
		$storage = Manager::get_instance();
		if ( $storage instanceof Manager ) {
			// get the "WP_Filesystem" object.
			$wp_filesystem = Helper::get_wp_filesystem();

			// get the protocol handler.
			$protocol_handler = $external_file_obj->get_protocol_handler_obj();

			// bail if protocol handler could not be loaded.
			if ( ! $protocol_handler instanceof Protocol_Base ) {
				return;
			}

			// get a tmp file for local import in the gallery.
			$tmp_file = $protocol_handler->get_temp_file( $external_file_obj->get_url( true ), $wp_filesystem );

			// bail if tmp file could not be saved.
			if ( ! is_string( $tmp_file ) ) {
				return;
			}

			// add the image.
			$storage->import_image_file( absint( $_POST['nextgengallery'] ), $tmp_file, basename( $external_file_obj->get_url() ) );

			// delete the tmp file.
			$wp_filesystem->delete( $tmp_file );
		}
	}

	/**
	 * Add option to import in Robo Gallery.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 *
	 * @return array<string,mixed>
	 */
	public function add_option_for_folder_import( array $dialog ): array {
		$dialog['texts'][] = '<details><summary>' . __( 'Import in a gallery from NextGen Gallery', 'external-files-in-media-library' ) . '</summary><div><label for="nextgengalleries">' . __( 'Choose a gallery:', 'external-files-in-media-library' ) . '</label>' . $this->get_gallery_selection( 0 ) . '</div></details>';
		return $dialog;
	}

	/**
	 * Return the query result for all robo galleries.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_galleries(): array {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . 'ngg_gallery', ARRAY_A );
	}
}
