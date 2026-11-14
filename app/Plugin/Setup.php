<?php
/**
 * File to handle the setup of this plugin, which is shown as "Quickstart".
 *
 * The setup guides users through the typical tasks of this plugin: they choose a goal (e.g. "Offload the media
 * library"), a service, enter its access data and choose some options. The setup then creates the external source
 * and configures everything for this goal.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Crypt;
use easyDirectoryListingForWordPress\Directory_Listing_Base;
use easyDirectoryListingForWordPress\Directory_Listings;
use ExternalFilesInMediaLibrary\ExternalFiles\Abilities;
use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Export_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\ExportDialog;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use ExternalFilesInMediaLibrary\Services\Services;
use Throwable;
use WP_Error;
use WP_REST_Request;

/**
 * Object to handle the setup.
 */
class Setup {
	/**
	 * The name of the setup configuration.
	 */
	public const NAME = 'efml-setup';

	/**
	 * The name of the field with the chosen goal.
	 */
	public const FIELD_GOAL = 'efml_setup_goal';

	/**
	 * The name of the field with the chosen service.
	 */
	public const FIELD_SERVICE = 'efml_setup_service';

	/**
	 * The name of the field with the local path.
	 */
	public const FIELD_PATH = 'efml_setup_path';

	/**
	 * The name of the field with the URL of exported files.
	 */
	public const FIELD_EXPORT_URL = 'efml_setup_export_url';

	/**
	 * The prefix of the fields with the access data of a service.
	 */
	public const FIELD_PREFIX = 'efml_setup_field_';

	/**
	 * The prefix of the fields with the options.
	 */
	public const OPTION_PREFIX = 'efml_setup_option_';

	/**
	 * The name of the URL parameter to show the setup.
	 */
	public const URL_PARAMETER = 'efml_setup';

	/**
	 * The name of the URL parameter to continue the setup (e.g. after connecting a service via OAuth).
	 */
	public const CONTINUE_PARAMETER = 'efml_setup_continue';

	/**
	 * The name of the user meta to return to the setup after connecting a service via OAuth.
	 */
	public const OAUTH_RETURN_META = 'efml_setup_oauth_return';

	/**
	 * The name of the user meta with the result of the last check of the access data.
	 */
	private const CHECK_META = 'efml_setup_check';

	/**
	 * The name of the user meta with the URL to forward to after the setup has been completed.
	 */
	private const FORWARD_META = 'efml_setup_forward';

	/**
	 * How long the setup returns to itself after connecting a service via OAuth (in seconds).
	 */
	private const OAUTH_RETURN_TIMEOUT = 30 * MINUTE_IN_SECONDS;

	/**
	 * Instance of actual object.
	 *
	 * @var ?Setup
	 */
	private static ?Setup $instance = null;

	/**
	 * The steps of the setup.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private array $setup = array();

	/**
	 * The messages of the actual process.
	 *
	 * @var array<int,array{text: string, success: bool}>
	 */
	private array $process_messages = array();

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
	 * @return Setup
	 */
	public static function get_instance(): Setup {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the setup.
	 *
	 * @return void
	 */
	public function init(): void {
		// bail if the setup library is not available.
		if ( ! $this->is_library_available() ) {
			return;
		}

		// use hooks.
		add_action( 'init', array( $this, 'maybe_clear_answers' ), 5 );
		add_action( 'init', array( $this, 'init_setup' ) );
		add_filter( 'rest_request_before_callbacks', array( $this, 'prepare_check' ), 10, 3 );
		add_filter( 'esfw_save_password_value', array( $this, 'encrypt_password' ), 10, 3 );
		add_filter( 'esfw_get_password_value', array( $this, 'decrypt_password' ), 10, 3 );
		add_filter( 'efml_command_palette_pages', array( $this, 'add_command_palette_page' ) );
	}

	/**
	 * Return whether the setup library is available in the needed version.
	 *
	 * @return bool
	 */
	private function is_library_available(): bool {
		// the class could be loaded in an older version by another plugin, so check the method this setup needs.
		return class_exists( '\easySetupForWordPress\Setup' ) && in_array( 'is_password_field', get_class_methods( '\easySetupForWordPress\Setup' ), true );
	}

	/**
	 * Initialize the setup library, if this request is part of the setup.
	 *
	 * @return void
	 */
	public function init_setup(): void {
		// bail if this request is not part of the setup.
		if ( ! $this->is_setup_request() ) {
			return;
		}

		// get the setup object.
		$setup_obj = \easySetupForWordPress\Setup::get_instance();

		// store the values of the setup per user.
		$setup_obj->set_user_values( true );

		// initialize the setup object.
		$setup_obj->init();

		// configure it.
		$setup_obj->set_url( Helper::get_plugin_url() );
		$setup_obj->set_path( Helper::get_plugin_dir() );
		$setup_obj->set_texts(
			array(
				'title_error' => __( 'Error', 'external-files-in-media-library' ),
				'txt_error_1' => __( 'The following error occurred:', 'external-files-in-media-library' ),
				/* translators: %1$s will be replaced with the URL of the plugin-forum on wp.org */
				'txt_error_2' => sprintf( __( '<strong>If reason is unclear</strong> please contact our <a href="%1$s" target="_blank">support-forum (opens a new window)</a> with as much detail as possible.', 'external-files-in-media-library' ), esc_url( Helper::get_plugin_support_url() ) ),
			)
		);
		$setup_obj->set_display_hook( 'media_page_efml_local_directories' );

		// set the configuration.
		$setup_obj->set_config( $this->get_config() );

		// use hooks.
		add_action( 'esfw_process', array( $this, 'run_process' ) );
		add_action( 'esfw_process', array( $this, 'show_process_end' ), PHP_INT_MAX );
		add_action( 'esfw_set_completed', array( $this, 'set_completed' ) );
	}

	/**
	 * Return whether the actual request is part of the setup.
	 *
	 * @return bool
	 */
	public function is_setup_request(): bool {
		// the REST requests the setup sends while it is running (with and without pretty permalinks).
		if ( isset( $_SERVER['REQUEST_URI'] ) && str_contains( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), 'easy-setup-for-wordpress' ) ) {
			return true;
		}

		// the page the setup is shown on.
		return $this->is_setup_page();
	}

