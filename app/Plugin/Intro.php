<?php
/**
 * File to handle intro for this plugin.
 *
 * After fresh installation of this plugin it starts via button click:
 * 1. Redirect to media-new.php
 * 2. Click on "External files" there.
 * 3. Add URL for example file in field.
 * 4. Click on "Add URLs"
 * 5. Show hint for different sources for files.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Button;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Section;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;

/**
 * Initialize this object.
 */
class Intro {
	/**
	 * Instance of this object.
	 *
	 * @var ?Intro
	 */
	private static ?Intro $instance = null;

	/**
	 * Constructor for this handler.
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
	public static function get_instance(): Intro {
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
		// add admin-actions.
		add_action( 'admin_action_efml_intro_reset', array( $this, 'reset_intro' ) );

		// add settings.
		add_action( 'init', array( $this, 'add_settings' ), 20 );

		$false = absint( get_option( 'efml_intro' ) ) > 0;
		/**
		 * Hide intro via hook.
		 *
		 * @since 5.0.0 Available since 5.0.0
		 *
		 * @param bool $false Return true to hide the intro.
		 */
		if ( apply_filters( 'efml_hide_intro', $false ) ) {
			return;
		}

		// add our script.
		add_action( 'admin_enqueue_scripts', array( $this, 'add_js' ) );

