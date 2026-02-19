<?php
/**
 * File to handle support for Polylang.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\ExternalFiles\File;

/**
 * Object to handle support for this plugin.
 */
class Polylang extends ThirdParty_Base implements ThirdParty {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Polylang
	 */
	private static ?Polylang $instance = null;

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
	 * @return Polylang
	 */
	public static function get_instance(): Polylang {
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
		// bail if Polylang is not active.
		if ( ! defined( 'POLYLANG_BASENAME' ) ) {
			return;
		}

		// add settings.
		add_action( 'init', array( $this, 'init_polylang' ) );

		// use our own hooks.
		add_action( 'efml_after_file_save', array( $this, 'efml_save_file_in_every_language' ) );
	}

	/**
	 * Initialize the WooCommerce-support.
	 *
	 * @return void
	 */
	public function init_polylang(): void {
		// get settings object.
		$settings_obj = Settings::get_instance();

		// get the settings page.
		$settings_page = $settings_obj->get_page( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_menu_slug() );

		// bail if page does not exist.
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// add settings tab for Polylang.
		$woocommerce_settings_tab = $settings_page->add_tab( 'polylang', 120 );
		$woocommerce_settings_tab->set_title( __( 'Polylang', 'external-files-in-media-library' ) );

		// add a section for Polylang settings.
		$woocommerce_settings_section = $woocommerce_settings_tab->add_section( 'eml_polylang_settings', 10 );
		$woocommerce_settings_section->set_title( __( 'Polylang', 'external-files-in-media-library' ) );

		// add setting to enable the Polylang-support.
		$woocommerce_settings_setting = $settings_obj->add_setting( 'eml_polylang' );
		$woocommerce_settings_setting->set_section( $woocommerce_settings_section );
		$woocommerce_settings_setting->set_type( 'integer' );
		$woocommerce_settings_setting->set_default( 1 );
		$woocommerce_settings_setting->set_field(
			array(
				'title'       => __( 'Enable support for Polylang', 'external-files-in-media-library' ),
				'description' => __( 'If enabled each new external file will be added in each language, which is configured in Polylang.', 'external-files-in-media-library' ),
				'type'        => 'Checkbox',
			)
		);
	}

	/**
	 * Save a new external file in all other languages, if enabled.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 */
	public function efml_save_file_in_every_language( File $external_file_obj ): void {
		// bail if Polylang support is not enabled.
		if ( 1 !== absint( get_option( 'eml_polylang' ) ) ) {
			return;
		}

		// get the language of the external file object.
		$primary_obj_language = pll_get_post_language( $external_file_obj->get_id() );

		// loop through the languages set in Polylang and duplicate the file for each language, if it does not already exist.
		foreach ( pll_languages_list() as $language ) {
			// bail if this is the primary language.
			if ( $primary_obj_language === $language ) {
				continue;
			}

			// get translations of the main object.
			$translations = pll_get_post_translations( $external_file_obj->get_id() );

			// bail if a translation for this file in this language is already set.
			if ( isset( $translations[ $language ] ) ) {
				continue;
			}

			// create the "post_array" to add a new entry.
			$post_array = get_post( $external_file_obj->get_id(), ARRAY_A );

			// bail if array could not be loaded.
			if( ! is_array( $post_array ) ) {
				continue;
			}

			// remove the ID.
			unset( $post_array['ID'] );

			// copy the external file object.
			$post_id = pll_insert_post( $post_array, $language );

			// bail on error.
			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			// copy the post meta fields.
			$post_meta = get_post_custom( $external_file_obj->get_id() );
			foreach ( $post_meta as $key => $values ) {
				foreach ( $values as $value ) {
					add_post_meta( $post_id, $key, maybe_unserialize( $value ) );
				}
			}

			// copy post taxonomies.
			$taxonomies = get_post_taxonomies( $external_file_obj->get_id() );
			foreach ( $taxonomies as $taxonomy_name ) {
				// get the terms from the original object.
				$term_ids = wp_get_object_terms( $external_file_obj->get_id(), $taxonomy_name, array( 'fields' => 'ids' ) );

				// bail if terms could not be loaded.
				if ( ! is_array( $term_ids ) ) {
					continue;
				}

				// add them to the new object.
				wp_set_object_terms( $post_id, $term_ids, $taxonomy_name );
			}

			// create the translations if not set.
			if ( empty( $translations ) ) {
				$translations = array(
					$primary_obj_language => $external_file_obj->get_id(),
				);
			}

			// assign this object to the main language object in Polylang.
			$translations[ $language ] = $post_id;

			// and save them.
			pll_save_post_translations( $translations );
		}
	}
}
