<?php
/**
 * File to handle support for the plugin "Filebird Lite".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Languages;
use FileBird\Model\Folder;

/**
 * Object to handle support for this plugin.
 */
class Filebird extends ThirdParty_Base implements ThirdParty, Folder_Structure_Interface {

	/**
	 * The term ID used in a sync.
	 *
	 * @var int
	 */
	private int $term_id = 0;

	/**
	 * Instance of actual object.
	 *
	 * @var ?Filebird
	 */
	private static ?Filebird $instance = null;

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
	 * @return Filebird
	 */
	public static function get_instance(): Filebird {
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
		return array( 'filebird/filebird.php' );
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// bail if plugin is not enabled.
		if ( ! Helper::is_plugin_active( 'filebird/filebird.php' ) ) {
			return;
		}

		// take over the structure of subdirectories during synchronizations.
		add_filter( 'efml_folder_structure_integrations', array( $this, 'add_folder_structure_integration' ) );

		// add hooks.
		add_filter( 'efml_sync_configure_form', array( $this, 'add_folder_selection' ), 10, 2 );
		add_action( 'efml_sync_save_config', array( $this, 'save_sync_settings' ) );
		add_action( 'efml_before_sync', array( $this, 'add_action_before_sync' ), 10, 3 );
		add_filter( 'efml_add_dialog', array( $this, 'add_option_for_folder_import' ) );
		add_action( 'efml_after_file_save', array( $this, 'save_url_in_folder' ) );
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
	 * Add folder selection to external directory synchronization form.
	 *
	 * @param string $form The HTML-code of the form.
	 * @param int    $term_id The term ID.
	 *
	 * @return string
	 */
	public function add_folder_selection( string $form, int $term_id ): string {
		// bail if "FolderModel" does not exist.
		if ( ! method_exists( '\FileBird\Model\Folder', 'allFolders' ) ) {
			return $form;
		}

		// get the folders.
		$folders = Folder::allFolders();

		// bail if list is empty.
		if ( empty( $folders ) ) {
			return $form;
		}

		// get the actual setting.
		$term_folder = absint( get_term_meta( $term_id, 'fildbirdfolder', true ) );

		// add the HTML-code.
		$form .= '<div><label for="filebirdfolders">' . __( 'Choose a folder as target:', 'external-files-in-media-library' ) . '</label>' . $this->get_folder_selection( $term_folder ) . '</div>';

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
	private function get_folder_selection( int $mark ): string {
		// bail if "FolderModel" does not exist.
		if ( ! method_exists( '\FileBird\Model\Folder', 'allFolders' ) ) {
			return '';
		}

		// get the folders.
		$folders = Folder::allFolders();

		// create the HTML-code.
		$form  = '<select id="fildbirdfolder" name="fildbirdfolder" class="eml-use-for-import">';
		$form .= '<option value="0">' . __( 'None', 'external-files-in-media-library' ) . '</option>';
		foreach ( $folders as $folder ) {
			$form .= '<option value="' . absint( $folder->id ) . '"' . ( absint( $folder->id ) === $mark ? ' selected' : '' ) . '>' . esc_html( $folder->name ) . '</option>';
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
		if ( ! isset( $fields['fildbirdfolder'] ) || 0 === absint( $fields['term_id'] ) ) {
			return;
		}

		// get the term ID.
		$term_id = absint( $fields['term_id'] );

		// if fildbirdfolder is 0, just remove the setting.
		if ( 0 === absint( $fields['fildbirdfolder'] ) ) {
			delete_term_meta( $term_id, 'fildbirdfolder' );
			return;
		}

		// save the setting.
		update_term_meta( $term_id, 'fildbirdfolder', absint( $fields['fildbirdfolder'] ) );
	}

	/**
	 * Add action to move files in folder before sync is running.
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
		// bail if "FolderModel" does not exist.
		if ( ! method_exists( '\FileBird\Model\Folder', 'assignFolder' ) ) {
			return;
		}

		// bail if term ID is missing.
		if ( 0 === $this->get_term_id() ) {
			return;
		}

		// get the folder setting from this term ID.
		$folder = absint( get_term_meta( $this->get_term_id(), 'fildbirdfolder', true ) );

		// bail if no folder is set.
		if ( 0 === $folder ) {
			return;
		}

		// move the file to the given folder.
		Folder::assignFolder( $folder, array( $external_file_obj->get_id() ), Languages::get_instance()->get_current_lang() );
	}

	/**
	 * Save the external file to a configured folder after import.
	 *
	 * @param File $external_file_obj The used URL as external file object.
	 *
	 * @return void
	 */
	public function save_url_in_folder( File $external_file_obj ): void {
		// check nonce.
		if ( isset( $_POST['efml-nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['efml-nonce'] ) ), 'efml-nonce' ) ) {
			exit;
		}

		// bail if "FolderModel" does not exist.
		if ( ! method_exists( '\FileBird\Model\Folder', 'assignFolder' ) ) {
			return;
		}

		// bail if fields does not contain "filebirdfolder".
		if ( ! isset( $_POST['fildbirdfolder'] ) ) {
			return;
		}

		// bail if given "fildbirdfolder" value is not > 0.
		if ( 0 === absint( $_POST['fildbirdfolder'] ) ) {
			return;
		}

		// move the file to the given folder.
		Folder::assignFolder( absint( $_POST['fildbirdfolder'] ), array( $external_file_obj->get_id() ), Languages::get_instance()->get_current_lang() );
	}

	/**
	 * Add option to import in Filebird.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 *
	 * @return array<string,mixed>
	 */
	public function add_option_for_folder_import( array $dialog ): array {
		$dialog['texts'][] = '<details><summary>' . __( 'Import in a specific folder of Filebird', 'external-files-in-media-library' ) . '</summary><div><label for="filebirdfolders">' . __( 'Choose folder:', 'external-files-in-media-library' ) . '</label>' . $this->get_folder_selection( 0 ) . '</div></details>';
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
		return 'filebird';
	}

	/**
	 * Return the title of the folder plugin.
	 *
	 * @return string
	 */
	public function get_folder_structure_title(): string {
		return 'FileBird';
	}

	/**
	 * Return whether the plugin is active and its folders could be created.
	 *
	 * @return bool
	 */
	public function is_folder_structure_available(): bool {
		return Helper::is_plugin_active( 'filebird/filebird.php' ) && method_exists( '\\FileBird\\Model\\Folder', 'newOrGet' ) && method_exists( '\\FileBird\\Model\\Folder', 'isFolderExist' ) && method_exists( '\\FileBird\\Model\\Folder', 'assignFolder' );
	}

	/**
	 * Return the folder of the given external source, under which the structure is created.
	 *
	 * @param int $term_id The ID of the external source.
	 *
	 * @return int
	 */
	public function get_target_folder( int $term_id ): int {
		return absint( get_term_meta( $term_id, 'fildbirdfolder', true ) );
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
		// get or create the folder.
		$result = Folder::newOrGet( $name, $parent_id );

		// return its ID.
		return is_array( $result ) && ! empty( $result['id'] ) ? absint( $result['id'] ) : 0;
	}

	/**
	 * Return whether the folder with the given ID exists.
	 *
	 * @param int $folder_id The ID of the folder.
	 *
	 * @return bool
	 */
	public function folder_exists( int $folder_id ): bool {
		return $folder_id > 0 && Folder::isFolderExist( $folder_id );
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
		Folder::assignFolder( $folder_id, array( $attachment_id ), Languages::get_instance()->get_current_lang() );
	}
}
