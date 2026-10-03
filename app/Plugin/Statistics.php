<?php
/**
 * This file contains an object to handle statistics for this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easySettingsForWordPress\Fields\Button;
use easySettingsForWordPress\Fields\TextInfo;
use easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use ExternalFilesInMediaLibrary\Services\Services;

/**
 * Object to handle statistics.
 */
class Statistics {
	/**
	 * The cache key.
	 */
	public const CACHE_KEY = 'efml_sizes_per_service';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Statistics
	 */
	private static ?Statistics $instance = null;

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
	 * @return Statistics
	 */
	public static function get_instance(): Statistics {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// add settings.
		add_action( 'init', array( $this, 'init_statistics' ), 30 );

		// use our own hooks.
		add_action( 'efml_after_file_save', array( $this, 'add_file_count' ), 10, 0 );
		add_action( 'efml_after_file_save', array( $this, 'add_file_sizes' ) );
		add_action( 'efml_file_delete', array( $this, 'sub_file_count' ), 10, 0 );
		add_action( 'efml_file_delete', array( $this, 'sub_file_sizes' ) );
		add_action( 'efml_real_import_local', array( $this, 'sub_file_count' ), 10, 0 );
		add_action( 'efml_real_import_local', array( $this, 'sub_file_sizes' ) );
		add_action( 'efml_switch_to_local_after', array( $this, 'clear_sizes_per_service_cache' ), 10, 0 );
		add_action( 'efml_switch_to_external_after', array( $this, 'clear_sizes_per_service_cache' ), 10, 0 );

		// add actions.
		add_action( 'admin_action_eml_recalc_files', array( $this, 'recalc_files_by_request' ) );
	}

	/**
	 * Initiate the statistics settings tab.
	 *
	 * @return void
	 */
	public function init_statistics(): void {
		// get the settings object.
		$settings_obj = Settings::get_instance()->get_settings_obj();

		// get the settings page.
		$settings_page = $settings_obj->get_page( Settings::get_instance()->get_menu_slug() );

		// bail if page does not exist.
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// add a tab.
		$tab = $settings_page->add_tab( 'eml_statistics', 110 );
		$tab->set_title( __( 'Statistics', 'external-files-in-media-library' ) );
		$tab->set_hide_save( true );

		// add a section for file statistics.
		$section_files = $tab->add_section( 'section_file_statistics', 10 );
		$section_files->set_title( __( 'Files', 'external-files-in-media-library' ) );

		// add setting for file count.
		$file_count_setting = $settings_obj->add_setting( 'eml_file_count' );
		$file_count_setting->set_section( $section_files );
		$file_count_setting->set_type( 'integer' );
		$file_count_setting->set_default( 0 );
		$file_count_setting->prevent_export( true );
		$file_count_setting->set_field(
			array(
				'type'  => 'Value',
				'title' => __( 'External file counter', 'external-files-in-media-library' ),
			)
		);

		// add setting for file sizes.
		$file_size_setting = $settings_obj->add_setting( 'eml_file_sizes' );
		$file_size_setting->set_section( $section_files );
		$file_size_setting->set_type( 'integer' );
		$file_size_setting->set_default( 0 );
		$file_size_setting->set_autoload( false );
		$file_size_setting->prevent_export( true );
		$file_size_setting->set_read_callback( array( $this, 'format_file_sizes' ) );
		$file_size_setting->set_field(
			array(
				'type'        => 'Value',
				'title'       => __( 'External file sizes', 'external-files-in-media-library' ),
				'description' => __( 'The value is in bytes.', 'external-files-in-media-library' ),
			)
		);

		// add setting to list the file sizes per external service.
		$service_sizes_setting = $settings_obj->add_setting( 'eml_file_sizes_per_service' );
		$service_sizes_setting->set_section( $section_files );
		$service_sizes_setting->set_type( 'string' );
		$service_sizes_setting->set_autoload( false );
		$service_sizes_setting->prevent_export( true );
		$service_sizes_field = new TextInfo( $settings_obj );
		$service_sizes_field->set_title( __( 'External file sizes per service', 'external-files-in-media-library' ) );
		$service_sizes_field->set_description( $this->get_sizes_per_service_as_html() );
		$service_sizes_setting->set_field( $service_sizes_field );

		// create re-calc URL.
		$url = add_query_arg(
			array(
				'action' => 'eml_recalc_files',
				'nonce'  => wp_create_nonce( 'eml-recalc-files' ),
			),
			get_admin_url() . 'admin.php'
		);

		// create import dialog.
		$dialog = array(
			'className' => 'efml',
			'title'     => __( 'Reset file statistics', 'external-files-in-media-library' ),
			'texts'     => array(
				'<p><strong>' . __( 'Click on the button below to reset the file statistics for external files.', 'external-files-in-media-library' ) . '</strong></p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'location.href="' . $url . '";',
					'variant' => 'primary',
					'text'    => __( 'Reset now', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'secondary',
					'text'    => __( 'Cancel', 'external-files-in-media-library' ),
				),
			),
		);

		// add re-calc button.
		$setting = $settings_obj->add_setting( 'eml_file_re_calc' );
		$setting->set_section( $section_files );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$setting->set_autoload( false );
		$setting->prevent_export( true );
		$field = new Button( $settings_obj );
		$field->set_title( __( 'Reset file statistics', 'external-files-in-media-library' ) );
		$field->set_button_title( __( 'Reset now', 'external-files-in-media-library' ) );
		$field->add_class( 'easy-dialog-for-wordpress' );
		$field->add_data( 'dialog', Helper::get_json( $dialog ) );
		$setting->set_field( $field );
	}

