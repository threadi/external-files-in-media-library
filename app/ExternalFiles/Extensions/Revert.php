<?php
/**
 * This file controls the option to revert the last import of external files in media library.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Extensions;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Checkbox;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Section;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;
use ExternalFilesInMediaLibrary\ExternalFiles\Extension_Base;
use WP_Query;

/**
 * Handler controls how to check the availability of external files.
 */
class Revert extends Extension_Base {
	/**
	 * The internal extension name.
	 *
	 * @var string
	 */
	protected string $name = 'revert';

	/**
	 * Instance of actual object.
	 *
	 * @var Revert|null
	 */
	private static ?Revert $instance = null;

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
	 * @return Revert
	 */
	public static function get_instance(): Revert {
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
		// use hooks.
		add_action( 'init', array( $this, 'add_settings' ), 20 );

		// use our own hooks.
		add_filter( 'efml_dialog_after_adding', array( $this, 'change_dialog' ) );

		// add actions.
		add_action( 'admin_action_efml_revert', array( $this, 'revert_by_request' ) );
	}

	/**
	 * Return the object title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Revert the last import', 'external-files-in-media-library' );
	}

	/**
	 * Add our custom settings for this plugin.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		// get the settings object.
		$settings_obj = Settings::get_instance();

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
		$setting = $settings_obj->add_setting( 'eml_revert' );
		$setting->set_section( $advanced_section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Checkbox();
		$field->set_title( __( 'Allow to revert the last import', 'external-files-in-media-library' ) );
		$field->set_description( __( 'If enabled an option will be visible after each import, to revert the last import. This will remove the imported file from media library.', 'external-files-in-media-library' ) );
		$setting->set_field( $field );
	}

	/**
	 * Hide this extension in settings.
	 *
	 * @return bool
	 */
	public function hide(): bool {
		return true;
	}

	/**
	 * Add our button to revert the last import in dialog after import has been run.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 *
	 * @return array<string,mixed>
	 */
	public function change_dialog( array $dialog ): array {
		// bail if option is not enabled.
		if( 0 === absint( get_option( 'eml_revert' ) ) ) {
			return $dialog;
		}

		// create URL.
		$url = add_query_arg(
			array(
				'action' => 'efml_revert',
				'nonce' => wp_create_nonce( 'efml-revert' ),
			),
			get_admin_url() . 'admin.php'
		);

		// add the button.
		$dialog['detail']['buttons'][] = array(
			'action'  => 'location.href="' . $url . '";',
			'variant' => 'secondary',
			'text'    => __( 'Undo this import', 'external-files-in-media-library' ),
		);

		// return the resulting dialog.
		return $dialog;
	}

	/**
	 * Revert the last import by request.
	 *
	 * @return void
	 */
	public function revert_by_request(): void {
		// check nonce.
		check_ajax_referer( 'efml-revert', 'nonce' );

		// get the last job ID for the actual user.
		$job_id = Jobs::get_instance()->get_last_job_id();

		// bail if no job ID is given.
		if( empty( $job_id ) ) {
			wp_safe_redirect( (string) wp_get_referer() );
		}

		// get all files with this job ID.
		$query = array(
			'post_type'   => 'attachment',
			'post_status' => 'any',
			'posts_per_page' => -1,
			'meta_query'  => array(
				array(
					'key'     => 'eml_job_id',
					'value'   => $job_id,
					'compare' => '=',
				)
			),
			'fields'      => 'ids',
		);
		$result = new WP_Query( $query );

		// bail if no results could be found.
		if( 0 === $result->found_posts ) {
			wp_safe_redirect( (string) wp_get_referer() );
		}

		// delete all those files.
		foreach( $result->posts as $post_id ) {
			wp_delete_post( absint( $post_id ), true );
		}

		// show ok message.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_name( 'efml_revert_ok' );
		$transient_obj->set_message( '<strong>' . __( 'The last import has been reverted!', 'external-files-in-media-library' ) . '</strong> ' . __( 'The imported files have been deleted.', 'external-files-in-media-library' ) );
		$transient_obj->set_type( 'success' );
		$transient_obj->save();

		// redirect the user.
		wp_safe_redirect( (string) wp_get_referer() );
	}
}
