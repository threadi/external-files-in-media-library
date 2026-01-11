<?php
/**
 * File to handle support for the plugin "Download List with Icons".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use WP_Term;

/**
 * Object to handle support for this plugin.
 */
class Downloadlist extends ThirdParty_Base implements ThirdParty {

	/**
	 * The term ID used in a sync.
	 *
	 * @var int
	 */
	private int $term_id = 0;

	/**
	 * Instance of actual object.
	 *
	 * @var ?Downloadlist
	 */
	private static ?Downloadlist $instance = null;

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
	 * @return Downloadlist
	 */
	public static function get_instance(): Downloadlist {
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
		if ( ! Helper::is_plugin_active( 'download-list-block-with-icons/download-list-block-with-icons.php' ) ) {
			return;
		}

		// use plugin hooks.
		add_filter( 'downloadlist_rel_attribute', array( $this, 'set_rel_attribute' ), 10, 2 );

		// use our own hooks.
		add_filter( 'efml_sync_configure_form', array( $this, 'add_list_selection' ), 10, 2 );
		add_action( 'efml_sync_save_config', array( $this, 'save_sync_settings' ) );
		add_action( 'efml_before_sync', array( $this, 'add_action_before_sync' ), 10, 3 );
		add_filter( 'efml_add_dialog', array( $this, 'add_option_for_list_import' ) );
		add_action( 'efml_after_file_save', array( $this, 'save_file_in_list' ) );
	}

	/**
	 * Set the rel-attribute for external files.
	 *
	 * @param string               $rel_attribute The rel-value.
	 * @param array<string,string> $file The file-attributes.
	 *
	 * @return string
	 */
	public function set_rel_attribute( string $rel_attribute, array $file ): string {
		// bail if array is empty.
		if ( empty( $file ) ) {
			return $rel_attribute;
		}

		// bail if ID is not given.
		if ( empty( $file['id'] ) ) {
			return $rel_attribute;
		}

		// check if this is an external file.
		$external_file_obj = Files::get_instance()->get_file( (int) $file['id'] );

		// return the original URL if this URL-file is not valid or not available or a not allowed mime type.
		if ( false === $external_file_obj->is_valid() || false === $external_file_obj->is_available() || false === $external_file_obj->is_mime_type_allowed() ) {
			return $rel_attribute;
		}

		// return external value for rel-attribute.
		return 'external';
	}

	/**
	 * Add list selection to external directory synchronization form.
	 *
	 * @param string $form The HTML-code of the form.
	 * @param int    $term_id The term ID.
	 *
	 * @return string
	 */
	public function add_list_selection( string $form, int $term_id ): string {
		// get the available lists.
		$terms = get_terms(
			array(
				'taxonomy'   => 'dl_icon_lists',
				'hide_empty' => false,
			)
		);

		// bail if no lists exist.
		if ( empty( $terms ) ) {
			return '';
		}

		// get the actual setting.
		$term_list = absint( get_term_meta( $term_id, 'downloadlistlist', true ) );

		// add the HTML-code.
		$form .= '<div><label for="downloadlistlist">' . __( 'Choose list as target:', 'external-files-in-media-library' ) . '</label>' . $this->get_list_selection( $term_list ) . '</div>';

		// return the resulting html-code for the form.
		return $form;
	}

	/**
	 * Return the HTML-code for a list selection.
	 *
	 * @param int $mark The list to mark in the selection.
	 *
	 * @return string
	 */
	private function get_list_selection( int $mark ): string {
		// get the available lists.
		$terms = get_terms(
			array(
				'taxonomy'   => 'dl_icon_lists',
				'hide_empty' => false,
			)
		);

		// bail if terms could not be loaded.
		if ( ! is_array( $terms ) ) {
			return '';
		}

		// create the HTML-code.
		$form  = '<select id="downloadlistlist" name="downloadlistlist" class="eml-use-for-import">';
		$form .= '<option value="0">' . __( 'None', 'external-files-in-media-library' ) . '</option>';
		foreach ( $terms as $entry ) {
			$form .= '<option value="' . absint( $entry->term_id ) . '"' . ( absint( $entry->term_id ) === $mark ? ' selected' : '' ) . '>' . esc_html( $entry->name ) . '</option>';
		}
		$form .= '</select>';

		// return the resulting HTML-code.
		return $form;
	}

	/**
	 * Add option to import in Download List with Icons.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 *
	 * @return array<string,mixed>
	 */
	public function add_option_for_list_import( array $dialog ): array {
		$dialog['texts'][] = '<details><summary>' . __( 'Import in specific download list of Download List with Icons', 'external-files-in-media-library' ) . '</summary><div><label for="downloadlistlist">' . __( 'Choose list:', 'external-files-in-media-library' ) . '</label>' . $this->get_list_selection( 0 ) . '</div></details>';
		return $dialog;
	}

	/**
	 * Save the external file to a configured download list after import.
	 *
	 * @param File $external_file_obj The used URL as external file object.
	 *
	 * @return void
	 */
	public function save_file_in_list( File $external_file_obj ): void {
		// check nonce.
		if ( isset( $_POST['efml-nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['efml-nonce'] ) ), 'efml-nonce' ) ) {
			exit;
		}

		// bail if fields does not contain "downloadlistlist".
		if ( ! isset( $_POST['downloadlistlist'] ) ) {
			return;
		}

		// bail if given "downloadlistlist" value is not > 0.
		if ( 0 === absint( $_POST['downloadlistlist'] ) ) {
			return;
		}

		// get the term object of the given term_id.
		$term = get_term( absint( $_POST['downloadlistlist'] ), 'dl_icon_lists' );

		// bail if term object could not be found.
		if ( ! $term instanceof WP_Term ) {
			return;
		}

		// assign the file to the chosen list.
		wp_set_object_terms( $external_file_obj->get_id(), absint( $_POST['downloadlistlist'] ), 'dl_icon_lists', true );
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
		if ( ! isset( $fields['downloadlistlist'] ) || 0 === absint( $fields['term_id'] ) ) {
			return;
		}

		// get the term ID.
		$term_id = absint( $fields['term_id'] );

		// if downloadlistlist is 0, just remove the setting.
		if ( 0 === absint( $fields['downloadlistlist'] ) ) {
			delete_term_meta( $term_id, 'downloadlistlist' );
			return;
		}

		// save the setting.
		update_term_meta( $term_id, 'downloadlistlist', absint( $fields['downloadlistlist'] ) );
	}

	/**
	 * Add action to move files in list before sync is running.
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
		add_action( 'efml_after_file_save', array( $this, 'move_file_to_list' ) );
	}

	/**
	 * Move the external file to a configured list after sync.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 */
	public function move_file_to_list( File $external_file_obj ): void {
		// bail if term ID is missing.
		if ( 0 === $this->get_term_id() ) {
			return;
		}

		// get the list setting from this term ID.
		$list = absint( get_term_meta( $this->get_term_id(), 'downloadlistlist', true ) );

		// bail if no folder is set.
		if ( 0 === $list ) {
			return;
		}

		// assign the file to the chosen list.
		wp_set_object_terms( $external_file_obj->get_id(), $list, 'dl_icon_lists', true );
	}

	/**
	 * Return the term ID used in a sync.
	 *
	 * @return int
	 */
	private function get_term_id(): int {
		return $this->term_id;
	}
}
