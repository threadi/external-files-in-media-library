<?php
/**
 * File to handle support for the plugin "Prevent Direct Access".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;

/**
 * Object to handle support for this plugin.
 *
 * The plugin protects local files by moving them into the folder "_pda" in the uploads directory, which is protected
 * by rules of the web server. This protection does not work for files on external sources:
 *
 * - protected files are not exported, as they would be public on the external source.
 * - exported and other external files could not be protected, the plugin would change their path.
 */
class PreventDirectAccess extends ThirdParty_Base implements ThirdParty {

	/**
	 * Instance of actual object.
	 *
	 * @var ?PreventDirectAccess
	 */
	private static ?PreventDirectAccess $instance = null;


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
	 * @return PreventDirectAccess
	 */
	public static function get_instance(): PreventDirectAccess {
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
		return array( 'prevent-direct-access/prevent-direct-access.php' );
	}

	/**
	 * Initialize this support.
	 *
	 * @return void
	 */
	public function init(): void {
		// bail if plugin is not active.
		if ( ! $this->is_supported_plugin_active() ) {
			return;
		}

		// use hooks.
		$this->add_hooks();
	}

	/**
	 * Add the hooks.
	 *
	 * @return void
	 */
	public function add_hooks(): void {
		// bail if they are added already.
		if ( false !== has_filter( 'efml_prevent_export', array( $this, 'prevent_export' ) ) ) {
			return;
		}

		add_filter( 'efml_prevent_export', array( $this, 'prevent_export' ), 10, 2 );
		add_filter( 'efml_export_prevent_reason', array( $this, 'get_prevent_reason' ), 10, 2 );
		add_action( 'wp_ajax_myaction', array( $this, 'prevent_protection_of_external_files' ), 1 );
		add_action( 'manage_media_custom_column', array( $this, 'hide_column_for_external_files' ), -1, 2 );
		add_action( 'efml_table_column_local_file', array( $this, 'show_marker' ) );
	}

	/**
	 * Return whether the given attachment is protected by the plugin.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_protected( int $attachment_id ): bool {
		return '1' === (string) get_post_meta( $attachment_id, '_pda_protection', true ) || str_starts_with( (string) get_post_meta( $attachment_id, '_wp_attached_file', true ), '_pda/' );
	}

	/**
	 * Prevent the export of files, which are protected by the plugin, as they would be public on the external source.
	 *
	 * @param bool $result            True to prevent the export.
	 * @param File $external_file_obj The file object.
	 *
	 * @return bool
	 */
	public function prevent_export( bool $result, File $external_file_obj ): bool {
		// bail if the export is prevented already or the file is not protected.
		if ( $result || ! $this->is_protected( $external_file_obj->get_id() ) ) {
			return $result;
		}

		// prevent the export.
		return true;
	}

	/**
	 * Return the reason, why the export of the given file is prevented, for the log.
	 *
	 * @param string $reason            The reason.
	 * @param File   $external_file_obj The file object.
	 *
	 * @return string
	 */
	public function get_prevent_reason( string $reason, File $external_file_obj ): string {
		// bail if a reason is known already or this support does not prevent the export.
		if ( '' !== $reason || ! ( $this->is_protected( $external_file_obj->get_id() ) ) ) {
			return $reason;
		}

		/* translators: %1$s will be replaced by the name of a plugin. */
		return sprintf( __( 'It is protected by %1$s, it would be public on the external source.', 'external-files-in-media-library' ), 'Prevent Direct Access' );
	}

	/**
	 * Refuse the protection of external files by the plugin, as it would change their path.
	 *
	 * @return void
	 */
	public function prevent_protection_of_external_files(): void {
		// bail if this is not a request to protect a file (the plugin checks the nonce and the capability).
		if ( ! isset( $_POST['id'], $_POST['is_prevented'], $_REQUEST['security_check'] ) || ! wp_validate_boolean( sanitize_text_field( wp_unslash( $_POST['is_prevented'] ) ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
			return;
		}

		// bail if the nonce is not valid.
		$attachment_id = absint( $_POST['id'] );
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['security_check'] ) ), 'pda_ajax_nonce' . $attachment_id ) ) {
			return;
		}

		// bail if this is not an external file.
		if ( ! Files::get_instance()->get_file( $attachment_id )->is_valid() ) {
			return;
		}

		// refuse the protection.
		/* translators: %1$s will be replaced by the name of a plugin. */
		wp_send_json_error( array( 'message' => sprintf( __( 'External files could not be protected by %1$s, as they are not saved in the uploads directory. Save them privately on their external source instead.', 'external-files-in-media-library' ), 'Prevent Direct Access' ) ) );
	}

	/**
	 * Hide the column of the plugin for external files in the media library.
	 *
	 * @param string $column_name   The name of the column.
	 * @param int    $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function hide_column_for_external_files( string $column_name, int $attachment_id ): void {
		// bail if this is not the column of the plugin or not an external file.
		if ( 'direct_access' !== $column_name || ! Files::get_instance()->get_file( $attachment_id )->is_valid() ) {
			return;
		}

		// hide the output of the plugin and show the reason.
		echo '<style>#pda-v3-column_' . absint( $attachment_id ) . ' { display: none; }</style>';
		echo '<span class="description">' . esc_html__( 'Not available for external files', 'external-files-in-media-library' ) . '</span>';
	}

	/**
	 * Show a marker for protected local files, which are not exported, in the media library.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function show_marker( int $attachment_id ): void {
		// bail if it is not protected.
		if ( ! $this->is_protected( $attachment_id ) ) {
			return;
		}

		// show the marker.
		/* translators: %1$s will be replaced by the name of a plugin. */
		echo ' <span class="dashicons dashicons-lock" title="' . esc_attr( sprintf( __( 'This file is not exported, as it is protected by %1$s.', 'external-files-in-media-library' ), 'Prevent Direct Access' ) ) . '"></span>';
	}
}
