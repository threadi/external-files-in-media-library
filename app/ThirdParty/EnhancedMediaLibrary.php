<?php
/**
 * File to handle support for plugin "Enhanced Media Library".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Object to handle support for this plugin.
 */
class EnhancedMediaLibrary extends ThirdParty_Base implements ThirdParty {

	/**
	 * The term ID used in a sync.
	 *
	 * @var int
	 */
	private int $term_id = 0;

	/**
	 * Instance of actual object.
	 *
	 * @var ?EnhancedMediaLibrary
	 */
	private static ?EnhancedMediaLibrary $instance = null;

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
	 * @return EnhancedMediaLibrary
	 */
	public static function get_instance(): EnhancedMediaLibrary {
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
		if ( ! Helper::is_plugin_active( 'enhanced-media-library/enhanced-media-library.php' ) ) {
			return;
		}

		// add hooks.
		add_filter( 'efml_sync_configure_form', array( $this, 'add_category_selection' ), 10, 2 );
		add_action( 'efml_sync_save_config', array( $this, 'save_sync_settings' ) );
		add_action( 'efml_before_sync', array( $this, 'add_action_before_sync' ), 10, 3 );
		add_filter( 'eml_add_dialog', array( $this, 'add_option_for_folder_import' ) );
		add_action( 'eml_after_file_save', array( $this, 'save_url_in_categories' ) );
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
	 * Add category selection to external directory synchronization form.
	 *
	 * @param string $form The HTML-code of the form.
	 * @param int    $term_id The term ID.
	 *
	 * @return string
	 */
	public function add_category_selection( string $form, int $term_id ): string {
		// get the categories.
		$terms = get_terms(
			array(
				'taxonomy'   => 'media_category',
				'hide_empty' => false,
			)
		);

		// bail on any error.
		if ( ! is_array( $terms ) ) {
			return $form;
		}

		// bail if list is empty.
		if ( empty( $terms ) ) {
			return $form;
		}

		// get the actual setting.
		$assigned_categories = get_term_meta( $term_id, 'eml_categories', true );
		if ( ! is_array( $assigned_categories ) ) {
			$assigned_categories = array();
		}

		// add the HTML-code.
		$form .= '<div><label for="eml_categories">' . __( 'Choose categories:', 'external-files-in-media-library' ) . '</label>' . $this->get_category_selection( $assigned_categories ) . '</div>';

		// return the resulting html-code for the form.
		return $form;
	}

	/**
	 * Return the HTML-code for a category selection.
	 *
	 * @param array<int,mixed> $mark The categories to mark in the selection.
	 *
	 * @return string
	 */
	private function get_category_selection( array $mark ): string {
		// get the categories.
		$terms = get_terms(
			array(
				'taxonomy'   => 'media_category',
				'hide_empty' => false,
			)
		);

		// bail on any error.
		if ( ! is_array( $terms ) ) {
			return '';
		}

		// bail if list is empty.
		if ( empty( $terms ) ) {
			return '';
		}

		// create the HTML-code.
		$form = '';
		foreach ( $terms as $term ) {
			$form .= '<label for="eml_category_' . absint( $term->term_id ) . '"><input type="checkbox" id="eml_category_' . absint( $term->term_id ) . '" class="eml-use-for-import eml-multi" name="eml_categories" value="' . absint( $term->term_id ) . '"' . ( isset( $mark[ $term->term_id ] ) ? ' checked' : '' ) . '> ' . esc_html( $term->name ) . '</label>';
		}

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
		// check for nonce.
		if ( isset( $_GET['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'eml-nonce' ) ) {
			return;
		}

		// bail if term ID not given.
		if ( 0 === absint( $fields['term_id'] ) ) {
			return;
		}

		// get the term ID.
		$term_id = absint( $fields['term_id'] );

		// get our fields from request.
		$eml_categories = isset( $_POST['fields']['eml_categories'] ) ? array_map( 'absint', wp_unslash( $_POST['fields']['eml_categories'] ) ) : array();

		// if eml_categories is empty, just remove the setting.
		if ( empty( $eml_categories ) ) {
			delete_term_meta( $term_id, 'eml_categories' );
			return;
		}

		// save the setting.
		update_term_meta( $term_id, 'eml_categories', $eml_categories );
	}

	/**
	 * Add action to assign files to eml categories before sync is running.
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
		add_action( 'eml_after_file_save', array( $this, 'move_file_to_categories' ) );
	}

	/**
	 * Move external file to a configured category after sync.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 */
	public function move_file_to_categories( File $external_file_obj ): void {
		// bail if term ID is missing.
		if ( 0 === $this->get_term_id() ) {
			return;
		}

		// get the folder setting from this term ID.
		$categories = get_term_meta( $this->get_term_id(), 'eml_categories', true );

		// bail if no categories are set.
		if ( empty( $categories ) ) {
			return;
		}

		// assign the file to the categories.
		foreach ( $categories as $cat_id => $enabled ) {
			wp_set_object_terms( $external_file_obj->get_id(), $cat_id, 'media_category' );
		}
	}

	/**
	 * Save external file to a configured categories after import.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 */
	public function save_url_in_categories( File $external_file_obj ): void {
		// check for nonce.
		if ( isset( $_GET['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'eml-nonce' ) ) {
			return;
		}

		// get our fields from request.
		$eml_categories = isset( $_POST['eml_categories'] ) ? array_map( 'absint', wp_unslash( $_POST['eml_categories'] ) ) : array();

		// assign the file to the categories.
		foreach ( $eml_categories as $cat_id => $enabled ) {
			wp_set_object_terms( $external_file_obj->get_id(), $cat_id, 'media_category' );
		}
	}

	/**
	 * Add option to import in categories.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 *
	 * @return array<string,mixed>
	 */
	public function add_option_for_folder_import( array $dialog ): array {
		$dialog['texts'][] = '<details><summary>' . __( 'Assign files to categories', 'external-files-in-media-library' ) . '</summary><div>' . $this->get_category_selection( array() ) . '</div></details>';
		return $dialog;
	}
}
