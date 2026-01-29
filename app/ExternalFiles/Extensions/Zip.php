<?php
/**
 * This file controls the option to unzip files on media library.
 *
 * Hint:
 * We use functions of the Zip-service for this tasks.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Extensions;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;
use ExternalFilesInMediaLibrary\ExternalFiles\Extension_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\ImportDialog;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use WP_Post;
use WP_Query;

/**
 * Handler controls how to unzip files in media library.
 */
class Zip extends Extension_Base {
	/**
	 * The internal extension name.
	 *
	 * @var string
	 */
	protected string $name = 'zip';

	/**
	 * Instance of actual object.
	 *
	 * @var Zip|null
	 */
	private static ?Zip $instance = null;

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
	 * @return Zip
	 */
	public static function get_instance(): Zip {
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
	}

	/**
	 * Return the object title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Unzip of files', 'external-files-in-media-library' );
	}

	/**
	 * Return whether is extension require a capability to use it.
	 *
	 * @return bool
	 */
	public function has_capability(): bool {
		return true;
	}

	/**
	 * Return the default roles with capability for this object.
	 *
	 * @return array<int,string>
	 */
	public function get_capability_default(): array {
		return array( 'administrator' );
	}

	/**
	 * Return the description for the capability settings.
	 *
	 * @return string
	 */
	public function get_capability_description(): string {
		return __( 'Choose the roles that should be allowed to unzip files in your media library.', 'external-files-in-media-library' );
	}
}
