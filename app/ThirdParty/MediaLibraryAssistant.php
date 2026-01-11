<?php
/**
 * File to handle support for the plugin "Media Library Assistant".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Object to handle support for this plugin.
 */
class MediaLibraryAssistant extends ThirdParty_Base implements ThirdParty {

	/**
	 * The term ID used in a sync.
	 *
	 * @var int
	 */
	private int $term_id = 0;

	/**
	 * Instance of actual object.
	 *
	 * @var ?MediaLibraryAssistant
	 */
	private static ?MediaLibraryAssistant $instance = null;

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
	 * @return MediaLibraryAssistant
	 */
	public static function get_instance(): MediaLibraryAssistant {
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
		if ( ! Helper::is_plugin_active( 'media-library-assistant/index.php' ) ) {
			return;
		}

		// add hooks.
		add_filter( 'efml_sync_configure_form', array( $this, 'add_category_selection' ), 10, 2 );
		add_filter( 'efml_sync_configure_form', array( $this, 'add_tag_selection' ), 10, 2 );
		add_action( 'efml_sync_save_config', array( $this, 'save_sync_settings' ) );
		add_action( 'efml_before_sync', array( $this, 'add_action_before_sync' ), 10, 3 );
		add_filter( 'efml_add_dialog', array( $this, 'add_option_for_folder_import' ) );
		add_action( 'efml_after_file_save', array( $this, 'save_url_in_categories' ) );
		add_action( 'efml_after_file_save', array( $this, 'save_url_in_tags' ) );
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
				'taxonomy'   => 'attachment_category',
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
		$assigned_categories = get_term_meta( $term_id, 'mla_categories', true );
		if ( ! is_array( $assigned_categories ) ) {
			$assigned_categories = array();
		}

		// add the HTML-code.
		$form .= '<div><label for="mlo_categories">' . __( 'Choose categories of plugin Media Library Assistant', 'external-files-in-media-library' ) . '</label>' . $this->get_category_selection( array_flip( $assigned_categories ) ) . '</div>';

		// return the resulting html-code for the form.
		return $form;
	}

	/**
	 * Add category selection to external directory synchronization form.
	 *
	 * @param string $form The HTML-code of the form.
	 * @param int    $term_id The term ID.
	 *
	 * @return string
	 */
	public function add_tag_selection( string $form, int $term_id ): string {
		// get the categories.
		$terms = get_terms(
			array(
				'taxonomy'   => 'attachment_tag',
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
		$assigned_tags = get_term_meta( $term_id, 'mla_tags', true );
		if ( ! is_array( $assigned_tags ) ) {
			$assigned_tags = array();
		}

		// add the HTML-code.
		$form .= '<div><label for="mlo_tags">' . __( 'Choose tags of plugin Media Library Assistant', 'external-files-in-media-library' ) . '</label>' . $this->get_tag_selection( array_flip( $assigned_tags ) ) . '</div>';

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
				'taxonomy'   => 'attachment_category',
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
			$form .= '<label for="mla_category_' . absint( $term->term_id ) . '"><input type="checkbox" id="mla_category_' . absint( $term->term_id ) . '" class="eml-use-for-import eml-multi" name="mla_categories[]" value="' . absint( $term->term_id ) . '"' . ( isset( $mark[ $term->term_id ] ) ? ' checked' : '' ) . '> ' . esc_html( $term->name ) . '</label>';
		}

		// return the resulting HTML-code.
		return $form;
	}

	/**
	 * Return the HTML-code for a category selection.
	 *
	 * @param array<int,mixed> $mark The categories to mark in the selection.
	 *
	 * @return string
	 */
	private function get_tag_selection( array $mark ): string {
		// get the categories.
		$terms = get_terms(
			array(
				'taxonomy'   => 'attachment_tag',
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
			$form .= '<label for="mla_tag_' . absint( $term->term_id ) . '"><input type="checkbox" id="mla_tag_' . absint( $term->term_id ) . '" class="eml-use-for-import eml-multi" name="mla_tags[]" value="' . absint( $term->term_id ) . '"' . ( isset( $mark[ $term->term_id ] ) ? ' checked' : '' ) . '> ' . esc_html( $term->name ) . '</label>';
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
		$categories = isset( $_POST['fields']['mla_categories'] ) ? array_map( 'absint', wp_unslash( $_POST['fields']['mla_categories'] ) ) : array();

		// if categories are empty, just remove the setting.
		if ( empty( $categories ) ) {
			delete_term_meta( $term_id, 'mla_categories' );
		} else {
			// save the setting.
			update_term_meta( $term_id, 'mla_categories', array_flip( $categories ) );
		}

		// get our fields from request.
		$tags = isset( $_POST['fields']['mla_tags'] ) ? array_map( 'absint', wp_unslash( $_POST['fields']['mla_tags'] ) ) : array();

		// if tags are empty, just remove the setting.
		if ( empty( $tags ) ) {
			delete_term_meta( $term_id, 'mla_tags' );
		} else {
			// save the setting.
			update_term_meta( $term_id, 'mla_tags', array_flip( $tags ) );
		}
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
		add_action( 'efml_after_file_save', array( $this, 'move_file_to_categories' ) );
		add_action( 'efml_after_file_save', array( $this, 'move_file_to_tags' ) );
	}

	/**
	 * Move the external file to a configured category after sync.
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
		$categories = get_term_meta( $this->get_term_id(), 'mla_categories', true );

		// bail if no categories are set.
		if ( empty( $categories ) ) {
			return;
		}

		// assign the file to the categories.
		foreach ( $categories as $cat_id ) {
			wp_set_object_terms( $external_file_obj->get_id(), $cat_id, 'attachment_category', true );
		}
	}

	/**
	 * Move the external file to a configured category after sync.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 */
	public function move_file_to_tags( File $external_file_obj ): void {
		// bail if term ID is missing.
		if ( 0 === $this->get_term_id() ) {
			return;
		}

		// get the folder setting from this term ID.
		$categories = get_term_meta( $this->get_term_id(), 'mla_tags', true );

		// bail if no categories are set.
		if ( empty( $categories ) ) {
			return;
		}

		// assign the file to the categories.
		foreach ( $categories as $cat_id ) {
			wp_set_object_terms( $external_file_obj->get_id(), $cat_id, 'attachment_tag', true );
		}
	}

	/**
	 * Save the external file to a configured categories after import.
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
		$categories = isset( $_POST['mla_categories'] ) ? array_map( 'absint', wp_unslash( $_POST['mla_categories'] ) ) : array();

		// assign the file to the categories.
		foreach ( $categories as $cat_id ) {
			wp_set_object_terms( $external_file_obj->get_id(), $cat_id, 'attachment_category', true );
		}
	}

	/**
	 * Save the external file to a configured categories after import.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 */
	public function save_url_in_tags( File $external_file_obj ): void {
		// check for nonce.
		if ( isset( $_GET['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'eml-nonce' ) ) {
			return;
		}

		// get our fields from request.
		$categories = isset( $_POST['mla_tags'] ) ? array_map( 'absint', wp_unslash( $_POST['mla_tags'] ) ) : array();

		// assign the file to the categories.
		foreach ( $categories as $cat_id ) {
			wp_set_object_terms( $external_file_obj->get_id(), $cat_id, 'attachment_tag', true );
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
		$dialog['texts'][] = '<details><summary>' . __( 'Assign files to tags', 'external-files-in-media-library' ) . '</summary><div>' . $this->get_tag_selection( array() ) . '</div></details>';
		return $dialog;
	}
}
