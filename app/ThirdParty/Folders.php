<?php
/**
 * File to handle support for plugin "Folders".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use WCP_Folders;
use WCP_Tree;
use WP_Post;

/**
 * Object to handle support for this plugin.
 */
class Folders extends ThirdParty_Base implements ThirdParty {

	/**
	 * The term ID used in a sync.
	 *
	 * @var int
	 */
	private int $term_id = 0;

	/**
	 * Instance of actual object.
	 *
	 * @var ?Folders
	 */
	private static ?Folders $instance = null;

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
	 * @return Folders
	 */
	public static function get_instance(): Folders {
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
		if ( ! Helper::is_plugin_active( 'folders/folders.php' ) ) {
			return;
		}

		// remove additional options from this plugin for external files.
		add_filter( 'media_row_actions', array( $this, 'remove_media_action' ), 20, 2 );
		add_action( 'add_meta_boxes', array( $this, 'remove_meta_boxes' ), 20, 2 );

		// add hooks.
		add_filter( 'efml_sync_configure_form', array( $this, 'add_category_selection' ), 10, 2 );
		add_action( 'efml_sync_save_config', array( $this, 'save_sync_settings' ) );
		add_action( 'efml_before_sync', array( $this, 'add_action_before_sync' ), 10, 3 );
		add_filter( 'efml_add_dialog', array( $this, 'add_option_for_folder_import' ) );
		add_action( 'efml_after_file_save', array( $this, 'save_url_in_folders' ) );
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
		// bail if WCP_Tree does not exist.
		if ( ! class_exists( 'WCP_Tree' ) ) {
			return $form;
		}

		// get the categories.
		$options = WCP_Tree::get_folder_option_data( WCP_Folders::get_custom_post_type( 'attachment' ) );

		// bail if list is empty.
		if ( empty( $options ) ) {
			return $form;
		}

		// get the actual setting.
		$assigned_category = absint( get_term_meta( $term_id, 'folders_folder', true ) );

		// add the HTML-code.
		$form .= '<div><label for="folders_categories">' . __( 'Choose folder of plugin Folders:', 'external-files-in-media-library' ) . '</label>' . $this->get_folder_selection( $assigned_category ) . '</div>';

		// return the resulting html-code for the form.
		return $form;
	}

	/**
	 * Return the HTML-code for a category selection.
	 *
	 * @param int $mark The category to mark in the selection.
	 *
	 * @return string
	 */
	private function get_folder_selection( int $mark ): string {
		// bail if WCP_Tree does not exist.
		if ( ! class_exists( 'WCP_Tree' ) ) {
			return '';
		}

		// get the categories.
		$options = WCP_Tree::get_folder_option_data( WCP_Folders::get_custom_post_type( 'attachment' ) );

		// bail if list is empty.
		if ( empty( $options ) ) {
			return '';
		}

		// remove existing selection.
		$options = str_replace( 'selected', '', $options );

		if ( ! empty( $mark ) ) {
			// add selection for mark.
			$options = str_replace( ' value="' . $mark . '"', ' selected value="' . $mark . '"', $options );
		}

		// create the HTML-code.
		return '<select class="eml-use-for-import" id="folder_for_media" name="folder_for_media"><option value="0">' . __( 'Choose folder', 'external-files-in-media-library' ) . '</option>' . $options . '</select>';
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
		$folders_categories = isset( $_POST['fields']['folders_folder'] ) ? array_map( 'absint', wp_unslash( $_POST['fields']['folders_folder'] ) ) : array();

		// if folderly_categories is empty, just remove the setting.
		if ( empty( $folders_categories ) ) {
			delete_term_meta( $term_id, 'folders_folder' );
			return;
		}

		// save the setting.
		update_term_meta( $term_id, 'folders_folder', $folders_categories );
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
		add_action( 'efml_after_file_save', array( $this, 'move_file_to_folder' ) );
	}

	/**
	 * Move external file to a configured folder after sync.
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

		// get the folder setting from this term ID.
		$_REQUEST['folder_for_media'] = absint( get_term_meta( $this->get_term_id(), 'folders_folder', true ) );
		$_POST['folder_for_media']    = absint( get_term_meta( $this->get_term_id(), 'folders_folder', true ) );

		// assign the file to the categories.
		( new \WCP_Folders() )->add_attachment_category( $external_file_obj->get_id() );
	}

	/**
	 * Save external file to a configured categories after import.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 */
	public function save_url_in_folders( File $external_file_obj ): void {
		// check for nonce.
		if ( isset( $_GET['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'eml-nonce' ) ) {
			return;
		}

		// assign the file to the categories.
		( new \WCP_Folders() )->add_attachment_category( $external_file_obj->get_id() );
	}

	/**
	 * Add option to import in categories.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 *
	 * @return array<string,mixed>
	 */
	public function add_option_for_folder_import( array $dialog ): array {
		$dialog['texts'][] = '<details><summary>' . __( 'Assign files to folder', 'external-files-in-media-library' ) . '</summary><div>' . $this->get_folder_selection( 0 ) . '</div></details>';
		return $dialog;
	}

	/**
	 * Remove Folders additional file actions for external files.
	 *
	 * @param array<string,string> $actions List of actions.
	 * @param WP_Post              $post The post object of the attachment.
	 *
	 * @return array<string,string>
	 */
	public function remove_media_action( array $actions, WP_Post $post ): array {
		// get external file object by object ID.
		$external_file_obj = Files::get_instance()->get_file( $post->ID );

		// bail if it is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			return $actions;
		}

		// remove the "replace_media" action from Folders plugin.
		unset( $actions['replace_media'] );

		// return the resulting list of actions.
		return $actions;
	}

	/**
	 * Remove meta box to replace external files.
	 *
	 * @param string  $post_type The requested post type.
	 * @param WP_Post $post The post object.
	 *
	 * @return void
	 */
	public function remove_meta_boxes( string $post_type, WP_Post $post ): void {
		// bail if post type is not attachment.
		if ( 'attachment' !== $post_type ) {
			return;
		}

		// get external file object for given object ID.
		// get external file object by object ID.
		$external_file_obj = Files::get_instance()->get_file( $post->ID );

		// bail if it is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			return;
		}

		// remove the box to replace or rename this media file.
		remove_meta_box( 'folders-replace-box', 'attachment', 'side' );
		remove_meta_box( 'folders-replace-file-name', 'attachment', 'side' );
	}
}