	/**
	 * Return whether the actual request shows the page of the setup.
	 *
	 * @return bool
	 */
	public function is_setup_page(): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only routing.
		return is_admin()
			&& isset( $_GET['page'], $_GET[ self::URL_PARAMETER ] )
			&& 'efml_local_directories' === sanitize_text_field( wp_unslash( $_GET['page'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Return whether the actual user could use the setup.
	 *
	 * @return bool
	 */
	public function is_allowed(): bool {
		return $this->is_library_available() && current_user_can( 'manage_options' );
	}

	/**
	 * Return the URL of the setup.
	 *
	 * @param bool $resume True to continue the actual setup instead of starting a new one.
	 *
	 * @return string
	 */
	public function get_url( bool $resume = false ): string {
		// collect the parameters.
		$parameters = array(
			self::URL_PARAMETER => 1,
		);

		// add the marker to continue.
		if ( $resume ) {
			$parameters[ self::CONTINUE_PARAMETER ] = 1;
		}

		// return the URL.
		return add_query_arg( $parameters, Directory_Listing::get_instance()->get_view_directory_url( false ) );
	}

	/**
	 * Show the setup.
	 *
	 * @return void
	 */
	public function display(): void {
		// bail if the user is not allowed to use the setup.
		if ( ! $this->is_allowed() ) {
			return;
		}

		// output the setup.
		echo '<div class="wrap efml-setup">' . wp_kses_post( \easySetupForWordPress\Setup::get_instance()->display( self::NAME ) ) . '</div>';
	}

	/**
	 * Return the configuration for the setup.
	 *
	 * @return array<string,mixed>
	 */
	private function get_config(): array {
		// collect the configuration.
		$config = array(
			'name'                  => self::NAME,
			'title'                 => __( 'Quickstart', 'external-files-in-media-library' ),
			'steps'                 => $this->get_setup(),
			'update_fields'         => true,
			'user_values'           => true,
			'capability'            => 'manage_options',
			'back_button_label'     => __( 'Back', 'external-files-in-media-library' ) . '<span class="dashicons dashicons-undo"></span>',
			'continue_button_label' => __( 'Continue', 'external-files-in-media-library' ) . '<span class="dashicons dashicons-controls-play"></span>',
			'finish_button_label'   => __( 'Completed', 'external-files-in-media-library' ) . '<span class="dashicons dashicons-saved"></span>',
			'skip_button_label'     => __( 'Cancel', 'external-files-in-media-library' ) . '<span class="dashicons dashicons-undo"></span>',
			'skip_url'              => Directory_Listing::get_instance()->get_view_directory_url( false ),
			'error_label'           => __( 'An error occurred. Received an incorrect response from the server. Please check your permalink settings.', 'external-files-in-media-library' ),
		);

		/**
		 * Filter the configuration of the setup.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param array<string,mixed> $config The configuration.
		 */
		return apply_filters( 'efml_setup_config', $config );
	}

	/**
	 * Return the steps of the setup.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_setup(): array {
		// build the steps, if they are not set.
		if ( empty( $this->setup ) ) {
			$this->set_steps();
		}

		// return the steps.
		return $this->setup;
	}

	/**
	 * Build the steps of the setup depending on the choices made so far.
	 *
	 * @return void
	 */
	private function set_steps(): void {
		// get the chosen goal.
		$goal = $this->get_chosen_goal();

		// collect the steps.
		$steps   = array();
		$steps[] = $this->get_goal_step();

		// add the step to choose a service, if the goal does not define it.
		if ( is_null( $goal ) || '' === $goal['service'] ) {
			$steps[] = $this->get_service_step();
		}

		// add the steps for the access data and the options.
		$steps[] = $this->get_access_step();
		$steps[] = $this->get_options_step();

		// add the process.
		$steps[] = array(
			'runSetup' => array(
				'type'  => 'ProgressBar',
				'label' => __( 'The quickstart is configuring your external source', 'external-files-in-media-library' ),
			),
		);

		// number the steps from 1.
		$this->setup = (array) array_combine( range( 1, count( $steps ) ), $steps );
	}

	/**
	 * Return the possible goals.
	 *
	 * Each goal is defined by:
	 * - label: the title.
	 * - description: the description.
	 * - icon: the icon as HTML.
	 * - features: the features a service must support (see Service_Base::get_features()).
	 * - service: the name of the service to use (empty to let the user choose one).
	 * - options: the options to offer (see @get_option_definitions()).
	 * - defaults: the default values of the options.
	 * - action: what should happen after the external source has been created ("export", "sync", "import" or empty).
	 *
	 * @return array<string,array{label: string, description: string, icon: string, features: array<int,string>, service: string, options: array<int,string>, defaults: array<string,int|string>, action: string}>
	 */
	public function get_goals(): array {
		// define the goals.
		$goals = array(
			'offload'   => array(
				'label'       => __( 'Offload your media library', 'external-files-in-media-library' ),
				'description' => __( 'New files in your media library are saved on an external storage and delivered from there.', 'external-files-in-media-library' ),
				'icon'        => Helper::get_icon( 'core-admin/upload', 'dashicons-upload', '', 32 ),
				'features'    => array( 'export' ),
				'service'     => '',
				'options'     => array( 'export', 'export_main', 'export_folders', 'export_private' ),
				'defaults'    => array(
					'export'         => 1,
					'export_main'    => 1,
					'export_folders' => 1,
					'export_private' => 0,
				),
				'action'      => 'export',
			),
			'downloads' => array(
				'label'       => __( 'Protected downloads', 'external-files-in-media-library' ),
				'description' => __( 'Save files privately on an external storage. Your website delivers them, e.g. as downloads of your WooCommerce products.', 'external-files-in-media-library' ),
				'icon'        => Helper::get_icon( 'core-admin/shield', 'dashicons-shield', '', 32 ),
				'features'    => array( 'export', 'private' ),
				'service'     => '',
				'options'     => array( 'export', 'export_main', 'export_folders', 'export_private', 'woocommerce' ),
				'defaults'    => array(
					'export'         => 1,
					'export_main'    => 1,
					'export_folders' => 1,
					'export_private' => 1,
					'woocommerce'    => 1,
				),
				'action'      => 'export',
			),
			'sync'      => array(
				'label'       => __( 'Embed and synchronize external files', 'external-files-in-media-library' ),
				'description' => __( 'Use files of an external storage in your media library and keep them up to date automatically.', 'external-files-in-media-library' ),
				'icon'        => Helper::get_icon( 'core-admin/update', 'dashicons-update', '', 32 ),
				'features'    => array( 'sync' ),
				'service'     => '',
				'options'     => array( 'sync', 'sync_interval' ),
				'defaults'    => array(
					'sync'          => 1,
					'sync_interval' => $this->get_default_interval(),
				),
				'action'      => 'sync',
			),
			'local'     => array(
				'label'       => __( 'Import local files', 'external-files-in-media-library' ),
				'description' => __( 'Import files from a directory of your hosting into your media library.', 'external-files-in-media-library' ),
				'icon'        => '<span class="efml-setup-service-icon efml-local"><span></span></span>',
				'features'    => array(),
				'service'     => 'local',
				'options'     => array( 'real_import' ),
				'defaults'    => array(
					'real_import' => 0,
				),
				'action'      => 'import',
			),
			'ftp'       => array(
				'label'       => __( 'Synchronize files from FTP', 'external-files-in-media-library' ),
				'description' => __( 'Use the files of a directory on an FTP server in your media library and keep them up to date automatically.', 'external-files-in-media-library' ),
				'icon'        => '<span class="efml-setup-service-icon efml-ftp"><span></span></span>',
				'features'    => array( 'sync' ),
				'service'     => 'ftp',
				'options'     => array( 'sync', 'sync_interval' ),
				'defaults'    => array(
					'sync'          => 1,
					'sync_interval' => $this->get_default_interval(),
				),
				'action'      => 'sync',
			),
		);

		/**
		 * Filter the goals of the setup.
		 *
		 * Each goal is an array with the keys label, description, icon, features, service, options, defaults
		 * and action. See Setup::get_goals() for details.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param array<string,array<string,mixed>> $goals List of goals.
		 */
		$goals = apply_filters( 'efml_setup_goals', $goals );

		// complete the goals with default values.
		$list = array();
		foreach ( $goals as $name => $goal ) {
			// bail if goal is not an array or has no label.
			if ( ! is_array( $goal ) || empty( $goal['label'] ) ) {
				continue;
			}

			// add the goal.
			$list[ (string) $name ] = array(
				'label'       => (string) $goal['label'],
				'description' => isset( $goal['description'] ) ? (string) $goal['description'] : '',
				'icon'        => isset( $goal['icon'] ) ? (string) $goal['icon'] : '',
				'features'    => isset( $goal['features'] ) && is_array( $goal['features'] ) ? array_values( array_map( 'strval', $goal['features'] ) ) : array(),
				'service'     => isset( $goal['service'] ) ? (string) $goal['service'] : '',
				'options'     => isset( $goal['options'] ) && is_array( $goal['options'] ) ? array_values( array_map( 'strval', $goal['options'] ) ) : array(),
				'defaults'    => isset( $goal['defaults'] ) && is_array( $goal['defaults'] ) ? $goal['defaults'] : array(),
				'action'      => isset( $goal['action'] ) ? (string) $goal['action'] : '',
			);
		}

		// return the resulting list.
		return $list;
	}

	/**
	 * Return the definitions of the options the goals could offer.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_option_definitions(): array {
		// collect the intervals for the synchronization.
		$intervals = array();
		foreach ( Intervals::get_instance()->get_intervals_for_settings() as $name => $label ) {
			// bail if this is the entry to disable a check, which is no interval.
			if ( 'eml_disable_check' === $name ) {
				continue;
			}

			$intervals[] = array(
				'label' => $label,
				'value' => $name,
			);
		}

		// define the options.
		$options = array(
			'export'         => array(
				'type'  => 'CheckboxControl',
				'label' => __( 'Export new files in the media library to this external source', 'external-files-in-media-library' ),
				'help'  => __( 'This enables the export in the plugin settings.', 'external-files-in-media-library' ),
			),
			'export_main'    => array(
				'type'  => 'CheckboxControl',
				'label' => __( 'Use this external source as the main export', 'external-files-in-media-library' ),
				'help'  => __( 'New files in the media library are assigned to it.', 'external-files-in-media-library' ),
			),
			'export_folders' => array(
				'type'  => 'CheckboxControl',
				'label' => __( 'Save exported files in folders by year and month', 'external-files-in-media-library' ),
				'help'  => __( 'Like WordPress does it in your uploads directory.', 'external-files-in-media-library' ),
			),
			'export_private' => array(
				'type'  => 'CheckboxControl',
				'label' => __( 'Save files privately', 'external-files-in-media-library' ),
				'help'  => __( 'Files are not public available on this external source. Your website delivers them via its own URL.', 'external-files-in-media-library' ),
			),
			'woocommerce'    => array(
				'type'  => 'CheckboxControl',
				'label' => __( 'Use external files as downloads of WooCommerce products', 'external-files-in-media-library' ),
				'help'  => __( 'Private files are delivered via links with limited validity.', 'external-files-in-media-library' ),
			),
			'sync'           => array(
				'type'  => 'CheckboxControl',
				'label' => __( 'Synchronize this external source automatically', 'external-files-in-media-library' ),
				'help'  => __( 'New files are added to your media library, deleted files are removed from it. The first synchronization starts at the end of this quickstart.', 'external-files-in-media-library' ),
			),
			'sync_interval'  => array(
				'type'    => 'SelectControl',
				'label'   => __( 'Interval of the synchronization', 'external-files-in-media-library' ),
				'options' => $intervals,
			),
			'real_import'    => array(
				'type'  => 'CheckboxControl',
				'label' => __( 'Copy the files into your media library', 'external-files-in-media-library' ),
				'help'  => __( 'Otherwise the files stay in their directory and are only linked in your media library.', 'external-files-in-media-library' ),
			),
		);

		/**
		 * Filter the options the goals of the setup could offer.
		 *
		 * Use the action "efml_setup_apply_option" to save the chosen value of an own option.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param array<string,array<string,mixed>> $options List of options with their field configuration.
		 */
		return apply_filters( 'efml_setup_option_definitions', $options );
	}

	/**
	 * Return whether an option could be offered for the given service.
	 *
	 * @param string            $option      The name of the option.
	 * @param Service_Base|null $service_obj The service object.
	 *
	 * @return bool
	 */
	private function is_option_available( string $option, ?Service_Base $service_obj ): bool {
		// get the export object of the service.
		$export_obj = $service_obj instanceof Service_Base ? $service_obj->get_export_object() : false;

		// check the option.
		$available = match ( $option ) {
			'export', 'export_main' => $export_obj instanceof Export_Base && current_user_can( 'efml_cap_tools_export' ),
			'export_folders' => $export_obj instanceof Export_Base && $export_obj->supports_folders() && 1 === absint( get_option( 'uploads_use_yearmonth_folders' ) ),
			'export_private' => $export_obj instanceof Export_Base && $export_obj->supports_private_files(),
			'woocommerce' => function_exists( 'WC' ),
			'sync', 'sync_interval' => $service_obj instanceof Service_Base && ! $service_obj->is_sync_disabled() && current_user_can( 'efml_cap_tools_sync' ),
			'real_import' => current_user_can( 'efml_cap_tools_real_import' ),
			default => true,
		};

		/**
		 * Filter whether an option of the setup could be offered for the given service.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param bool              $available   True if the option could be offered.
		 * @param string            $option      The name of the option.
		 * @param Service_Base|null $service_obj The service object.
		 */
		return apply_filters( 'efml_setup_option_available', $available, $option, $service_obj );
	}

	/**
	 * Return the default interval for synchronizations.
	 *
	 * @return string
	 */
	private function get_default_interval(): string {
		// get the interval from the settings.
		$interval = (string) get_option( 'eml_sync_interval' );

		// use hourly, if none is set.
		if ( '' === $interval ) {
			return 'efml_hourly';
		}

		// return the interval.
		return $interval;
	}

	/**
	 * Return the fields of the step to choose a goal.
	 *
	 * @return array<string,mixed>
	 */
	private function get_goal_step(): array {
		// collect the goals as options.
		$options = array();
		foreach ( $this->get_goals() as $name => $goal ) {
			$options[] = array(
				'label'       => $goal['label'],
				'value'       => $name,
				'description' => $goal['description'],
				'icon'        => $goal['icon'],
			);
		}

		// return the fields.
		return array(
			self::FIELD_GOAL => array(
				'type'     => 'CardControl',
				'label'    => __( 'What would you like to do?', 'external-files-in-media-library' ),
				'options'  => $options,
				'required' => true,
			),
			'help'           => array(
				'type' => 'Text',
				/* translators: %1$s will be replaced by a URL. */
				'text' => '<p><span class="dashicons dashicons-controls-skipforward"></span> ' . sprintf( __( '<strong>Something else in mind?</strong> Choose a service <a href="%1$s">from the list</a>. You can change everything the quickstart configures at any time.', 'external-files-in-media-library' ), esc_url( Directory_Listing::get_instance()->get_view_directory_url( false ) ) ) . '</p>',
			),
		);
	}

	/**
	 * Return the fields of the step to choose a service.
	 *
	 * @return array<string,mixed>
	 */
	private function get_service_step(): array {
		// get the chosen goal.
		$goal = $this->get_chosen_goal();

		// show a hint if no goal has been chosen yet.
		if ( is_null( $goal ) ) {
			return array(
				'help' => array(
					'type' => 'Text',
					'text' => '<p>' . esc_html__( 'Please choose what you would like to do on the first page of this quickstart.', 'external-files-in-media-library' ) . '</p>',
				),
			);
		}

		// collect the services as options.
		$options = array();
		foreach ( $this->get_services_for_goal( $goal ) as $listing_obj ) {
			// get the service object.
			$service_obj = Services::get_instance()->get_service_for_listing( $listing_obj );

			// add the service, with a hint if it must be connected first.
			$options[] = array(
				'label'       => wp_strip_all_tags( $listing_obj->get_label() ),
				'value'       => $listing_obj->get_name(),
				'description' => $service_obj instanceof Service_Base && ! empty( $service_obj->get_setup_connect_dialog() ) ? __( 'You will connect it in the next step.', 'external-files-in-media-library' ) : '',
				'icon'        => $this->get_service_icon( $listing_obj->get_name() ),
			);
		}

		// collect the fields.
		$fields = array(
			'chosen' => $this->get_headline( $goal['label'], $goal['icon'] ),
		);

		// show a hint if no service could be used for this goal.
		if ( empty( $options ) ) {
			$fields['help'] = array(
				'type' => 'Text',
				/* translators: %1$s will be replaced by a URL. */
				'text' => '<p>' . sprintf( __( 'No service, which you could use for this, is available at the moment. Some services are provided by extensions, which you can find <a href="%1$s">in the list of services</a>.', 'external-files-in-media-library' ), esc_url( Directory_Listing::get_instance()->get_view_directory_url( false ) ) ) . '</p>',
			);

			// add an empty card list, which prevents to continue.
			$fields[ self::FIELD_SERVICE ] = array(
				'type'     => 'CardControl',
				'options'  => array(),
				'required' => true,
			);

			return $fields;
		}

		// add the list of services.
		$fields[ self::FIELD_SERVICE ] = array(
			'type'     => 'CardControl',
			'label'    => __( 'Which service would you like to use?', 'external-files-in-media-library' ),
			'options'  => $options,
			'required' => true,
		);

		// add a hint for extensions.
		$fields['help'] = array(
			'type' => 'Text',
			/* translators: %1$s will be replaced by a URL. */
			'text' => '<p><span class="dashicons dashicons-admin-plugins"></span> ' . sprintf( __( '<strong>Missing your service?</strong> Further services are provided by extensions, which you can find <a href="%1$s">in the list of services</a>.', 'external-files-in-media-library' ), esc_url( Directory_Listing::get_instance()->get_view_directory_url( false ) ) ) . '</p>',
		);

		// return the fields.
		return $fields;
	}

	/**
	 * Return the services, which could be used for the given goal.
	 *
	 * @param array<string,mixed> $goal The goal.
	 *
	 * @return array<int,Directory_Listing_Base>
	 */
	public function get_services_for_goal( array $goal ): array {
		// collect the services.
		$list = array();
		foreach ( Directory_Listings::get_instance()->get_directory_listings_objects() as $listing_obj ) {
			// bail if this is the import of single URLs.
			if ( $listing_obj instanceof Import ) {
				continue;
			}

			// bail if a service is defined by the goal and this is not the service.
			if ( ! empty( $goal['service'] ) && $goal['service'] !== $listing_obj->get_name() ) {
				continue;
			}

			// bail if the user is not allowed to use this service.
			if ( ! current_user_can( $listing_obj->get_permission_name() ) ) {
				continue;
			}

			// bail if the service does not support all features of the goal.
			$features = Services::get_instance()->get_supported_features( $listing_obj );
			if ( ! empty( $goal['features'] ) && is_array( $goal['features'] ) && array_diff( $goal['features'], $features ) ) {
				continue;
			}

			// bail if the service could not be used in the setup.
			if ( ! $this->is_service_usable( $listing_obj ) ) {
				continue;
			}

			// add the service to the list.
			$list[] = $listing_obj;
		}

		// return the resulting list.
		return $list;
	}

	/**
	 * Return whether the given service could be used in the setup.
	 *
	 * @param Directory_Listing_Base $listing_obj The service.
	 *
	 * @return bool
	 */
	private function is_service_usable( Directory_Listing_Base $listing_obj ): bool {
		// get the service object.
		$service_obj = Services::get_instance()->get_service_for_listing( $listing_obj );

		// check whether it is disabled, but could be connected in the setup.
		if ( $listing_obj->is_disabled() && ( ! $service_obj instanceof Service_Base || empty( $service_obj->get_setup_connect_dialog() ) ) ) {
			return false;
		}

		// check whether all fields of the service could be shown in the setup.
		foreach ( $listing_obj->get_fields() as $field ) {
			if ( '' === $this->get_control_type( $field ) ) {
				return false;
			}
		}

		// the service could be used.
		return true;
	}

	/**
	 * Return the type of the setup control for the given field of a service.
	 *
	 * Returns an empty string, if the field could not be shown in the setup.
	 *
	 * @param array<string,mixed> $field The field.
	 *
	 * @return string
	 */
	private function get_control_type( array $field ): string {
		// get the type of the field.
		$type = isset( $field['type'] ) ? strtolower( (string) $field['type'] ) : 'text';

		// return the matching control.
		return match ( $type ) {
			'text', 'url', 'numeric', 'email' => 'TextControl',
			'password' => 'PasswordControl',
			'select' => 'SelectControl',
			'checkbox' => 'CheckboxControl',
			'hidden' => 'hidden',
			default => '',
		};
	}

	/**
	 * Return the fields of the step to enter the access data.
	 *
	 * @return array<string,mixed>
	 */
	private function get_access_step(): array {
		// get the chosen goal and service.
		$goal        = $this->get_chosen_goal();
		$listing_obj = $this->get_chosen_service();

		// show a hint if nothing has been chosen yet.
		if ( is_null( $goal ) || ! $listing_obj instanceof Directory_Listing_Base ) {
			return array(
				'help' => array(
					'type' => 'Text',
					'text' => '<p>' . esc_html__( 'Please choose what you would like to do and a service first.', 'external-files-in-media-library' ) . '</p>',
				),
			);
		}

		// collect the fields.
		$fields = array(
			'chosen' => $this->get_chosen_headline( $goal, $listing_obj ),
		);

		// get the service object.
		$service_obj = Services::get_instance()->get_service_for_listing( $listing_obj );

		// show the button to connect the service, if it is not connected.
		$connect_dialog = $service_obj instanceof Service_Base ? $service_obj->get_setup_connect_dialog() : array();
		if ( ! empty( $connect_dialog ) ) {
			// return to the setup after connecting.
			update_user_meta(
				get_current_user_id(),
				self::OAUTH_RETURN_META,
				array(
					'service' => $service_obj->get_name(),
					'time'    => time(),
				)
			);

			$fields['help']    = array(
				'type' => 'Text',
				/* translators: %1$s will be replaced by the name of a service. */
				'text' => '<p>' . sprintf( esc_html__( 'Connect your website with %1$s first. You will return to this quickstart afterwards.', 'external-files-in-media-library' ), esc_html( wp_strip_all_tags( $listing_obj->get_label() ) ) ) . '</p>',
			);
			$fields['connect'] = array(
				'type'    => 'ButtonControl',
				/* translators: %1$s will be replaced by the name of a service. */
				'label'   => sprintf( __( 'Connect %1$s', 'external-files-in-media-library' ), wp_strip_all_tags( $listing_obj->get_label() ) ),
				'onclick' => '() => efml_create_dialog( { detail: ' . Helper::get_json( $connect_dialog ) . ' } )',
			);

			// add an empty card list, which prevents to continue until the service is connected.
			$fields['efml_setup_connected'] = array(
				'type'     => 'CardControl',
				'options'  => array(),
				'required' => true,
			);

			return $fields;
		}

		// add the field for the local path.
		if ( 'local' === $listing_obj->get_name() ) {
			$fields[ self::FIELD_PATH ] = array(
				'type'        => 'TextControl',
				'label'       => __( 'Directory in your hosting', 'external-files-in-media-library' ),
				'help'        => __( 'Enter the absolute path of the directory with the files you want to import.', 'external-files-in-media-library' ),
				'placeholder' => untrailingslashit( ABSPATH ),
				'default'     => untrailingslashit( ABSPATH ),
				'required'    => true,
			);

			return $fields;
		}

		// collect the hints for values, which are already set.
		$hints = array();

		// add the fields of the service.
		$service_fields = $listing_obj->get_fields();
		foreach ( $service_fields as $name => $field ) {
			// get the control.
			$control = $this->get_control_type( $field );

			// bail if this field is not visible.
			if ( 'hidden' === $control || '' === $control ) {
				continue;
			}

			// get the label.
			$label = isset( $field['label'] ) ? wp_strip_all_tags( (string) $field['label'] ) : (string) $name;

			// show a hint for values defined via constants.
			if ( ! empty( $field['constant'] ) && Service_Base::CONNECTION_FIELD !== $name ) {
				/* translators: %1$s will be replaced by the label of a field, %2$s by the name of a constant. */
				$hints[] = sprintf( __( '%1$s: defined via %2$s', 'external-files-in-media-library' ), esc_html( $label ), '<code>' . esc_html( (string) $field['constant'] ) . '</code>' );
				continue;
			}

			// show a hint for values, which are already saved (e.g. in the plugin settings).
			if ( ! empty( $field['readonly'] ) && ! empty( $field['value'] ) ) {
				/* translators: %1$s will be replaced by the label of a field. */
				$hints[] = sprintf( __( '%1$s: already saved in the settings', 'external-files-in-media-library' ), esc_html( $label ) );
				continue;
			}

			// create the control.
			$control_field = array(
				'type'        => $control,
				'label'       => $label,
				'help'        => isset( $field['description'] ) ? wp_kses_post( (string) $field['description'] ) : '',
				'placeholder' => isset( $field['placeholder'] ) ? wp_strip_all_tags( (string) $field['placeholder'] ) : '',
				'required'    => empty( $field['not_required'] ) && 'CheckboxControl' !== $control,
			);

			// add the options of a select.
			if ( 'SelectControl' === $control ) {
				$control_field['options'] = isset( $field['options'] ) && is_array( $field['options'] ) ? array_values( $field['options'] ) : array();
			}

			// use the value of the field as default, but never for passwords.
			if ( 'PasswordControl' !== $control && isset( $field['value'] ) && is_scalar( $field['value'] ) && '' !== (string) $field['value'] ) {
				$control_field['default'] = 'CheckboxControl' === $control ? absint( $field['value'] ) : (string) $field['value'];
			} elseif ( 'SelectControl' === $control && ! empty( $control_field['options'][0]['value'] ) ) {
				$control_field['default'] = (string) $control_field['options'][0]['value'];
			}

			// add the field.
			$fields[ $this->get_field_name( $listing_obj->get_name(), (string) $name ) ] = $control_field;
		}

		// add the hints.
		if ( ! empty( $hints ) ) {
			$fields['hints'] = array(
				'type' => 'Text',
				'text' => '<p>' . esc_html__( 'These values are already set:', 'external-files-in-media-library' ) . '</p><ul><li>' . implode( '</li><li>', $hints ) . '</li></ul>',
			);
		}

		// show a hint if nothing must be entered.
		if ( 1 === count( $fields ) ) {
			$fields['help'] = array(
				'type' => 'Text',
				/* translators: %1$s will be replaced by the name of a service. */
				'text' => '<p>' . sprintf( esc_html__( '%1$s needs no further details. Continue to check the connection.', 'external-files-in-media-library' ), esc_html( wp_strip_all_tags( $listing_obj->get_label() ) ) ) . '</p>',
			);
		}

		// return the fields.
		return $fields;
	}

	/**
	 * Return the fields of the step with the result of the check and the options.
	 *
	 * @return array<string,mixed>
	 */
	private function get_options_step(): array {
		// get the chosen goal and service.
		$goal        = $this->get_chosen_goal();
		$listing_obj = $this->get_chosen_service();

		// show a hint if nothing has been chosen yet.
		if ( is_null( $goal ) || ! $listing_obj instanceof Directory_Listing_Base ) {
			return array(
				'help' => array(
					'type' => 'Text',
					'text' => '<p>' . esc_html__( 'Please choose what you would like to do and a service first.', 'external-files-in-media-library' ) . '</p>',
				),
			);
		}

		// collect the fields.
		$fields = array(
			'chosen' => $this->get_chosen_headline( $goal, $listing_obj ),
		);

		// get the result of the check of the access data.
		$check = $this->get_check_result();

		// show the result, if the check failed.
		if ( ! $check['success'] ) {
			$fields['check'] = array(
				'type' => 'Text',
				'text' => '<div class="efml-setup-check efml-setup-check-failed"><p><span class="dashicons dashicons-dismiss"></span> <strong>' . esc_html__( 'The connection could not be established.', 'external-files-in-media-library' ) . '</strong></p>' . ( '' !== $check['message'] ? '<p>' . esc_html( $check['message'] ) . '</p>' : '' ) . '<p>' . esc_html__( 'Go back to check the details you entered.', 'external-files-in-media-library' ) . '</p></div>',
			);

			// add an empty card list, which prevents to continue.
			$fields['efml_setup_check_failed'] = array(
				'type'     => 'CardControl',
				'options'  => array(),
				'required' => true,
			);

			return $fields;
		}

		// show the successful check.
		$fields['check'] = array(
			'type' => 'Text',
			'text' => '<div class="efml-setup-check efml-setup-check-success"><p><span class="dashicons dashicons-yes-alt"></span> <strong>' . esc_html__( 'The connection has been established.', 'external-files-in-media-library' ) . '</strong></p></div>',
		);

		// get the service object.
		$service_obj = Services::get_instance()->get_service_for_listing( $listing_obj );

		// get the definitions of the options.
		$definitions = $this->get_option_definitions();

		// add the options of the goal.
		$has_options = false;
		foreach ( $goal['options'] as $option ) {
			// bail if option is not defined or not available for this service.
			if ( empty( $definitions[ $option ] ) || ! $this->is_option_available( $option, $service_obj instanceof Service_Base ? $service_obj : null ) ) {
				continue;
			}

			// get the field.
			$field = $definitions[ $option ];

			// add the default value of the goal.
			if ( isset( $goal['defaults'][ $option ] ) ) {
				$field['default'] = $goal['defaults'][ $option ];
			}

			// add the field.
			$fields[ self::OPTION_PREFIX . $option ] = $field;
			$has_options                             = true;
		}

		// add the field for the URL of exported files, if the service requires it.
		$export_obj = $service_obj instanceof Service_Base ? $service_obj->get_export_object() : false;
		if ( 'export' === $goal['action'] && $export_obj instanceof Export_Base && $export_obj->is_url_required() ) {
			$fields[ self::FIELD_EXPORT_URL ] = array(
				'type'        => 'TextControl',
				'label'       => __( 'URL of the exported files', 'external-files-in-media-library' ),
				'help'        => __( 'This must be the URL where files uploaded to this external source are available.', 'external-files-in-media-library' ),
				'placeholder' => 'https://example.com',
				'required'    => true,
			);
			$has_options                      = true;
		}

		// show a hint if there are no options.
		if ( ! $has_options ) {
			$fields['help'] = array(
				'type' => 'Text',
				'text' => '<p>' . esc_html__( 'Continue to save your external source.', 'external-files-in-media-library' ) . '</p>',
			);
		}

		// return the fields.
		return $fields;
	}

	/**
	 * Return the headline with the chosen goal and service.
	 *
	 * @param array<string,mixed>    $goal        The goal.
	 * @param Directory_Listing_Base $listing_obj The service.
	 *
	 * @return array<string,string>
	 */
	private function get_chosen_headline( array $goal, Directory_Listing_Base $listing_obj ): array {
		// get the label of the goal.
		$label = isset( $goal['label'] ) && is_string( $goal['label'] ) ? $goal['label'] : '';

		// add the service, if the user has chosen it.
		if ( empty( $goal['service'] ) ) {
			$label .= ' – ' . wp_strip_all_tags( $listing_obj->get_label() );
		}

		// return the headline.
		return $this->get_headline( $label, $this->get_service_icon( $listing_obj->get_name() ) );
	}

	/**
	 * Return a headline showing what has been chosen.
	 *
	 * @param string $label The label, plain text.
	 * @param string $icon  The icon as trusted markup, may be empty.
	 *
	 * @return array<string,string>
	 */
	private function get_headline( string $label, string $icon ): array {
		return array(
			'type' => 'Text',
			'text' => '<div class="efml-setup-chosen">' . ( '' !== $icon ? '<span class="efml-setup-chosen-icon">' . $icon . '</span>' : '' ) . '<span class="efml-setup-chosen-label">' . esc_html( $label ) . '</span></div>',
		);
	}

	/**
	 * Return the icon of a service as HTML.
	 *
	 * Uses the styles of the tiles to choose a service.
	 *
	 * @param string $name The name of the service.
	 *
	 * @return string
	 */
	private function get_service_icon( string $name ): string {
		return '<span class="efml-setup-service-icon efml-' . esc_attr( sanitize_html_class( $name ) ) . '"><span></span></span>';
	}

	/**
	 * Return the name of the setup field for a field of a service.
	 *
	 * @param string $service The name of the service.
	 * @param string $field   The name of the field.
	 *
	 * @return string
	 */
	private function get_field_name( string $service, string $field ): string {
		return self::FIELD_PREFIX . sanitize_key( $service ) . '__' . sanitize_key( $field );
	}

	/**
	 * Return a value of the setup.
	 *
	 * @param string $field_name The name of the field.
	 *
	 * @return string
	 */
	private function get_value( string $field_name ): string {
		$value = \easySetupForWordPress\Setup::get_instance()->get_field_value( self::NAME, $field_name, '' );
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Return the chosen goal.
	 *
	 * @return array{label: string, description: string, icon: string, features: array<int,string>, service: string, options: array<int,string>, defaults: array<string,int|string>, action: string}|null
	 */
	public function get_chosen_goal(): ?array {
		// get the goals.
		$goals = $this->get_goals();

		// get the chosen goal.
		$name = $this->get_value( self::FIELD_GOAL );

		// return the goal, if it exists.
		return $goals[ $name ] ?? null;
	}

	/**
	 * Return the chosen service.
	 *
	 * @return Directory_Listing_Base|false
	 */
	public function get_chosen_service(): Directory_Listing_Base|false {
		// get the chosen goal.
		$goal = $this->get_chosen_goal();

		// bail if no goal has been chosen.
		if ( is_null( $goal ) ) {
			return false;
		}

		// get the name of the service: the goal could define it.
		$name = '' !== $goal['service'] ? $goal['service'] : $this->get_value( self::FIELD_SERVICE );

		// bail if no service has been chosen.
		if ( '' === $name ) {
			return false;
		}

		// return the service, if it could be used for this goal.
		foreach ( $this->get_services_for_goal( $goal ) as $listing_obj ) {
			if ( $name === $listing_obj->get_name() ) {
				return $listing_obj;
			}
		}

		// return false if the service could not be used.
		return false;
	}

	/**
	 * Return the values of the fields of the chosen service.
	 *
	 * @param Directory_Listing_Base $listing_obj The service.
	 *
	 * @return array<string,string>
	 */
	private function get_service_values( Directory_Listing_Base $listing_obj ): array {
		// collect the values.
		$values = array();
		foreach ( $listing_obj->get_fields() as $name => $field ) {
			// bail if its value is defined in another way.
			if ( ! empty( $field['constant'] ) || ( ! empty( $field['readonly'] ) && ! empty( $field['value'] ) ) ) {
				continue;
			}

			// bail if the field is not shown in the setup.
			$control = $this->get_control_type( $field );
			if ( '' === $control || 'hidden' === $control ) {
				continue;
			}

			// get the value.
			$value = $this->get_value( $this->get_field_name( $listing_obj->get_name(), (string) $name ) );

			// use "0" for unchecked checkboxes.
			if ( 'CheckboxControl' === $control ) {
				$value = 1 === absint( $value ) ? '1' : '0';
			}

			// add the value.
			$values[ (string) $name ] = $value;
		}

		// return the values.
		return $values;
	}

	/**
	 * Return the directory to use for the chosen service.
	 *
	 * @param Directory_Listing_Base $listing_obj The service.
	 *
	 * @return string
	 */
	private function get_directory( Directory_Listing_Base $listing_obj ): string {
		// bail if this is not the local service, its directory is defined by its fields.
		if ( 'local' !== $listing_obj->get_name() ) {
			return '';
		}

		// get the path.
		$path = trim( $this->get_value( self::FIELD_PATH ) );

		// bail if no path is given.
		if ( '' === $path ) {
			return '';
		}

		// return the path as URL.
		return 'file://' . trailingslashit( wp_normalize_path( $path ) );
	}

	/**
	 * Check the access data before the fields of the next step are requested.
	 *
	 * The fields of the setup are built on every request. Checking the access data while they are built would
	 * slow down every request. This is the one request, which needs the result: the setup saved the access data
	 * and now asks for the fields of the next step.
	 *
	 * @param mixed $response The response so far, passed through unchanged.
	 * @param mixed $handler  The route handler, not used.
	 * @param mixed $request  The request.
	 *
	 * @return mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function prepare_check( mixed $response, mixed $handler, mixed $request ): mixed {
		// bail if this is not the request for the fields of our setup.
		if ( ! $request instanceof WP_REST_Request || '/easy-setup-for-wordpress/v1/fields/' . self::NAME !== $request->get_route() ) {
			return $response;
		}

		// bail if the user is not allowed to use the setup: this filter runs before the permission check.
		if ( ! $this->is_allowed() ) {
			return $response;
		}

		// run the check, if the access data are complete.
		$this->run_check();

		// build the steps again with the result of the check.
		$this->setup = array();
		\easySetupForWordPress\Setup::get_instance()->set_config( $this->get_config() );

		// return the response.
		return $response;
	}

	/**
	 * Check the access data of the chosen service and save the result for the actual user.
	 *
	 * The result is saved with a hash of the access data, so the check only runs again if they have been changed.
	 *
	 * @return void
	 */
	private function run_check(): void {
		// get the chosen service.
		$listing_obj = $this->get_chosen_service();

		// bail if no service has been chosen.
		if ( ! $listing_obj instanceof Directory_Listing_Base ) {
			return;
		}

		// get the values and the directory.
		$values    = $this->get_service_values( $listing_obj );
		$directory = $this->get_directory( $listing_obj );

		// get the hash of these values.
		$hash = wp_hash( $listing_obj->get_name() . '|' . $directory . '|' . Helper::get_json( $values ) );

		// bail if these values have already been checked.
		$check = get_user_meta( get_current_user_id(), self::CHECK_META, true );
		if ( is_array( $check ) && isset( $check['hash'] ) && $hash === $check['hash'] ) {
			return;
		}

		// check the access data.
		$result = $this->check_access( $listing_obj, $values, $directory );

		// save the result.
		update_user_meta(
			get_current_user_id(),
			self::CHECK_META,
			array(
				'hash'    => $hash,
				'success' => $result['success'],
				'message' => $result['message'],
			)
		);
	}

	/**
	 * Return the result of the last check of the access data.
	 *
	 * @return array{success: bool, message: string}
	 */
	private function get_check_result(): array {
		// get the saved result.
		$check = get_user_meta( get_current_user_id(), self::CHECK_META, true );

		// bail if no check has been run.
		if ( ! is_array( $check ) || ! isset( $check['success'] ) ) {
			return array(
				'success' => false,
				'message' => '',
			);
		}

		// return the result.
		return array(
			'success' => (bool) $check['success'],
			'message' => isset( $check['message'] ) ? (string) $check['message'] : '',
		);
	}

	/**
	 * Check the given access data for the given service.
	 *
	 * @param Directory_Listing_Base $listing_obj The service.
	 * @param array<string,string>   $values      The values of its fields.
	 * @param string                 $directory   The directory to use (empty to use the directory of the fields).
	 *
	 * @return array{success: bool, message: string}
	 */
	public function check_access( Directory_Listing_Base $listing_obj, array $values, string $directory = '' ): array {
		// make sure the services are usable for the actual user.
		Abilities::get_instance()->init_services();

		// check a local directory.
		if ( 'local' === $listing_obj->get_name() ) {
			// get the path.
			$path = Helper::get_local_path_from_url( $directory );

			// bail if the directory is not usable.
			if ( '' === $path || ! is_dir( $path ) || ! is_readable( $path ) ) {
				return array(
					'success' => false,
					'message' => __( 'The directory does not exist or is not readable.', 'external-files-in-media-library' ),
				);
			}

			// the directory is usable.
			return array(
				'success' => true,
				'message' => '',
			);
		}

		// get the fields of the service and set the values.
		$fields = $listing_obj->get_fields();
		foreach ( $values as $name => $value ) {
			if ( isset( $fields[ $name ] ) ) {
				$fields[ $name ]['value'] = $value;
			}
		}

		// secure the amount of errors on the service before we start to get only the errors of this check.
		$error_offset = count( $listing_obj->get_errors() );

		try {
			// configure the service.
			$listing_obj->set_fields( $fields );
			$listing_obj->set_directory( $directory );

			// get the directory to use from the service.
			$directory = $listing_obj->get_directory();

			// bail if no directory is given.
			if ( '' === $directory ) {
				return array(
					'success' => false,
					'message' => __( 'Please enter all details.', 'external-files-in-media-library' ),
				);
			}

			// bail if the credentials of this service are defined via constants for another target.
			if ( $listing_obj instanceof Service_Base && $listing_obj->is_target_blocked_by_constants( $directory, $fields ) ) {
				return array(
					/* translators: %1$s will be replaced by the service title (e.g. FTP). */
					'message' => sprintf( __( 'The access data for %1$s are defined as constants for another server, e.g. in your wp-config.php.', 'external-files-in-media-library' ), wp_strip_all_tags( $listing_obj->get_label() ) ),
					'success' => false,
				);
			}

			// check the login.
			if ( ! $listing_obj->do_login( $directory ) ) {
				return array(
					'success' => false,
					'message' => $this->get_error_text( $listing_obj, $error_offset ),
				);
			}
		} catch ( Throwable $e ) {
			return array(
				'success' => false,
				'message' => wp_strip_all_tags( $e->getMessage() ),
			);
		} finally {
			// remove the credentials from the service object.
			$listing_obj->set_fields( array() );
			$listing_obj->set_directory( '' );
		}

		// the access data are working.
		return array(
			'success' => true,
			'message' => '',
		);
	}

	/**
	 * Return the text of the errors, which the service collected since the given offset.
	 *
	 * @param Directory_Listing_Base $listing_obj  The service.
	 * @param int                    $error_offset The amount of errors before the check.
	 *
	 * @return string
	 */
	private function get_error_text( Directory_Listing_Base $listing_obj, int $error_offset ): string {
		// collect the messages.
		$messages = array();
		foreach ( array_slice( $listing_obj->get_errors(), $error_offset ) as $error ) {
			// add its messages.
			foreach ( $error->get_error_messages() as $message ) {
				$messages[] = wp_strip_all_tags( $message );
			}
		}

		// return a generic text, if no messages are known.
		if ( empty( $messages ) ) {
			return __( 'Check the details you entered and the log for further information.', 'external-files-in-media-library' );
		}

		// return the messages.
		return implode( ' ', array_unique( $messages ) );
	}

	/**
	 * Encrypt the value of a password field of our setup before it is stored.
	 *
	 * @param string $value       The value.
	 * @param string $field_name  The name of the field.
	 * @param string $config_name The name of the setup configuration.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function encrypt_password( string $value, string $field_name, string $config_name ): string {
		// bail if this is not our setup.
		if ( self::NAME !== $config_name ) {
			return $value;
		}

		// return the encrypted value.
		return Crypt::get_instance()->encrypt( $value );
	}

	/**
	 * Decrypt the value of a password field of our setup before it is used.
	 *
	 * @param string $value       The value.
	 * @param string $field_name  The name of the field.
	 * @param string $config_name The name of the setup configuration.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function decrypt_password( string $value, string $field_name, string $config_name ): string {
		// bail if this is not our setup.
		if ( self::NAME !== $config_name ) {
			return $value;
		}

		// return the decrypted value.
		return Crypt::get_instance()->decrypt( $value );
	}

	/**
	 * Run the process of the setup: create the external source and configure everything for the chosen goal.
	 *
	 * @param string $config_name The name of the setup configuration.
	 *
	 * @return void
	 */
	public function run_process( string $config_name ): void {
		// bail if this is not our setup.
		if ( self::NAME !== $config_name ) {
			return;
		}

		// start this run without the messages of a previous one.
		$this->process_messages = array();

		// make sure the services are usable for the actual user.
		Abilities::get_instance()->init_services();

		// get the chosen goal and service.
		$goal        = $this->get_chosen_goal();
		$listing_obj = $this->get_chosen_service();

		// set the max steps of this process.
		update_option( 'esfw_max_steps', absint( get_option( 'esfw_max_steps' ) ) + 4 );

		// bail if nothing has been chosen.
		if ( is_null( $goal ) || ! $listing_obj instanceof Directory_Listing_Base ) {
			$this->add_process_message( __( 'Nothing has been created, because the quickstart was not complete. Please start again.', 'external-files-in-media-library' ), false );
			return;
		}

		// step 1: create the external source.
		$this->set_process_label( __( 'Saving the external source.', 'external-files-in-media-library' ) );
		$term_id = $this->create_source( $listing_obj );
		$this->update_process_step();

		// bail if no external source could be created.
		if ( 0 === $term_id ) {
			return;
		}

		// get the service object.
		$service_obj = Services::get_instance()->get_service_for_listing( $listing_obj );

		// step 2: save the chosen options.
		$this->set_process_label( __( 'Saving the settings.', 'external-files-in-media-library' ) );
		$this->apply_options( $goal, $service_obj instanceof Service_Base ? $service_obj : null, $term_id );
		$this->update_process_step();

		// step 3: test the connection, if files should be exported.
		$this->set_process_label( __( 'Testing the connection.', 'external-files-in-media-library' ) );
		if ( 'export' === $goal['action'] && $service_obj instanceof Service_Base ) {
			$this->run_connection_test( $service_obj, $term_id );
		}
		$this->update_process_step();

		// step 4: run the action of the goal.
		$this->set_process_label( __( 'Loading the files.', 'external-files-in-media-library' ) );
		$this->run_action( $goal, $term_id );
		$this->update_process_step();

		// save the URL to forward to.
		update_user_meta( get_current_user_id(), self::FORWARD_META, 'export' === $goal['action'] ? Directory_Listing::get_instance()->get_listing_url() : admin_url( 'upload.php' ) );

		// remove the values of this setup.
		$this->clear_answers();
	}

	/**
	 * Create the external source for the chosen service.
	 *
	 * @param Directory_Listing_Base $listing_obj The service.
	 *
	 * @return int The ID of the external source, 0 if it could not be created.
	 */
	private function create_source( Directory_Listing_Base $listing_obj ): int {
		// create the external source.
		$result = Abilities::get_instance()->add_source(
			array(
				'service'   => $listing_obj->get_name(),
				'fields'    => $this->get_service_values( $listing_obj ),
				'directory' => $this->get_directory( $listing_obj ),
			)
		);

		// bail on error.
		if ( is_wp_error( $result ) ) {
			/* translators: %1$s will be replaced by an error message. */
			$this->add_process_message( sprintf( __( 'The external source could not be saved: %1$s', 'external-files-in-media-library' ), $result->get_error_message() ), false );
			return 0;
		}

		// get the ID.
		$term_id = isset( $result['id'] ) && is_scalar( $result['id'] ) ? absint( $result['id'] ) : 0;

		// add the message.
		/* translators: %1$s will be replaced by the name of the external source. */
		$this->add_process_message( sprintf( __( 'The external source %1$s has been saved.', 'external-files-in-media-library' ), '<em>' . esc_html( isset( $result['name'] ) && is_scalar( $result['name'] ) ? (string) $result['name'] : '' ) . '</em>' ), true );

		// return the ID.
		return $term_id;
	}

	/**
	 * Save the chosen options of the goal.
	 *
	 * @param array<string,mixed> $goal        The goal.
	 * @param Service_Base|null   $service_obj The service object.
	 * @param int                 $term_id     The ID of the external source.
	 *
	 * @return void
	 */
	private function apply_options( array $goal, ?Service_Base $service_obj, int $term_id ): void {
		// collect the chosen values of the available options.
		$values = array();
		foreach ( (array) $goal['options'] as $option ) {
			// bail if the option is not available.
			if ( ! $this->is_option_available( (string) $option, $service_obj ) ) {
				continue;
			}

			$values[ (string) $option ] = $this->get_value( self::OPTION_PREFIX . $option );
		}

		// configure the export.
		if ( isset( $values['export'] ) ) {
			$this->apply_export_options( $values, $service_obj, $term_id );
		}

		// enable the downloads of WooCommerce.
		if ( isset( $values['woocommerce'] ) && 1 === absint( $values['woocommerce'] ) ) {
			update_option( 'eml_woocommerce', 1 );
			$this->add_process_message( __( 'External files could now be used as downloads of WooCommerce products.', 'external-files-in-media-library' ), true );
		}

		// configure the synchronization.
		if ( isset( $values['sync'] ) && 1 === absint( $values['sync'] ) ) {
			// enable the synchronization in the settings.
			update_option( 'eml_sync', 1 );

			// save the interval.
			$interval = isset( $values['sync_interval'] ) ? $values['sync_interval'] : '';
			if ( '' !== $interval && array_key_exists( $interval, wp_get_schedules() ) ) {
				update_term_meta( $term_id, 'interval', $interval );
			}

			// enable the synchronization for this external source.
			Synchronization::get_instance()->set_state( $term_id, 1 );
			$this->add_process_message( __( 'The automatic synchronization has been enabled.', 'external-files-in-media-library' ), true );
		}

		// run the tasks of other options.
		foreach ( $values as $option => $value ) {
			/**
			 * Save the chosen value of an option of the setup.
			 *
			 * @since 6.0.0 Available since 6.0.0.
			 * @param string            $option      The name of the option.
			 * @param string            $value       The chosen value.
			 * @param int               $term_id     The ID of the created external source.
			 * @param Service_Base|null $service_obj The service object.
			 */
			do_action( 'efml_setup_apply_option', $option, $value, $term_id, $service_obj );
		}
	}

	/**
	 * Save the options for the export.
	 *
	 * @param array<string,string> $values      The chosen values.
	 * @param Service_Base|null    $service_obj The service object.
	 * @param int                  $term_id     The ID of the external source.
	 *
	 * @return void
	 */
	private function apply_export_options( array $values, ?Service_Base $service_obj, int $term_id ): void {
		// bail if the export should not be enabled.
		if ( 1 !== absint( $values['export'] ) ) {
			$this->add_process_message( __( 'The export has not been enabled, as you have not chosen it.', 'external-files-in-media-library' ), true );
			return;
		}

		// get the export object of the service.
		$export_obj = $service_obj instanceof Service_Base ? $service_obj->get_export_object() : false;

		// bail if the service does not support the export.
		if ( ! $export_obj instanceof Export_Base ) {
			return;
		}

		// save the URL of the exported files, if the service requires it.
		if ( $export_obj->is_url_required() ) {
			// get the URL.
			$url = esc_url_raw( trim( $this->get_value( self::FIELD_EXPORT_URL ) ) );

			// check the URL.
			$error = ExportDialog::get_instance()->check_url( $url );

			// bail if the URL could not be used.
			if ( '' !== $error ) {
				/* translators: %1$s will be replaced by an error message. */
				$this->add_process_message( sprintf( __( 'The export has not been enabled: %1$s Configure the export for this external source in the list of your external sources.', 'external-files-in-media-library' ), $error ), false );
				return;
			}

			// save it.
			update_term_meta( $term_id, 'efml_export_url', $url );
		}

		// save the option to save files privately.
		if ( isset( $values['export_private'] ) && 1 === absint( $values['export_private'] ) ) {
			update_term_meta( $term_id, 'efml_export_private', 1 );
		}

		// enable the export in the settings.
		update_option( 'eml_export', 1 );

		// enable the folders.
		if ( isset( $values['export_folders'] ) && 1 === absint( $values['export_folders'] ) ) {
			update_option( 'eml_export_folders', 1 );
		}

		// enable the export for this external source.
		Export::get_instance()->set_state_for_term( $term_id, 1 );

		// set it as main export.
		if ( isset( $values['export_main'] ) && 1 === absint( $values['export_main'] ) ) {
			update_option( 'eml_export_main_source', $term_id );
		}

		// add the message.
		$this->add_process_message( __( 'New files in your media library are now exported to this external source.', 'external-files-in-media-library' ), true );
	}

	/**
	 * Run the connection test for the created external source.
	 *
	 * @param Service_Base $service_obj The service object.
	 * @param int          $term_id     The ID of the external source.
	 *
	 * @return void
	 */
	private function run_connection_test( Service_Base $service_obj, int $term_id ): void {
		// get the service object for the connection test.
		$test_obj = Connection_Test::get_instance()->get_service_by_name( $service_obj->get_name() );

		// bail if the service does not support the connection test.
		if ( ! $test_obj instanceof Service_Base ) {
			return;
		}

		// get the configuration of the external source.
		$term_data = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_entry( $term_id );

		// bail if no configuration could be loaded.
		if ( empty( $term_data ) ) {
			return;
		}

		try {
			// configure the service.
			$test_obj->set_fields( ! empty( $term_data['fields'] ) && is_array( $term_data['fields'] ) ? $term_data['fields'] : array() );
			$test_obj->set_connection_test_directory( ! empty( $term_data['directory'] ) && is_string( $term_data['directory'] ) ? $term_data['directory'] : '' );

			// run the test.
			$result = Connection_Test::get_instance()->get_steps_list( $test_obj->run_connection_test() );
		} finally {
			// remove the credentials from the service object.
			$test_obj->set_fields( array() );
		}

		// add the result.
		$this->add_process_message( ( $result['success'] ? __( 'The connection test was successful.', 'external-files-in-media-library' ) : __( 'The connection test failed. Check the steps for details:', 'external-files-in-media-library' ) ) . $result['html'], $result['success'] );
	}

	/**
	 * Run the action of the chosen goal.
	 *
	 * @param array<string,mixed> $goal    The goal.
	 * @param int                 $term_id The ID of the external source.
	 *
	 * @return void
	 */
	private function run_action( array $goal, int $term_id ): void {
		// get the action.
		$action = isset( $goal['action'] ) && is_string( $goal['action'] ) ? $goal['action'] : '';

		// run the action.
		switch ( $action ) {
			case 'export':
				// point to the bulk action for the files, which are already in the media library.
				/* translators: %1$s will be replaced by a URL. */
				$this->add_process_message( sprintf( __( 'Files, which are already in your media library, stay where they are. To export them, choose them <a href="%1$s">in the list view of your media library</a> and use the bulk action "Export to external source".', 'external-files-in-media-library' ), esc_url( add_query_arg( array( 'mode' => 'list' ), admin_url( 'upload.php' ) ) ) ), true );
				break;

			case 'sync':
				// bail if the synchronization is not enabled.
				if ( 1 !== absint( get_term_meta( $term_id, 'sync', true ) ) ) {
					break;
				}

				// run the first synchronization.
				$result = Abilities::get_instance()->sync_source( array( 'source_id' => $term_id ) );
				if ( is_wp_error( $result ) ) {
					/* translators: %1$s will be replaced by an error message. */
					$this->add_process_message( sprintf( __( 'The first synchronization failed: %1$s', 'external-files-in-media-library' ), $result->get_error_message() ), false );
					break;
				}
				$this->add_process_message( __( 'The first synchronization has been run. Large directories are synchronized in the background, the progress is shown in the admin bar.', 'external-files-in-media-library' ), true );
				break;

			case 'import':
				// get the directory of the external source.
				$term_data = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_entry( $term_id );
				$directory = ! empty( $term_data['directory'] ) && is_string( $term_data['directory'] ) ? $term_data['directory'] : '';

				// bail if no directory is known.
				if ( '' === $directory ) {
					break;
				}

				// collect the options.
				$input = array(
					'urls'      => array( $directory ),
					'source_id' => $term_id,
				);
				if ( 1 === absint( $this->get_value( self::OPTION_PREFIX . 'real_import' ) ) && current_user_can( 'efml_cap_tools_real_import' ) ) {
					$input['real_import'] = true;
				}

				// run the import.
				$result = Abilities::get_instance()->import( $input );
				if ( is_wp_error( $result ) ) {
					/* translators: %1$s will be replaced by an error message. */
					$this->add_process_message( sprintf( __( 'The import failed: %1$s', 'external-files-in-media-library' ), $result->get_error_message() ), false );
					break;
				}

				/* translators: %1$d will be replaced by a number. */
				$this->add_process_message( sprintf( _n( '%1$d file has been imported.', '%1$d files have been imported.', absint( $result['imported_count'] ), 'external-files-in-media-library' ), absint( $result['imported_count'] ) ) . ' ' . __( 'Large directories are imported in the background, the progress is shown in the admin bar.', 'external-files-in-media-library' ), true );
				break;
		}

		/**
		 * Run further tasks at the end of the setup.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param string              $action  The action of the goal.
		 * @param int                 $term_id The ID of the created external source.
		 * @param array<string,mixed> $goal    The goal.
		 */
		do_action( 'efml_setup_run_action', $action, $term_id, $goal );
	}

	/**
	 * Add a message for the result of the process.
	 *
	 * @param string $text    The message (HTML).
	 * @param bool   $success Whether it is a success.
	 *
	 * @return void
	 */
	private function add_process_message( string $text, bool $success ): void {
		$this->process_messages[] = array(
			'text'    => $text,
			'success' => $success,
		);
	}

	/**
	 * Return the messages of the actual process.
	 *
	 * @return array<int,array{text: string, success: bool}>
	 */
	public function get_process_messages(): array {
		return $this->process_messages;
	}

	/**
	 * Set the label of the process.
	 *
	 * @param string $label The label.
	 *
	 * @return void
	 */
	private function set_process_label( string $label ): void {
		update_option( 'esfw_step_label', $label );
	}

	/**
	 * Update the step of the process.
	 *
	 * @param int $step The steps to add.
	 *
	 * @return void
	 */
	private function update_process_step( int $step = 1 ): void {
		update_option( 'esfw_step', absint( get_option( 'esfw_step' ) ) + $step );
	}

	/**
	 * Show the result at the end of the process.
	 *
	 * @param string $config_name The name of the setup configuration.
	 *
	 * @return void
	 */
	public function show_process_end( string $config_name ): void {
		// bail if this is not our setup.
		if ( self::NAME !== $config_name ) {
			return;
		}

		// collect the messages.
		$list = '';
		foreach ( $this->process_messages as $message ) {
			$list .= '<li class="efml-setup-result-' . ( $message['success'] ? 'success' : 'failed' ) . '"><span class="dashicons ' . ( $message['success'] ? 'dashicons-yes-alt' : 'dashicons-dismiss' ) . '" aria-hidden="true"></span><div>' . wp_kses_post( $message['text'] ) . '</div></li>';
		}

		// create the text.
		$text = '<ul class="efml-setup-result">' . $list . '</ul><p>' . esc_html__( 'Click on "Completed" to finish the quickstart. You can change all settings at any time.', 'external-files-in-media-library' ) . '</p>';

		/**
		 * Filter the text at the end of the setup.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param string $text The text.
		 * @param array<int,array{text: string, success: bool}> $messages The messages of the process.
		 */
		$this->set_process_label( apply_filters( 'efml_setup_process_completed_text', $text, $this->process_messages ) );

		// set the steps to the max.
		update_option( 'esfw_step', absint( get_option( 'esfw_max_steps' ) ) );
	}

	/**
	 * Forward the user after the setup has been completed.
	 *
	 * @param string $config_name The name of the setup configuration.
	 *
	 * @return void
	 */
	public function set_completed( string $config_name ): void {
		// bail if this is not our setup.
		if ( self::NAME !== $config_name ) {
			return;
		}

		// get the URL to forward to.
		$forward = (string) get_user_meta( get_current_user_id(), self::FORWARD_META, true );
		delete_user_meta( get_current_user_id(), self::FORWARD_META );

		// use the list of services as fallback.
		if ( '' === $forward ) {
			$forward = Directory_Listing::get_instance()->get_view_directory_url( false );
		}

		// return JSON with the URL.
		wp_send_json(
			array(
				'forward' => $forward,
			)
		);
	}

	/**
	 * Start a fresh setup whenever its page is opened.
	 *
	 * @return void
	 */
	public function maybe_clear_answers(): void {
		// bail if this is not the page of the setup.
		if ( ! $this->is_setup_page() || wp_doing_ajax() ) {
			return;
		}

		// bail if the setup should be continued (e.g. after connecting a service).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
		if ( isset( $_GET[ self::CONTINUE_PARAMETER ] ) ) {
			return;
		}

		// remove the values.
		$this->clear_answers();
	}

	/**
	 * Remove the values of the setup for the actual user.
	 *
	 * @return void
	 */
	public function clear_answers(): void {
		\easySetupForWordPress\Setup::get_instance()->delete_field_values( self::NAME );
		delete_user_meta( get_current_user_id(), self::CHECK_META );
		delete_user_meta( get_current_user_id(), self::OAUTH_RETURN_META );
	}

	/**
	 * Return the URL to return to after connecting the given service via OAuth.
	 *
	 * Returns an empty string, if the connection has not been started in the setup.
	 *
	 * @param string $service_name The name of the service.
	 *
	 * @return string
	 */
	public function get_oauth_return_url( string $service_name ): string {
		// get the marker.
		$marker = get_user_meta( get_current_user_id(), self::OAUTH_RETURN_META, true );

		// bail if the connection has not been started in the setup.
		if ( ! is_array( $marker ) || empty( $marker['service'] ) || $service_name !== $marker['service'] ) {
			return '';
		}

		// remove the marker, it is only used once.
		delete_user_meta( get_current_user_id(), self::OAUTH_RETURN_META );

		// bail if the marker is too old.
		if ( empty( $marker['time'] ) || absint( $marker['time'] ) < time() - self::OAUTH_RETURN_TIMEOUT ) {
			return '';
		}

		// return the URL to continue the setup.
		return $this->get_url( true );
	}

	/**
	 * Add the setup to the pages of the command palette.
	 *
	 * @param array<int,array{name: string, label: string, url: string, keywords: array<int,string>, external: bool}> $entries List of entries.
	 *
	 * @return array<int,array{name: string, label: string, url: string, keywords: array<int,string>, external: bool}>
	 */
	public function add_command_palette_page( array $entries ): array {
		// bail if the user is not allowed to use the setup.
		if ( ! $this->is_allowed() ) {
			return $entries;
		}

		// add the entry.
		$entries[] = array(
			'name'     => 'setup',
			'label'    => __( 'Quickstart', 'external-files-in-media-library' ),
			'url'      => $this->get_url(),
			'keywords' => array_values( array_filter( array_map( 'trim', explode( ',', _x( 'quickstart, setup, wizard, getting started, offload, synchronize, import', 'search terms for the quickstart, separated by commas', 'external-files-in-media-library' ) ) ) ) ),
			'external' => false,
		);

		// return the resulting list.
		return $entries;
	}

	/**
	 * Remove the data of the setup during uninstallation.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		// bail if the setup library is not available.
		if ( ! $this->is_library_available() ) {
			return;
		}

		// remove the options of the library.
		\easySetupForWordPress\Setup::get_instance()->uninstall( self::NAME );

		// remove the values of all users.
		delete_metadata( 'user', 0, 'esfw_values_' . self::NAME, '', true );
		delete_metadata( 'user', 0, self::CHECK_META, '', true );
		delete_metadata( 'user', 0, self::OAUTH_RETURN_META, '', true );
		delete_metadata( 'user', 0, self::FORWARD_META, '', true );
	}
}
