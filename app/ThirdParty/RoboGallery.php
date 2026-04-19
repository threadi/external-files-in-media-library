<?php
/**
 * File to handle support for the plugin "Robo Gallery".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use WP_Post;
use WP_Query;

/**
 * Object to handle support for this plugin.
 */
class RoboGallery extends ThirdParty_Base implements ThirdParty {

	/**
	 * The term ID used in a sync.
	 *
	 * @var int
	 */
	private int $term_id = 0;

	/**
	 * Instance of actual object.
	 *
	 * @var ?RoboGallery
	 */
	private static ?RoboGallery $instance = null;

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
	 * @return RoboGallery
	 */
	public static function get_instance(): RoboGallery {
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
		if ( ! Helper::is_plugin_active( 'robo-gallery/robogallery.php' ) ) {
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
		if ( 0 === $galleries->found_posts ) {
			return $form;
		}

		// get the actual setting.
		$term_gallery = absint( get_term_meta( $term_id, 'robogallery', true ) );

		// add the HTML-code.
		$form .= '<div><label for="robogalleries">' . __( 'Choose a gallery as target:', 'external-files-in-media-library' ) . '</label>' . $this->get_gallery_selection( $term_gallery ) . '</div>';

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
		$form  = '<select id="robogalleries" name="robogallery" class="eml-use-for-import">';
		$form .= '<option value="0">' . __( 'None', 'external-files-in-media-library' ) . '</option>';
		foreach ( $galleries->get_posts() as $gallery ) {
			if ( ! $gallery instanceof WP_Post ) {
				continue;
			}
			$form .= '<option value="' . absint( $gallery->ID ) . '"' . ( absint( $gallery->ID ) === $mark ? ' selected' : '' ) . '>' . esc_html( $gallery->post_title ) . '</option>';
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
		if ( ! isset( $fields['robogalleries'] ) || 0 === absint( $fields['term_id'] ) ) {
			return;
		}

		// get the term ID.
		$term_id = absint( $fields['term_id'] );

		// if "robogalleries" is 0, just remove the setting.
		if ( 0 === absint( $fields['robogalleries'] ) ) {
			delete_term_meta( $term_id, 'robogallery' );
			return;
		}

		// save the setting.
		update_term_meta( $term_id, 'robogallery', absint( $fields['robogalleries'] ) );
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
		add_action( 'efml_after_file_save', array( $this, 'move_file_to_folder' ) );
	}

	/**
	 * Move the external file to a configured gallery after sync.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 */
	public function move_file_to_folder( File $external_file_obj ): void {
		// bail if term ID is missing.
		if ( 0 === $this->get_term_id() ) {
			return;
		}

		// get the gallery setting for this term ID.
		$gallery_id = absint( get_term_meta( $this->get_term_id(), 'robogallery', true ) );

		// bail if no gallery are set.
		if ( 0 === $gallery_id ) {
			return;
		}

		// get the gallery setting for images.
		$images = get_post_meta( $gallery_id, 'rsg_galleryImages', true );

		// if it is not an array, create it.
		if ( ! is_array( $images ) ) {
			$images = array();
		}

		// add the image to the list.
		$images[] = $external_file_obj->get_id();

		// save the list.
		update_post_meta( $gallery_id, 'rsg_galleryImages', $images );
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

		// bail if fields does not contain "robogallery".
		if ( ! isset( $_POST['robogallery'] ) ) {
			return;
		}

		// bail if given "robogallery" value is not > 0.
		if ( 0 === absint( $_POST['robogallery'] ) ) {
			return;
		}

		// get the gallery setting for images.
		$images = get_post_meta( absint( $_POST['robogallery'] ), 'rsg_galleryImages', true );

		// if it is not an array, create it.
		if ( ! is_array( $images ) ) {
			$images = array();
		}

		// add the image to the list.
		$images[] = $external_file_obj->get_id();

		// save the list.
		update_post_meta( absint( $_POST['robogallery'] ), 'rsg_galleryImages', $images );
	}

	/**
	 * Add option to import in Robo Gallery.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 *
	 * @return array<string,mixed>
	 */
	public function add_option_for_folder_import( array $dialog ): array {
		$dialog['texts'][] = '<details><summary>' . __( 'Import in a gallery from Robo Gallery', 'external-files-in-media-library' ) . '</summary><div><label for="robogalleries">' . __( 'Choose a gallery:', 'external-files-in-media-library' ) . '</label>' . $this->get_gallery_selection( 0 ) . '</div></details>';
		return $dialog;
	}

	/**
	 * Return the query result for all robo galleries.
	 *
	 * @return WP_Query
	 */
	private function get_galleries(): WP_Query {
		$query = array(
			'post_type'      => 'robo_gallery_table',
			'posts_per_page' => -1,
			'post_status'    => 'any',
		);
		return new WP_Query( $query );
	}
}
