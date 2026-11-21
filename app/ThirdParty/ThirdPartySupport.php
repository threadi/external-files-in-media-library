<?php
/**
 * This file contains the handling of third party support we provide.
 *
 * This could be another WP-plugin we adapt.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Object to handle third party support.
 */
class ThirdPartySupport {

	/**
	 * Instance of actual object.
	 *
	 * @var ?ThirdPartySupport
	 */
	private static ?ThirdPartySupport $instance = null;

	/**
	 * The files of the supported plugins as keys.
	 *
	 * @var array<string,int>|null
	 */
	private ?array $supported_plugin_files = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() { }

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return ThirdPartySupport
	 */
	public static function get_instance(): ThirdPartySupport {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Run activation tasks on each supported ThirdParty-plugin.
	 *
	 * @return void
	 */
	public function activation(): void {
		foreach ( $this->get_third_party_support() as $class_name ) {
			// bail if class does not exist.
			if ( ! class_exists( $class_name ) ) {
				continue;
			}

			// extend the class name to match callable.
			$class_name .= '::get_instance';

			// bail if it is not callable.
			if ( ! is_callable( $class_name ) ) {
				continue;
			}

			// initiate object.
			$obj = $class_name();

			// bail if object is not a third party object.
			if ( ! $obj instanceof ThirdParty_Base ) {
				continue;
			}

			// activate this object.
			$obj->activation();
		}
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		foreach ( $this->get_third_party_support() as $class_name ) {
			// bail if class does not exist.
			if ( ! class_exists( $class_name ) ) {
				continue;
			}

			// extend the class name to match callable.
			$class_name .= '::get_instance';

			// bail if it is not callable.
			if ( ! is_callable( $class_name ) ) {
				continue;
			}

			// initiate object.
			$obj = $class_name();

			// bail if object is not a third party object.
			if ( ! $obj instanceof ThirdParty_Base ) {
				continue;
			}

			// initialize this object.
			$obj->init();
		}

		// mark the supported plugins in the list of plugins.
		add_filter( 'plugin_row_meta', array( $this, 'mark_supported_plugin' ), 10, 2 );
	}

	/**
	 * Return the objects of the supported plugins.
	 *
	 * @return array<int,ThirdParty_Base>
	 */
	private function get_objects(): array {
		$objects = array();
		foreach ( $this->get_third_party_support() as $class_name ) {
			// bail if class does not exist.
			if ( ! class_exists( $class_name ) ) {
				continue;
			}

			// extend the class name to match callable.
			$class_name .= '::get_instance';

			// bail if it is not callable.
			if ( ! is_callable( $class_name ) ) {
				continue;
			}

			// get the object.
			$obj = $class_name();

			// add it, if it is a third party object.
			if ( $obj instanceof ThirdParty_Base ) {
				$objects[] = $obj;
			}
		}
		return $objects;
	}

	/**
	 * Return the files of all supported plugins (e.g. "plugin/plugin.php").
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return array<int,string>
	 */
	public function get_supported_plugin_files(): array {
		$files = array();
		foreach ( $this->get_objects() as $obj ) {
			$files = array_merge( $files, $obj->get_plugin_files() );
		}

		/**
		 * Filter the files of the plugins, which are supported (e.g. "plugin/plugin.php").
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param array<int,string> $files List of plugin files.
		 */
		return array_values( array_unique( (array) apply_filters( 'efml_supported_plugin_files', $files ) ) );
	}

	/**
	 * Show a hint for supported plugins in the list of plugins.
	 *
	 * @param array<int|string,string> $meta        The meta data of the plugin.
	 * @param string                   $plugin_file The file of the plugin.
	 *
	 * @return array<int|string,string>
	 */
	public function mark_supported_plugin( array $meta, string $plugin_file ): array {
		// bail if the marking is disabled.
		if ( 1 !== absint( get_option( 'eml_mark_supported_plugins', 1 ) ) ) {
			return $meta;
		}

		// get the supported plugins once.
		if ( is_null( $this->supported_plugin_files ) ) {
			$this->supported_plugin_files = array_flip( $this->get_supported_plugin_files() );
		}

		// bail if this plugin is not supported.
		if ( ! isset( $this->supported_plugin_files[ $plugin_file ] ) ) {
			return $meta;
		}

		// add the hint.
		$meta['efml_supported'] = '<span class="dashicons dashicons-yes-alt" style="color: #00a32a; font-size: 16px; width: 16px; height: 16px; line-height: 1; vertical-align: -3px;"></span> <a href="' . esc_url( Helper::get_plugins_doc_url() ) . '" target="_blank" title="' . esc_attr__( 'This plugin works with external files. Learn more about the support.', 'external-files-in-media-library' ) . '">' . esc_html__( 'Supported by External Files in Media Library', 'external-files-in-media-library' ) . '</a>';
		return $meta;
	}

	/**
	 * Return list of third party support we implement.
	 *
	 * @return array<string>
	 */
	private function get_third_party_support(): array {
		$list = array(
			'ExternalFilesInMediaLibrary\ThirdParty\AdvancedMediaOffloader',
			'ExternalFilesInMediaLibrary\ThirdParty\BlockEditor',
			'ExternalFilesInMediaLibrary\ThirdParty\Brizy',
			'ExternalFilesInMediaLibrary\ThirdParty\CatFolders',
			'ExternalFilesInMediaLibrary\ThirdParty\Downloadlist',
			'ExternalFilesInMediaLibrary\ThirdParty\Elementor',
			'ExternalFilesInMediaLibrary\ThirdParty\EnableMediaReplace',
			'ExternalFilesInMediaLibrary\ThirdParty\EnhancedMediaLibrary',
			'ExternalFilesInMediaLibrary\ThirdParty\Exmage',
			'ExternalFilesInMediaLibrary\ThirdParty\Filebird',
			'ExternalFilesInMediaLibrary\ThirdParty\Folderly',
			'ExternalFilesInMediaLibrary\ThirdParty\Folders',
			'ExternalFilesInMediaLibrary\ThirdParty\IFolders',
			'ExternalFilesInMediaLibrary\ThirdParty\InstantImages',
			'ExternalFilesInMediaLibrary\ThirdParty\Massedge',
			'ExternalFilesInMediaLibrary\ThirdParty\MediaCloudSync',
			'ExternalFilesInMediaLibrary\ThirdParty\ImageWatermark',
			'ExternalFilesInMediaLibrary\ThirdParty\MediaBulkDownload',
			'ExternalFilesInMediaLibrary\ThirdParty\MediaCleaner',
			'ExternalFilesInMediaLibrary\ThirdParty\MediaFileRenamer',
			'ExternalFilesInMediaLibrary\ThirdParty\PerfectImages',
			'ExternalFilesInMediaLibrary\ThirdParty\RegenerateThumbnails',
			'ExternalFilesInMediaLibrary\ThirdParty\ForceRegenerateThumbnails',
			'ExternalFilesInMediaLibrary\ThirdParty\Imagify',
			'ExternalFilesInMediaLibrary\ThirdParty\ShortPixel',
			'ExternalFilesInMediaLibrary\ThirdParty\Smush',
			'ExternalFilesInMediaLibrary\ThirdParty\EWWW',
			'ExternalFilesInMediaLibrary\ThirdParty\TinyPNG',
			'ExternalFilesInMediaLibrary\ThirdParty\ConverterForMedia',
			'ExternalFilesInMediaLibrary\ThirdParty\ModernImageFormats',
			'ExternalFilesInMediaLibrary\ThirdParty\MediaLibraryAssistant',
			'ExternalFilesInMediaLibrary\ThirdParty\MediaLibraryOrganizer',
			'ExternalFilesInMediaLibrary\ThirdParty\NextGen',
			'ExternalFilesInMediaLibrary\ThirdParty\Polylang',
			'ExternalFilesInMediaLibrary\ThirdParty\PreventDirectAccess',
			'ExternalFilesInMediaLibrary\ThirdParty\PreventFileAccess',
			'ExternalFilesInMediaLibrary\ThirdParty\RankMath',
			'ExternalFilesInMediaLibrary\ThirdParty\RealMediaLibrary',
			'ExternalFilesInMediaLibrary\ThirdParty\RoboGallery',
			'ExternalFilesInMediaLibrary\ThirdParty\SimplyStatic',
			'ExternalFilesInMediaLibrary\ThirdParty\WooCommerce',
			'ExternalFilesInMediaLibrary\ThirdParty\WpConsentApi',
			'ExternalFilesInMediaLibrary\ThirdParty\WpExtraFileTypes',
			'ExternalFilesInMediaLibrary\ThirdParty\Yoast',
		);

		// show deprecated warning for the old hook name.
		$list = apply_filters_deprecated( 'eml_third_party_support', array( $list ), '5.0.0', 'efml_third_party_support' );

		/**
		 * Filter the list of third party support.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array<string> $list List of third party support.
		 */
		return apply_filters( 'efml_third_party_support', $list );
	}
}
