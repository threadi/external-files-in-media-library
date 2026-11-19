<?php
/**
 * File to add a hint for support of the WebDav platform.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle support for this platform.
 */
class WebDav extends Service_Plugin_Base implements Service {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'webdav';

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'WebDav';

	/**
	 * The features of the service plugin, which are shown on the tiles before it is installed.
	 *
	 * @var array<int,string>
	 */
	protected array $plugin_features = array( 'sync', 'export', 'private', 'constants' );

	/**
	 * Set the configuration for the external source of this service plugin.
	 *
	 * @var array<string,string>
	 */
	protected array $source_config = array(
		'type'             => 'github',
		'github_user'      => 'threadi',
		'github_slug'      => 'external-files-from-webdav',
		'plugin_slug'      => 'external-files-from-webdav',
		'plugin_main_file' => 'external-files-from-webdav/external-files-from-webdav.php',
	);

	/**
	 * Instance of actual object.
	 *
	 * @var ?WebDav
	 */
	private static ?WebDav $instance = null;

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
	 * @return WebDav
	 */
	public static function get_instance(): WebDav {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Run during activation of the plugin.
	 *
	 * @return void
	 */
	public function activation(): void {}

	/**
	 * Return the label of the plugin.
	 *
	 * @return string
	 */
	public function get_plugin_label(): string {
		return __( 'External Files from WebDav', 'external-files-in-media-library' );
	}

	/**
	 * Return additional descriptions for the installation- and activation-dialog.
	 *
	 * @return array<int,string>
	 */
	protected function get_install_dialog_description(): array {
		return array(
			'<p>' . __( 'It will enable you to use files from WebDav in your WordPress, export them there, and synchronize them.', 'external-files-in-media-library' ) . '</p>',
			'<p>' . __( 'You can deactivate and uninstall the plugin at any time. However, this will remove the associated files from your media library.', 'external-files-in-media-library' ) . '</p>',
		);
	}

	/**
	 * Return further search terms of this service, separated by commas.
	 *
	 * @return string
	 */
	protected function get_keywords(): string {
		return 'WebDAV, Nextcloud, ownCloud, Seafile, DAV, cloud';
	}
}
