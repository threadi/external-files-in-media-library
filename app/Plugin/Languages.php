<?php
/**
 * File to handle all language-related tasks.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Handler for any language-tasks.
 */
class Languages {
	/**
	 * Instance of this object.
	 *
	 * @var ?Languages
	 */
	private static ?Languages $instance = null;

	/**
	 * Fallback-language.
	 *
	 * @var string
	 */
	private string $fallback_language_name = 'en';

	/**
	 * Constructor for Init-Handler.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Languages {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/**
	 * Check whether the current language in this Wordpress-project is a german language.
	 *
	 * @return bool
	 */
	public function is_german_language(): bool {
		$german_languages = array(
			'de',
			'de-DE',
			'de-DE_formal',
			'de-CH',
			'de-ch-informal',
			'de-AT',
		);

		// return result: true if the actual WP-language is a german language.
		return in_array( $this->get_current_lang(), $german_languages, true );
	}

	/**
	 * Return the current language in frontend and backend
	 * depending on our own supported languages as 2-char-string (e.g. "en").
	 *
	 * If detected language is not supported by our plugin, use the fallback language.
	 *
	 * @return string
	 */
	public function get_current_lang(): string {
		$wp_language = substr( get_bloginfo( 'language' ), 0, 2 );

		/**
		 * Filter the resulting language.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param string $wp_language The language-name (e.g. "en").
		 */
		return apply_filters( 'eml_current_language', $wp_language );
	}
}
