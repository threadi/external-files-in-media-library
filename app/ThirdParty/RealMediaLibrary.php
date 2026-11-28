<?php
/**
 * File to handle support for the plugin "Real Media Library".
 *
 * @source https://docs.devowl.io/real-media-library/php/
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
class RealMediaLibrary extends ThirdParty_Base implements ThirdParty, Folder_Structure_Interface {

	/**
	 * The term ID used in a sync.
	 *
	 * @var int
	 */
	private int $term_id = 0;

	/**
	 * Instance of actual object.
	 *
	 * @var ?RealMediaLibrary
	 */
	private static ?RealMediaLibrary $instance = null;

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
	 * @return RealMediaLibrary
	 */
	public static function get_instance(): RealMediaLibrary {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Return the plugin files of the supported plugin.
	 *
	 * @return array<int,string>
	 */
	public function get_plugin_files(): array {
		return array( 'real-media-library-lite/index.php' );
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// bail if plugin is not enabled.
		if ( ! Helper::is_plugin_active( 'real-media-library-lite/index.php' ) ) {
			return;
		}

		// take over the structure of subdirectories during synchronizations.
		add_filter( 'efml_folder_structure_integrations', array( $this, 'add_folder_structure_integration' ) );

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
		// bail if wp_rml_dropdown does not exist.
		if ( ! function_exists( 'wp_rml_dropdown' ) ) {
			return $form;
		}

		// get the actual setting.
		$assigned_category = absint( get_term_meta( $term_id, 'rml_folder', true ) );

		// add the HTML-code.
		$form .= '<div><label for="rml_folder">' . __( 'Choose a folder of plugin Real Media Library:', 'external-files-in-media-library' ) . '</label>' . $this->get_folder_selection( $assigned_category ) . '</div>';

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
		// get the dropdown-list without select-element.
		$folders = wp_rml_dropdown( $mark, array() );

		// bail if folders is empty.
		if ( empty( $folders ) ) {
			return '';
		}

		// return the select field.
		return '<select class="eml-use-for-import" id="rml_folder" name="rml_folder">' . $folders . '</select>';
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
		$rml_folder = isset( $_POST['fields']['rml_folder'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['fields']['rml_folder'] ) ) : 0;

		// if real media library folder is empty, just remove the setting.
		if ( 0 === $rml_folder ) {
			delete_term_meta( $term_id, 'rml_folder' );
			return;
		}

		// save the setting.
		update_term_meta( $term_id, 'rml_folder', $rml_folder );
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
	 * Move the external file to a configured folder after sync.
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

		// get the chosen folder.
		$rml_folder = absint( get_term_meta( $this->get_term_id(), 'rml_folder', true ) );

		// bail if it is 0.
		if ( 0 === $rml_folder ) {
			return;
		}

		// assign the file to the folder.
		wp_rml_move( $rml_folder, array( $external_file_obj->get_id() ) );
	}

	/**
	 * Save the external file to a configured categories after import.
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

		// get the chosen folder.
		$rml_folder = absint( filter_input( INPUT_POST, 'rml_folder', FILTER_SANITIZE_NUMBER_INT ) );

		// bail if it is 0.
		if ( 0 === $rml_folder ) {
			return;
		}

		// assign the file to the folder.
		wp_rml_move( $rml_folder, array( $external_file_obj->get_id() ) );
	}

	/**
	 * Add option to import in categories.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 *
	 * @return array<string,mixed>
	 */
	public function add_option_for_folder_import( array $dialog ): array {
		$dialog['texts'][] = '<details><summary>' . __( 'Assign files to folder of plugin Real Media Library', 'external-files-in-media-library' ) . '</summary><div>' . $this->get_folder_selection( 0 ) . '</div></details>';
		return $dialog;
	}

	/**
	 * Add this integration to the list of folder plugins, which could take over the structure of subdirectories.
	 *
	 * @param array<int,mixed> $integrations List of integrations.
	 *
	 * @return array<int,mixed>
	 */
	public function add_folder_structure_integration( array $integrations ): array {
		$integrations[] = $this;
		return $integrations;
	}

	/**
	 * Return the internal name of this integration.
	 *
	 * @return string
	 */
	public function get_folder_structure_name(): string {
		return 'real_media_library';
	}

	/**
	 * Return the title of the folder plugin.
	 *
	 * @return string
	 */
	public function get_folder_structure_title(): string {
		return 'Real Media Library';
	}

	/**
	 * Return whether the plugin is active and its folders could be created.
	 *
	 * @return bool
	 */
	public function is_folder_structure_available(): bool {
		return function_exists( 'wp_rml_create_or_return_existing_id' ) && function_exists( 'wp_rml_get_object_by_id' ) && function_exists( 'wp_rml_move' ) && function_exists( '_wp_rml_root' ) && $this->supports_subfolders();
	}

	/**
	 * Return the folder of the given external source, under which the structure is created.
	 *
	 * @param int $term_id The ID of the external source.
	 *
	 * @return int
	 */
	public function get_target_folder( int $term_id ): int {
		return absint( get_term_meta( $term_id, 'rml_folder', true ) );
	}

	/**
	 * Return whether Real Media Library could create subfolders: only the Pro version supports them.
	 *
	 * @return bool
	 */
	private function supports_subfolders(): bool {
		// get the callback for the main object of the plugin.
		$callback = array( 'MatthiasWeb\\RealMediaLibrary\\Core', 'getInstance' );

		// bail if the main class does not exist.
		if ( ! is_callable( $callback ) ) {
			return false;
		}

		// get the main object.
		$core = call_user_func( $callback );

		// return whether this is the Pro version.
		return is_object( $core ) && method_exists( $core, 'isPro' ) && true === (bool) $core->isPro();
	}

	/**
	 * Return the ID of the folder with the given name in the given parent folder; the folder is created, if it does not
	 * exist.
	 *
	 * @param int    $parent_id The ID of the parent folder (0 for the top level).
	 * @param string $name      The name of the folder.
	 *
	 * @return int The ID of the folder, 0 on errors.
	 */
	public function get_or_create_folder( int $parent_id, string $name ): int {
		// get the folder (the top level has its own ID in Real Media Library).
		$result = wp_rml_create_or_return_existing_id( $name, $parent_id > 0 ? $parent_id : _wp_rml_root(), 0, array(), true );

		// return the ID, if no errors are returned.
		return is_numeric( $result ) ? absint( $result ) : 0;
	}

	/**
	 * Return whether the folder with the given ID exists.
	 *
	 * @param int $folder_id The ID of the folder.
	 *
	 * @return bool
	 */
	public function folder_exists( int $folder_id ): bool {
		return $folder_id > 0 && function_exists( 'is_rml_folder' ) && is_rml_folder( wp_rml_get_object_by_id( $folder_id ) );
	}

	/**
	 * Assign the given file to the given folder (only to this folder).
	 *
	 * @param int $attachment_id The ID of the attachment.
	 * @param int $folder_id     The ID of the folder.
	 *
	 * @return void
	 */
	public function assign_file( int $attachment_id, int $folder_id ): void {
		wp_rml_move( $folder_id, array( $attachment_id ), true );
	}
}
