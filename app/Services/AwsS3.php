<?php
/**
 * File to add a hint for support of the AWS S3 platform.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle support for this platform.
 */
class AwsS3 extends Service_Plugin_Base implements Service {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'aws-s3';

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'AWS S3';

	/**
	 * Set the configuration for the external source of this service plugin.
	 *
	 * @var array<string,string>
	 */
	protected array $source_config = array(
		'type'             => 'github',
		'github_user'      => 'threadi',
		'github_slug'      => 'external-files-from-aws-s3',
		'plugin_slug'      => 'external-files-from-aws-s3',
		'plugin_main_file' => 'external-files-from-aws-s3/external-files-from-aws-s3.php',
	);

	/**
	 * Instance of actual object.
	 *
	 * @var ?AwsS3
	 */
	private static ?AwsS3 $instance = null;

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
	 * @return AwsS3
	 */
	public static function get_instance(): AwsS3 {
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
		return __( 'External Files from AWS S3', 'external-files-in-media-library' );
	}

	/**
	 * Return additional descriptions for the installation- and activation-dialog.
	 *
	 * @return array<int,string>
	 */
	protected function get_install_dialog_description(): array {
		return array(
			'<p>' . __( 'It will enable you to use files from AWS S3 in your WordPress, export them there, and synchronize them.', 'external-files-in-media-library' ) . '</p>',
			'<p>' . __( 'You can deactivate and uninstall the plugin at any time. However, this will remove the associated files from your media library.', 'external-files-in-media-library' ) . '</p>',
		);
	}
}