		// add AJAX-actions.
		add_action( 'wp_ajax_efml_intro_closed', array( $this, 'closed' ) );
		add_action( 'wp_ajax_efml_intro_started', array( $this, 'started' ) );
	}

	/**
	 * Return whether the intro is completed.
	 *
	 * @return bool
	 */
	public function is_closed(): bool {
		return absint( get_option( 'efml_intro' ) ) > 0;
	}

	/**
	 * Set the intro to closed.
	 *
	 * @return void
	 */
	public function set_closed(): void {
		update_option( 'efml_intro', time() );
	}

	/**
	 * Save that intro has been closed.
	 *
	 * @return void
	 */
	public function closed(): void {
		// check nonce.
		check_ajax_referer( 'efml-intro-closed', 'nonce' );

		// save that intro has been closed.
		$this->set_closed();

		// response with success message.
		wp_send_json_success();
	}

	/**
	 * Run tasks if intro has been startet.
	 *
	 * @return void
	 */
	public function started(): void {
		// check nonce.
		check_ajax_referer( 'efml-intro-started', 'nonce' );

		// remove the transient after installation.
		Transients::get_instance()->get_transient_by_name( 'eml_welcome' )->delete();

		// response with success message.
		wp_send_json_success();
	}

	/**
	 * Add the intro.js-scripts and -styles.
	 *
	 * @source https://introjs.com/docs/examples/basic/hello-world
	 *
	 * @return void
	 */
	public function add_js(): void {
		// embed necessary scripts for dialog.
		$path = Helper::get_plugin_path() . 'node_modules/intro.js/minified/';
		$url  = Helper::get_plugin_url() . 'node_modules/intro.js/minified/';

		// bail if path does not exist.
		if ( ! file_exists( $path ) ) {
			return;
		}

		// embed the JS-script from intro.js.
		wp_enqueue_script(
			'efml-intro',
			$url . 'intro.min.js',
			array(),
			(string) filemtime( Helper::get_plugin_path() . 'intro.min.js' ),
			true
		);

		// embed our own JS-script.
		wp_enqueue_script(
			'efml-intro-custom',
			Helper::get_plugin_url() . 'admin/intro.js',
			array( 'efml-intro' ),
			(string) filemtime( Helper::get_plugin_path() . '/admin/intro.js' ),
			true
		);

		// embed the CSS-file.
		wp_enqueue_style(
			'efml-intro',
			$url . 'introjs.min.css',
			array(),
			(string) filemtime( Helper::get_plugin_path() . 'introjs.min.css' ),
		);

		// embed the CSS-file.
		wp_enqueue_style(
			'efml-intro-custom',
			Helper::get_plugin_url() . 'admin/intro.css',
			array(),
			(string) filemtime( Helper::get_plugin_path() . '/admin/intro.css' ),
		);

		// create URL for add new media file with intro marker.
		$url_1 = add_query_arg(
			array(
				'efml-intro' => 'true'
			),
			get_admin_url() . 'media-new.php'
		);

		// get the example URL.
		// TODO !!!
		$url_2 = "https://pdfobject.com/pdf/sample.pdf";

		// create the forward URL after end of intro.
		$url_3 = get_admin_url() . 'media-new.php';

		// add php-vars to our js-script.
		wp_localize_script(
			'efml-intro-custom',
			'efmlIntroJsVars',
			array(
				'ajax_url'                     => admin_url( 'admin-ajax.php' ),
				'intro_closed_nonce'           => wp_create_nonce( 'efml-intro-closed' ),
				'intro_started_nonce' => wp_create_nonce( 'efml-intro-started' ),
				'button_title_next'            => __( 'Next', 'external-files-in-media-library' ),
				'button_title_back'            => __( 'Back', 'external-files-in-media-library' ),
				'button_title_done'            => __( 'Done', 'external-files-in-media-library' ),
				'url_1' => $url_1,
				'url_2' => $url_2,
				'url_3' => $url_3,
				'delay' => 50,
				'step_1_title'                 => __( 'Intro', 'external-files-in-media-library' ),
				'step_1_intro'                 => __( 'Thank you for installing External Files for Media Library. We will show you some basics to use this plugin.', 'external-files-in-media-library' ),
				'step_2_title'                 => __( 'Start adding URLs', 'external-files-in-media-library' ),
				'step_2_intro'                 => __( 'Go to on Media Library > New<br><br>We will forward you there now. Please wait a moment.', 'external-files-in-media-library' ),
				'step_3_title'                 => __( 'Open import dialog', 'external-files-in-media-library' ),
				'step_3_intro'                 => __( 'Click here to get the import dialog for external files. We will do this for you now.', 'external-files-in-media-library' ),
				'step_4_title'                 => __( 'Add your URL', 'external-files-in-media-library' ),
				'step_4_intro'                 => __( 'Enter the URL of the file you want to import. We will enter an example URL for you now.', 'external-files-in-media-library' ),
				'step_5_title'                 => __( 'Submit the URL', 'external-files-in-media-library' ),
				'step_5_intro'                 => __( 'Click to import the URL in your media library. We will to this for you now to demonstrate the function.', 'external-files-in-media-library' ),
				'step_6_title'                 => __( 'Result of the import', 'external-files-in-media-library' ),
				'step_6_intro'                 => __( 'The result of the import will be displayed. If successful, you can go directly to the URL(s) in the media database from here. If an error occurs, you will be given information about the reason.', 'external-files-in-media-library' ),
				'step_7_title'                 => __( 'And now you!', 'external-files-in-media-library' ),
				/* translators: %1$s and %1$s will be replaced by URLs. */
				'step_7_intro'                 => sprintf( __( 'Now you can store URLs in your media database yourself. Just follow the steps we just showed you.<br><br>Under Media Library > <a href="%1$s" target="_blank">Add External Files</a>, you can discover many more options for importing files besides specifying their URLs.<br><br>If you have any questions, please feel free to <a href="%2$s" target="_blank">contact the support forum</a>.', 'external-files-in-media-library' ), Helper::get_add_media_url(), Helper::get_plugin_support_url() ),
			)
		);
	}

	/**
	 * Reset intro via request.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function reset_intro(): void {
		// check nonce.
		check_admin_referer( 'efml-intro-reset', 'nonce' );

		// delete the actual setting.
		delete_option( 'efml_intro' );

		// redirect user to intro-start.
		wp_safe_redirect( (string) wp_get_referer() );
		exit;
	}

	/**
	 * Add settings for the intro.
	 * *
	 *
	 * @return void
	 */
	public function add_settings(): void {
		// get settings object.
		$settings_obj = \ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings::get_instance();

		// get the main settings page.
		$main_settings_page = $settings_obj->get_page( 'eml_settings' );

		// bail if page could not be loaded.
		if ( ! $main_settings_page instanceof Page ) {
			return;
		}

		// get the advanced tab.
		$advanced_tab = $main_settings_page->get_tab( 'eml_advanced' );

		// bail if page could not be loaded.
		if ( ! $advanced_tab instanceof Tab ) {
			return;
		}

		// get the advanced section.
		$advanced_section = $advanced_tab->get_section( 'settings_section_advanced' );

		// bail if section could not be loaded.
		if ( ! $advanced_section instanceof Section ) {
			return;
		}

		// add setting.
		$setting = $settings_obj->add_setting( 'efmlResetIntro' );
		$setting->set_section( $advanced_section );
		$setting->set_autoload( false );
		$setting->prevent_export( true );
		$field = new Button();
		$field->set_title( __( 'Intro', 'external-files-in-media-library' ) );
		if( $this->is_closed() ) {
			$field->set_button_title( __( 'Reset to run intro', 'external-files-in-media-library' ) );
			$field->set_button_url(
				add_query_arg(
					array(
						'action' => 'efml_intro_reset',
						'nonce'  => wp_create_nonce( 'efml-intro-reset' ),
					),
					get_admin_url() . 'admin.php'
				)
			);
		}
		else {
			$field->set_button_title( __( 'Run intro', 'external-files-in-media-library' ) );
			$field->add_class( 'efml-intro-start' );
			$field->set_button_url( '#' );
		}
		$setting->set_field( $field );

		// add setting.
		$setting = $settings_obj->add_setting( 'efml_intro' );
		$setting->set_section( $advanced_section );
		$setting->set_show_in_rest( true );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
	}
}
