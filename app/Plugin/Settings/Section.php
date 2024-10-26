<?php
/**
 * This file represents a single section within a tab for settings.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Settings;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to hold single section within a tab in settings.
 */
class Section {
	/**
	 * The internal name of this tab.
	 *
	 * @var string
	 */
	private string $name = '';

	/**
	 * The title of this tab.
	 *
	 * @var string
	 */
	private string $title = '';

	/**
	 * Setting this section belongs to.
	 *
	 * @var Settings|null
	 */
	private ?Settings $setting = null;

	/**
	 * The callback for section header.
	 *
	 * @var string|array
	 */
	private string|array $callback = '__return_true';

	/**
	 * Constructor.
	 */
	public function __construct() {}

	/**
	 * Return the internal name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		$name = $this->name;

		/**
		 * Filter the name of a section object.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param string $name The name.
		 * @param Tab $this The tab-object.
		 */
		return apply_filters( 'eml_settings_section_name', $name, $this );
	}

	/**
	 * Set internal name.
	 *
	 * @param string $name The name to use.
	 *
	 * @return void
	 */
	public function set_name( string $name ): void {
		$this->name = $name;
	}

	/**
	 * Return the internal name.
	 *
	 * @return string
	 */
	public function get_title(): string {
		$title = $this->title;

		/**
		 * Filter the title of a section object.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param string $title The title.
		 * @param Tab $this The tab-object.
		 */
		return apply_filters( 'eml_settings_section_title', $title, $this );
	}

	/**
	 * Set internal name.
	 *
	 * @param string $title The title to use.
	 *
	 * @return void
	 */
	public function set_title( string $title ): void {
		$this->title = $title;
	}

	/**
	 * Return the setting this section belongs to.
	 *
	 * @return ?Settings
	 */
	public function get_setting(): ?Settings {
		$setting = $this->setting;

		/**
		 * Filter the settings of a tabs object.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param Settings $setting The settings.
		 * @param Tab $this The tab-object.
		 */
		return apply_filters( 'eml_settings_section_setting', $setting, $this );
	}

	/**
	 * Set the setting this section belongs to.
	 *
	 * @param Settings $settings_obj The settings object this section belongs to.
	 *
	 * @return void
	 */
	public function set_setting( Settings $settings_obj ): void {
		$this->setting = $settings_obj;
	}

	/**
	 * Return the callback.
	 *
	 * @return string|array
	 */
	public function get_callback(): string|array {
		return $this->callback;
	}

	/**
	 * Set the callback.
	 *
	 * @param string|array $callback The callback.
	 *
	 * @return void
	 */
	public function set_callback( string|array $callback ): void {
		// bail if given callback is not callable.
		if ( ! is_callable( $callback ) ) {
			return;
		}

		// set the callback.
		$this->callback = $callback;
	}
}