	/**
	 * Return the actual file count.
	 *
	 * @return int
	 */
	private function get_file_count(): int {
		return absint( get_option( 'eml_file_count' ) );
	}

	/**
	 * Add 1 to the file count.
	 *
	 * @return void
	 */
	public function add_file_count(): void {
		update_option( 'eml_file_count', $this->get_file_count() + 1 );
	}

	/**
	 * Sub 1 from the file count.
	 *
	 * @return void
	 */
	public function sub_file_count(): void {
		update_option( 'eml_file_count', $this->get_file_count() - 1 );
	}

	/**
	 * Set file count to specific value.
	 *
	 * @param int $new_value The new value.
	 *
	 * @return void
	 */
	private function set_file_count( int $new_value ): void {
		update_option( 'eml_file_count', $new_value );
	}

	/**
	 * Return the actual file count.
	 *
	 * @return int
	 */
	private function get_file_sizes(): int {
		return absint( get_option( 'eml_file_sizes' ) );
	}

	/**
	 * Add the file size to the total.
	 *
	 * @param File $external_file_obj The external file as object.
	 *
	 * @return void
	 */
	public function add_file_sizes( File $external_file_obj ): void {
		update_option( 'eml_file_sizes', $this->get_file_sizes() + $external_file_obj->get_filesize() );

		// clear the cache.
		$this->clear_sizes_per_service_cache();
	}

	/**
	 * Subtract the file size from the total number.
	 *
	 * @param File $external_file_obj The external file as object.
	 *
	 * @return void
	 */
	public function sub_file_sizes( File $external_file_obj ): void {
		update_option( 'eml_file_sizes', $this->get_file_sizes() - $external_file_obj->get_filesize() );

		// clear the cache.
		$this->clear_sizes_per_service_cache();
	}

	/**
	 * Set file sizes to specific value.
	 *
	 * @param int $new_value The new value.
	 *
	 * @return void
	 */
	private function set_file_sizes( int $new_value ): void {
		update_option( 'eml_file_sizes', $new_value );
	}

	/**
	 * Recalc the file statistic by request.
	 *
	 * @return void
	 */
	public function recalc_files_by_request(): void {
		// check nonce.
		check_admin_referer( 'eml-recalc-files', 'nonce' );

		// bail if user has not the capability.
		if ( ! current_user_can( Settings::get_instance()->get_settings_obj()->get_capability() ) ) {
			return;
		}

		// get all external files.
		$files = Files::get_instance()->get_files();

		// get referer.
		$referer = wp_get_referer();

		// if referer is false, set empty string.
		if ( ! $referer ) {
			$referer = '';
		}

		// if no files could be loaded, set all settings to 0.
		if ( empty( $files ) ) {
			// reset the sizes.
			$this->set_file_count( 0 );
			$this->set_file_sizes( 0 );

			// clear the cache.
			$this->clear_sizes_per_service_cache();

			// trigger ok message.
			$transients_obj = Transients::get_instance();
			$transient_obj  = $transients_obj->add();
			$transient_obj->set_name( 'eml_recalc_ok' );
			$transient_obj->set_message( __( '<strong>The statistics has been reset.</strong> No external files have been found.', 'external-files-in-media-library' ) );
			$transient_obj->set_type( 'success' );
			$transient_obj->save();

			// forward user.
			wp_safe_redirect( $referer );
			exit;
		}

		// loop through the list and count the values.
		$file_count = 0;
		$file_size  = 0;
		foreach ( $files as $file ) {
			++$file_count;
			$file_size += $file->get_filesize();
		}

		// save the new values.
		$this->set_file_count( $file_count );
		$this->set_file_sizes( $file_size );

		// clear the cache.
		$this->clear_sizes_per_service_cache();

		// trigger ok message.
		$transients_obj = Transients::get_instance();
		$transient_obj  = $transients_obj->add();
		$transient_obj->set_name( 'eml_recalc_ok' );
		$transient_obj->set_message( __( 'The statistics has been reset.', 'external-files-in-media-library' ) );
		$transient_obj->set_type( 'success' );
		$transient_obj->save();

		// forward user.
		wp_safe_redirect( $referer );
		exit;
	}

