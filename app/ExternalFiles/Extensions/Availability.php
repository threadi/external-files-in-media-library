<?php
/**
 * This file controls the option to check the availability of external files.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Extensions;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Select;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\ExternalFiles\Extension_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Schedules\Check_Files;

/**
 * Handler controls how to check the availability of external files.
 */
class Availability extends Extension_Base {
	/**
	 * The internal extension name.
	 *
	 * @var string
	 */
	protected string $name = 'availability';

	/**
	 * Instance of actual object.
	 *
	 * @var Availability|null
	 */
	private static ?Availability $instance = null;

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
	 * @return Availability
	 */
	public static function get_instance(): Availability {
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
		add_action( 'admin_enqueue_scripts', array( $this, 'add_styles_and_js_admin' ) );
		add_action( 'wp_ajax_eml_check_availability', array( $this, 'check_file_availability_via_ajax' ), 10, 0 );

		// use our own hooks.
		add_action( 'efml_show_file_info', array( $this, 'show_availability' ) );
	}

	/**
	 * Return the object title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Check for availability', 'external-files-in-media-library' );
	}

	/**
	 * Add our custom settings for this plugin.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		// get the settings object.
		$settings_obj = Settings::get_instance();

		// get the advanced section.
		$general_tab_main = $settings_obj->get_section( 'settings_section_main' );

		// bail if section could not be loaded.
		if ( ! $general_tab_main ) {
			return;
		}

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_check_interval' );
		$setting->set_section( $general_tab_main );
		$setting->set_type( 'string' );
		$setting->set_default( 'efml_24hourly' );
		$setting->set_help( __( 'Defines the time interval in which files with URLs are automatically checked for its availability.', 'external-files-in-media-library' ) );
		$field = new Select();
		$field->set_title( __( 'Interval for availability check', 'external-files-in-media-library' ) );
		$field->set_description( $setting->get_help() );
		$field->set_options( Helper::get_intervals() );
		$field->set_sanitize_callback( array( $this, 'sanitize_interval_setting' ) );
		$setting->set_save_callback( array( $this, 'update_interval_setting' ) );
		$setting->set_field( $field );
	}

	/**
	 * Add CSS- and JS-files for backend.
	 *
	 * @param string $hook The used hook.
	 *
	 * @return void
	 */
	public function add_styles_and_js_admin( string $hook ): void {
		// bail if page is used where we do not use it.
		if ( 'post.php' !== $hook ) {
			return;
		}

		// backend-JS.
		wp_enqueue_script(
			'eml-availability-admin',
			plugins_url( '/admin/availability.js', EFML_PLUGIN ),
			array( 'jquery' ),
			(string) filemtime( Helper::get_plugin_dir() . '/admin/availability.js' ),
			true
		);

		// add php-vars to our js-script.
		wp_localize_script(
			'eml-availability-admin',
			'efmlJsAvailabilityVars',
			array(
				'ajax_url'                     => admin_url( 'admin-ajax.php' ),
				'availability_nonce'           => wp_create_nonce( 'eml-availability-check-nonce' ),
				'title_availability_refreshed' => __( 'Availability refreshed', 'external-files-in-media-library' ),
				'text_not_available'           => __( 'The file is NOT available.', 'external-files-in-media-library' ),
				'text_is_available'            => '<strong>' . __( 'The file is available.', 'external-files-in-media-library' ) . '</strong> ' . __( 'It is no problem to continue using the URL in your media library.', 'external-files-in-media-library' ),
				'lbl_ok'                       => __( 'OK', 'external-files-in-media-library' ),
			)
		);
	}

	/**
	 * Show availability state on attachment edit page.
	 *
	 * @param File $external_file_obj The external files object.
	 *
	 * @return void
	 */
	public function show_availability( File $external_file_obj ): void {
		// get protocol handler for this URL.
		$protocol_handler = $external_file_obj->get_protocol_handler_obj();

		// bail if no protocol handler could be loaded.
		if ( ! $protocol_handler instanceof Protocol_Base ) {
			return;
		}

		?>
		<li>
			<?php
			if ( $external_file_obj->is_available() ) {
				?>
				<span id="eml_url_file_state"><span class="dashicons dashicons-yes-alt"></span> <?php echo esc_html__( 'File-URL is available.', 'external-files-in-media-library' ); ?></span>
				<?php
			} else {
				$log_url = Helper::get_log_url();
				?>
				<span id="eml_url_file_state"><span class="dashicons dashicons-no-alt"></span>
					<?php
					/* translators: %1$s will be replaced by the URL for the logs */
					echo wp_kses_post( sprintf( __( 'File-URL is NOT available! Check <a href="%1$s">the log</a> for details.', 'external-files-in-media-library' ), esc_url( $log_url ) ) );
					?>
					</span>
				<?php
			}
			if ( $protocol_handler->can_check_availability() ) {
				?>
				<a class="button dashicons dashicons-image-rotate" href="#" id="eml_recheck_availability" title="<?php echo esc_attr__( 'Recheck availability', 'external-files-in-media-library' ); ?>"></a>
				<?php
			}
			?>
		</li>
		<?php
	}

