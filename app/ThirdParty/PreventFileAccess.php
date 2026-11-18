<?php
/**
 * File to handle support for the plugin "Prevent Files / Folders Access".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easySettingsForWordPress\Setting;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\Plugin\Settings;

/**
 * Object to handle support for this plugin.
 *
 * The plugin allows the access to files with the configured extensions in the uploads directory only for logged-in
 * users, via rules of the web server. This protection does not work for files on external sources, so these files
 * are not exported, while the restriction is enabled.
 */
class PreventFileAccess extends ThirdParty_Base implements ThirdParty {

	/**
	 * Instance of actual object.
	 *
	 * @var ?PreventFileAccess
	 */
	private static ?PreventFileAccess $instance = null;

	/**
	 * The extensions, which the plugin protects by default.
	 */
	private const DEFAULT_EXTENSIONS = array( 'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx' );


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
	 * @return PreventFileAccess
	 */
	public static function get_instance(): PreventFileAccess {
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
		return array( 'prevent-file-access/media-restriction.php' );
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
		add_action( 'efml_table_column_local_file', array( $this, 'show_marker' ) );
		add_action( 'init', array( $this, 'add_settings_hint' ), 30 );
	}

	/**
	 * Return the extensions, which the plugin protects, or an empty list, if the restriction is not enabled.
	 *
	 * @return array<int,string>
	 */
	public function get_protected_extensions(): array {
		// bail if the restriction is not enabled.
		if ( 1 !== absint( get_option( 'mo_enable_media_restriction' ) ) ) {
			return array();
		}

		// get the configured extensions (like the plugin does).
		$extensions = array_filter( explode( '|', strtolower( (string) preg_replace( '/[^a-zA-Z0-9|]/', '', (string) get_option( 'mo_media_restriction_file_types', '' ) ) ) ) );

		// return them or the default extensions of the plugin.
		return empty( $extensions ) ? self::DEFAULT_EXTENSIONS : array_values( $extensions );
	}

	/**
	 * Return whether the given attachment is protected by the plugin.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_protected( int $attachment_id ): bool {
		// get the protected extensions.
		$extensions = $this->get_protected_extensions();

		// bail if there are none.
		if ( empty( $extensions ) ) {
			return false;
		}

		// return whether the extension of the file is protected.
		$extension = strtolower( pathinfo( (string) get_post_meta( $attachment_id, '_wp_attached_file', true ), PATHINFO_EXTENSION ) );
		return '' !== $extension && in_array( $extension, $extensions, true );
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
		return sprintf( __( 'Files of its type are protected by %1$s, they would be public on the external source.', 'external-files-in-media-library' ), 'Prevent Files / Folders Access' );
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
		echo ' <span class="dashicons dashicons-lock" title="' . esc_attr( sprintf( __( 'This file is not exported, as files of its type are protected by %1$s.', 'external-files-in-media-library' ), 'Prevent Files / Folders Access' ) ) . '"></span>';
	}

	/**
	 * Add a hint to the setting for the export, which file types are not exported.
	 *
	 * @return void
	 */
	public function add_settings_hint(): void {
		// get the protected extensions.
		$extensions = $this->get_protected_extensions();

		// bail if there are none.
		if ( empty( $extensions ) ) {
			return;
		}

		// get the setting.
		$setting = Settings::get_instance()->get_settings_obj()->get_setting( 'eml_export' );

		// bail if it could not be loaded.
		if ( ! $setting instanceof Setting || is_null( $setting->get_field() ) ) {
			return;
		}

		// add the hint.
		$field = $setting->get_field();
		/* translators: %1$s will be replaced by the name of a plugin, %2$s by a list of file extensions. */
		$field->set_description( $field->get_description() . ' <strong>' . sprintf( __( 'Files of these types are not exported, as they are protected by %1$s: %2$s', 'external-files-in-media-library' ), 'Prevent Files / Folders Access', esc_html( implode( ', ', $extensions ) ) ) . '</strong>' );
	}
}