	/**
	 * Format the file size in KB or MB with 2 decimals.
	 *
	 * @param int $value The value to format.
	 *
	 * @return string
	 */
	public function format_file_sizes( int $value ): string {
		return (string) size_format( $value, 2 );
	}

	/**
	 * Return the total file size, in bytes, per external service currently in use.
	 *
	 * Only files which are actually hosted on the external platform are counted:
	 * files which have been switched to local hosting keep their URL-meta (and
	 * therefore still show up in Files::get_files()) but no longer occupy space
	 * on the external service, so they are excluded here.
	 *
	 * @return array<string,int> List of service name => size in bytes, sorted descending by size.
	 */
	private function get_sizes_per_service(): array {
		$sizes = array();

		// get all external files.
		foreach ( Files::get_instance()->get_files() as $external_file_obj ) {
			// bail if this file is locally saved - it is not really hosted external (anymore).
			if ( $external_file_obj->is_locally_saved() ) {
				continue;
			}

			// get the name of the used service, use a fallback if it is unknown.
			$service_name = $external_file_obj->get_service_name();
			if ( empty( $service_name ) ) {
				$service_label = __( 'Unknown', 'external-files-in-media-library' );
			} else {
				$service_obj = Services::get_instance()->get_service_by_name( $service_name );
				if ( $service_obj instanceof Service_Base ) {
					$service_label = $service_obj->get_label();
				} else {
					$service_label = $service_name;
				}
			}

			// add up the file size for this service.
			$sizes[ $service_label ] = ( ! empty( $sizes[ $service_name ] ) ? $sizes[ $service_name ] : 0 ) + $external_file_obj->get_filesize();
		}

		// sort the list by size, biggest first.
		arsort( $sizes );

		/**
		 * Filter the resulting list of file sizes per service.
		 *
		 * @since 5.5.0 Available since 5.5.0.
		 * @param array<string,int> $sizes List of service name => size in bytes.
		 */
		return apply_filters( 'efml_sizes_per_service', $sizes );
	}

	/**
	 * Return the list of file sizes per external service, rendered as an HTML table.
	 *
	 * @return string
	 */
	public function get_sizes_per_service_as_html(): string {
		// get the content from cache.
		$html = get_transient( self::CACHE_KEY );

		// if cache is empty, create it.
		if ( false === $html ) {

			// get the sizes per service.
			$sizes = $this->get_sizes_per_service();

			// bail if list is empty.
			if ( empty( $sizes ) ) {
				// create the hint.
				$html = '<p>' . esc_html__( 'No external hosted files found.', 'external-files-in-media-library' ) . '</p>';

				// save also this result in cache to prevent the calculation on every request.
				set_transient( self::CACHE_KEY, $html, WEEK_IN_SECONDS );

				// return the hint.
				return $html;
			}

			// build the table.
			$html  = '<table class="widefat striped">';
			$html .= '<thead><tr><th>' . esc_html__( 'Service', 'external-files-in-media-library' ) . '</th><th>' . esc_html__( 'Size', 'external-files-in-media-library' ) . '</th></tr></thead>';
			$html .= '<tbody>';
			foreach ( $sizes as $service_name => $size ) {
				$html .= '<tr><td>' . esc_html( $service_name ) . '</td><td>' . esc_html( (string) size_format( $size, 2 ) ) . '</td></tr>';
			}
			$html .= '</tbody>';
			$html .= '</table>';

			// save this in cache.
			set_transient( self::CACHE_KEY, $html, WEEK_IN_SECONDS );
		}

		// return the resulting HTML code.
		return $html;
	}

	/**
	 * Delete the cached list of file sizes per service.
	 *
	 * @return void
	 */
	public function clear_sizes_per_service_cache(): void {
		delete_transient( self::CACHE_KEY );
	}
}