	/**
	 * Check file availability via AJAX request.
	 *
	 * @return       void
	 */
	public function check_file_availability_via_ajax(): void {
		// check nonce.
		check_ajax_referer( 'eml-availability-check-nonce', 'nonce' );

		// create error-result.
		$result = array(
			'state'   => 'error',
			'message' => __( 'No ID given.', 'external-files-in-media-library' ),
		);

		// get ID.
		$attachment_id = absint( filter_input( INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT ) );

		// bail if no file is given.
		if ( 0 === $attachment_id ) {
			// send response as JSON.
			wp_send_json( $result );
		}

		// get the single external file-object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// bail if file is not valid.
		if ( ! $external_file_obj->is_valid() ) {
			// send response as JSON.
			wp_send_json( $result );
		}

		// get protocol handler for this url.
		$protocol_handler = $external_file_obj->get_protocol_handler_obj();

		// bail if protocol handler could not be loaded.
		if ( ! $protocol_handler instanceof Protocol_Base ) {
			// send response as JSON.
			wp_send_json( $result );
		}

		// check and save its availability.
		$external_file_obj->set_availability( $protocol_handler->check_availability( $external_file_obj->get_url( true ) ) );

		// return result depending on availability-value.
		if ( $external_file_obj->is_available() ) {
			$result = array(
				'state'   => 'success',
				'message' => __( 'File-URL is available.', 'external-files-in-media-library' ),
			);

			// send response as JSON.
			wp_send_json( $result );
		}

		// return error if file is not available.
		$result = array(
			'state'   => 'error',
			/* translators: %1$s will be replaced by the URL for the logs */
			'message' => sprintf( __( 'URL-File is NOT available! Check <a href="%1$s">the log</a> for details.', 'external-files-in-media-library' ), Helper::get_log_url() ),
		);

		// send response as JSON.
		wp_send_json( $result );
	}

	/**
	 * Check all external files regarding their availability.
	 *
	 * @return void
	 */
	public function check_files(): void {
		// get all files.
		$files = Files::get_instance()->get_files();

		// bail if no files are found.
		if ( empty( $files ) ) {
			return;
		}

		// loop through the files and check each.
		foreach ( $files as $external_file_obj ) {
			// get the protocol handler for this URL.
			$protocol_handler = $external_file_obj->get_protocol_handler_obj();

			// bail if handler is false.
			if ( ! $protocol_handler ) {
				continue;
			}

			// get and save its availability.
			$external_file_obj->set_availability( $protocol_handler->check_availability( $external_file_obj->get_url() ) );
		}
	}

	/**
	 * Validate the interval setting.
	 *
	 * @param string $value The value to sanitize.
	 *
	 * @return string
	 */
	public function sanitize_interval_setting( string $value ): string {
		// get option.
		$option = str_replace( 'sanitize_option_', '', current_filter() );

		// bail if value is empty.
		if ( empty( $value ) ) {
			add_settings_error( $option, $option, __( 'An interval has to be set.', 'external-files-in-media-library' ) );
			return '';
		}

		// bail if value is 'eml_disable_check'.
		if ( 'eml_disable_check' === $value ) {
			return $value;
		}

		// check if the given interval exists.
		$intervals = wp_get_schedules();
		if ( empty( $intervals[ $value ] ) ) {
			/* translators: %1$s will be replaced by the name of the used interval */
			add_settings_error( $option, $option, sprintf( __( 'The given interval %1$s does not exists.', 'external-files-in-media-library' ), esc_html( $value ) ) );
		}

		// return the value.
		return $value;
	}

	/**
	 * Update the schedule if interval has been changed.
	 *
	 * @param string|null $value The given value for the interval.
	 *
	 * @return string
	 */
	public function update_interval_setting( string|null $value ): string {
		// check if value is null.
		if ( is_null( $value ) ) {
			$value = '';
		}

		// get check files-schedule-object.
		$check_files_schedule = new Check_Files();

		// if new value is 'eml_disable_check' remove the schedule.
		if ( 'eml_disable_check' === $value ) {
			$check_files_schedule->delete();
		} else {
			// set the new interval.
			$check_files_schedule->set_interval( $value );

			// reset the schedule.
			$check_files_schedule->reset();
		}

		// return the new value to save it via WP.
		return $value;
	}
}
