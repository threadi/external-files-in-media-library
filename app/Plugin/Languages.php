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
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
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
	 * depending on our own supported languages as 2-char-string (e.g., "en").
	 *
	 * If detected language is not supported by our plugin, use the fallback language.
	 *
	 * @return string
	 */
	public function get_current_lang(): string {
		$wp_language = substr( get_bloginfo( 'language' ), 0, 2 );

		// show deprecated warning for the old hook name.
		$wp_language = apply_filters_deprecated( 'eml_current_language', array( $wp_language ), '5.0.0', 'efml_current_language' );

		/**
		 * Filter the resulting language.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param string $wp_language The language-name (e.g., "en").
		 */
		return apply_filters( 'efml_current_language', $wp_language );
	}
}
