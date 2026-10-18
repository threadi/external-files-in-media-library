<?php
/**
 * File to handle the import of URLs from XML files.
 *
 * Supported are any XML files with URLs, e.g. sitemaps, RSS and Atom feeds, product feeds or WordPress exports.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Services\Url_List\Parser_Base;
use ExternalFilesInMediaLibrary\Services\Url_List\Xml_Parser;

/**
 * Object to handle the import of URLs from XML files.
 */
class Xml extends Url_List_Base {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'xml';

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'XML';

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	protected string $settings_sub_tab = 'eml_xml';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Xml
	 */
	private static ?Xml $instance = null;

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
	 * @return Xml
	 */
	public static function get_instance(): Xml {
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
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// set title.
		$this->title = __( 'Import URLs from an XML file', 'external-files-in-media-library' );

		// use parent initialization.
		parent::init();
	}

	/**
	 * Return whether this listing object is disabled.
	 *
	 * @return bool
	 */
	public function is_disabled(): bool {
		return ! class_exists( 'XMLReader' );
	}

	/**
	 * Return the description for this listing object.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return '<span>' . __( 'Requirements not matched!', 'external-files-in-media-library' ) . '</span>';
	}

	/**
	 * Return the parser for XML files.
	 *
	 * @return Parser_Base
	 */
	protected function get_parser(): Parser_Base {
		return new Xml_Parser();
	}

	/**
	 * Return the possible file extensions for XML files.
	 *
	 * @return array<int,string>
	 */
	protected function get_extensions(): array {
		return array( 'xml' );
	}

	/**
	 * Return the possible mime types for XML files.
	 *
	 * @return array<int,string>
	 */
	protected function get_list_mime_types(): array {
		return array( 'text/xml', 'application/xml', 'application/rss+xml', 'application/atom+xml' );
	}

	/**
	 * Initialize the WP CLI support for this service.
	 *
	 * @return void
	 */
	public function cli(): void {}
}
