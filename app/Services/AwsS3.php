<?php
/**
 * File to add a hint for support of the AWS S3-compatible platforms.
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
	protected string $label = 'AWS S3-compatible';

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
	 * Return the label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'AWS S3-compatible', 'external-files-in-media-library' );
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
		return __( 'External Files from AWS S3-compatible platforms', 'external-files-in-media-library' );
	}

	/**
	 * Return additional descriptions for the installation- and activation-dialog.
	 *
	 * @return array<int,string>
	 */
	protected function get_install_dialog_description(): array {
		return array(
			'<p>' . __( 'It will enable you to use files from AWS S3 and other S3-compatible platforms in your WordPress, export them there, and synchronize them.', 'external-files-in-media-library' ) . '</p>',
			'<ul class="efml-dialog-list">'
				. '<li>' . __( 'Supported platforms: AWS S3, Backblaze, Cloudflare R2, DigitalOcean Spaces, Hetzner Object Storage, IONOS Object Storage, OVHcloud Object Storage, Scaleway Object Storage and Wasabi.', 'external-files-in-media-library' ) . '</li>'
				. '<li>' . __( 'Any other S3-compatible storage with its own endpoint, e.g. MinIO or Ceph.', 'external-files-in-media-library' ) . '</li>'
				. '<li>' . __( 'Files in AWS S3 can also be delivered via Amazon CloudFront, so your bucket can stay private.', 'external-files-in-media-library' ) . '</li>'
				. '<li>' . __( 'The credentials could be defined via constants in your wp-config.php.', 'external-files-in-media-library' ) . '</li>'
				. '<li>' . __( 'A complete connection test and detailed log entries help you to set up the connection.', 'external-files-in-media-library' ) . '</li>'
			. '</ul>',
			'<p>' . __( 'You can deactivate and uninstall the plugin at any time. However, this will remove the associated files from your media library.', 'external-files-in-media-library' ) . '</p>',
		);
	}

	/**
	 * Return further search terms of this service, separated by commas.
	 *
	 * @return string
	 */
	protected function get_keywords(): string {
		return 'AWS, Amazon, S3, CloudFront, Cloudflare R2, Backblaze, DigitalOcean Spaces, Hetzner, IONOS, OVHcloud, Scaleway, Wasabi, MinIO, object storage, bucket, CDN, cloud';
	}
}
