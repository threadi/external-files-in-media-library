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
		return $this->name;
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
		return $this->title;
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
		return $this->setting;
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
		// TODO check if callable.
		$this->callback = $callback;
	}
}
