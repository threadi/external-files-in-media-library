<?php
/**
 * File to add the functions of this plugin to the dialog of WordPress to choose or upload media files.
 *
 * The dialog gets:
 * - the filter for external files beside the filters of WordPress (its options are loaded on first use),
 * - a mark on external, exported and private files,
 * - the tab "Insert from URL" to import external URLs,
 * - the tab "From external source" with the directory listing of the saved external sources of the actual user,
 *   loaded in an iframe on its first use (or in a new window, if the iframe could not be used).
 *
 * Nothing of it is loaded before the user uses it: opening the dialog does not cause any further request or query.
 * Users get these functions only with the capability "efml_cap_tools_media_dialog" (chosen per role in the
 * settings under "Permissions"). Every import checks the permissions of the user like everywhere else.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use easyDirectoryListingForWordPress\Init;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Services\Services;
use WP_Error;
use WP_Post;
use WP_Term;

/**
 * Object to add the functions of this plugin to the media dialog.
 */
class Media_Dialog extends Tools_Base {
	/**
	 * The name of this tool (its capability is "efml_cap_tools_media_dialog").
	 *
	 * @var string
	 */
	protected string $name = 'media_dialog';

	/**
	 * The capability, which is needed to use the functions in the media dialog.
	 */
	public const CAPABILITY = 'efml_cap_tools_media_dialog';

	/**
	 * The name of the nonce for the requests of the media dialog.
	 */
	private const NONCE = 'efml-media-dialog';

	/**
	 * The name of the action, which shows the page in the iframe of the tab "From external source".
	 */
	public const FRAME_ACTION = 'efml_media_dialog';

	/**
	 * The hook of the page in the iframe (used for its scripts).
	 */
	private const FRAME_HOOK = 'efml-media-dialog';

	/**
	 * The configuration of the actual page in the iframe for its script.
	 *
	 * @var array{source: int, origin: string}|array{}
	 */
	private array $frame = array();

	/**
	 * Cache for the labels of the services.
	 *
	 * @var array<string,string>
	 */
	private array $service_labels = array();

	/**
	 * Instance of actual object.
	 *
	 * @var ?Media_Dialog
	 */
	private static ?Media_Dialog $instance = null;

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
	 * @return Media_Dialog
	 */
	public static function get_instance(): Media_Dialog {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * Only registers hooks: nothing is checked or loaded here.
	 *
	 * @return void
	 */
	public function init(): void {
		// the scripts in the media dialog, wherever WordPress loads it (also in page builders in the frontend).
		add_action( 'wp_enqueue_media', array( $this, 'add_scripts' ) );

		// the data for the mark on the files in the media dialog.
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'add_mark' ), 10, 2 );

		// the requests of the media dialog.
		add_action( 'wp_ajax_efml_media_dialog_filter_options', array( $this, 'send_filter_options' ) );
		add_action( 'wp_ajax_efml_media_dialog_import', array( $this, 'import_via_ajax' ) );

		// the page in the iframe of the tab "From external source".
		add_action( 'admin_action_' . self::FRAME_ACTION, array( $this, 'show_frame' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_frame_scripts' ) );
	}

	/**
	 * Return the title of this tool, used for its permission in the settings.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'External files in the media dialog', 'external-files-in-media-library' );
	}

	/**
	 * Return whether this tool requires a capability.
	 *
	 * @return bool
	 */
	public function has_capability(): bool {
		return true;
	}

	/**
	 * Return the roles, which could use this tool by default.
	 *
	 * @return array<int,string>
	 */
	public function get_capability_default(): array {
		return array( 'administrator', 'editor' );
	}

	/**
	 * Return the description for the capability settings.
	 *
	 * @return string
	 */
	public function get_capability_description(): string {
		return __( 'Select roles, which could use external files in the dialog to choose or upload media files: the filter, the mark on external files and the tabs to insert files from URLs and from external sources. The permissions for the external sources apply there, too.', 'external-files-in-media-library' );
	}

	/**
	 * Return whether the actual user could use the functions in the media dialog.
	 *
	 * Checks only the capabilities of the user, which are already loaded: no query.
	 *
	 * @return bool
	 */
	public function is_allowed(): bool {
		return is_user_logged_in() && current_user_can( EFML_CAP_NAME ) && current_user_can( self::CAPABILITY );
	}

	/**
	 * Return the origin (scheme, host and port) of the given URL.
	 *
	 * @param string $url The URL.
	 *
	 * @return string
	 */
	private function get_origin( string $url ): string {
		// get the parts.
		$parts = wp_parse_url( $url );

		// bail if the URL is not usable.
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		// return the origin.
		return strtolower( $parts['scheme'] . '://' . $parts['host'] . ( ! empty( $parts['port'] ) ? ':' . absint( $parts['port'] ) : '' ) );
	}

	/**
	 * Return the origins, from which the media dialog could use the page of the tab "From external source".
	 *
	 * These are the origins of the administration and of the website (page builders open the media dialog there).
	 *
	 * @return array<int,string>
	 */
	private function get_allowed_origins(): array {
		return array_values( array_unique( array_filter( array( $this->get_origin( admin_url() ), $this->get_origin( home_url() ), $this->get_origin( site_url() ) ) ) ) );
	}

	/**
	 * Return the URL of the page in the iframe of the tab "From external source".
	 *
	 * @param int    $source_id The ID of the external source to show (0 for the list of external sources).
	 * @param string $origin    The origin of the media dialog.
	 *
	 * @return string
	 */
	public function get_frame_url( int $source_id = 0, string $origin = '' ): string {
		// collect the parameters.
		$parameters = array(
			'action' => self::FRAME_ACTION,
		);
		if ( $source_id > 0 ) {
			$parameters['source'] = $source_id;
		}
		if ( '' !== $origin ) {
			$parameters['origin'] = rawurlencode( $origin );
		}

		// return the URL.
		return add_query_arg( $parameters, admin_url( 'admin.php' ) );
	}

	/**
	 * Add the scripts for the media dialog.
	 *
	 * Runs whenever WordPress loads the media dialog. Only adds the scripts and their configuration: no query and no
	 * request. The options of the filter, the external sources and the page of the tab are loaded on their first use.
	 *
	 * @return void
	 */
	public function add_scripts(): void {
		// bail if the user could not use it.
		if ( ! $this->is_allowed() ) {
			return;
		}

		// add the filter, except in the media library in grid view: it has the filter with all its options already.
		global $pagenow;
		if ( 'upload.php' !== $pagenow ) {
			// collect the options, which need no query: the others are loaded on first use of the filter.
			$options = array();
			foreach ( Tables::get_instance()->get_base_filter_options() as $value => $label ) {
				$options[] = array(
					'value' => (string) $value,
					'label' => $label,
				);
			}

			// add the script.
			wp_enqueue_script(
				'efml-media-filter',
				Helper::get_plugin_url() . 'admin/media-filter.js',
				array( 'media-views' ),
				Helper::get_file_version( Helper::get_plugin_dir() . 'admin/media-filter.js' ),
				true
			);

			// add its configuration.
			wp_localize_script(
				'efml-media-filter',
				'efmlMediaFilter',
				array(
					'label'    => __( 'Filter by external files', 'external-files-in-media-library' ),
					'options'  => $options,
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( self::NONCE ),
				)
			);
		}

		// add the script for the mark and the tabs.
		wp_enqueue_script(
			'efml-media-dialog',
			Helper::get_plugin_url() . 'admin/media-dialog.js',
			array( 'media-views', 'jquery' ),
			Helper::get_file_version( Helper::get_plugin_dir() . 'admin/media-dialog.js' ),
			true
		);

		// add its configuration.
		wp_localize_script(
			'efml-media-dialog',
			'efmlMediaDialog',
			array(
				'ajax_url'     => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( self::NONCE ),
				'admin_origin' => $this->get_origin( admin_url() ),
				'frame_url'    => $this->get_frame_url(),
				'frame_path'   => (string) wp_parse_url( admin_url( 'admin.php' ), PHP_URL_PATH ),
				'timeout'      => 8000,
				'texts'        => array(
					'tab_url'           => __( 'Insert from URL', 'external-files-in-media-library' ),
					'tab_sources'       => __( 'From external source', 'external-files-in-media-library' ),
					'url_label'         => __( 'URLs of the files', 'external-files-in-media-library' ),
					'url_description'   => __( 'Enter one URL per line. The files stay on their external source and are used in your media library.', 'external-files-in-media-library' ),
					'url_button'        => __( 'Add to media library', 'external-files-in-media-library' ),
					'importing'         => __( 'The files are added …', 'external-files-in-media-library' ),
					/* translators: %1$d will be replaced by a number. */
					'imported'          => __( '%1$d file has been added and selected.', 'external-files-in-media-library' ),
					/* translators: %1$d will be replaced by a number. */
					'imported_plural'   => __( '%1$d files have been added and selected.', 'external-files-in-media-library' ),
					/* translators: %1$d will be replaced by a number. */
					'queued'            => __( '%1$d URLs are imported in the background. They appear in your media library afterward.', 'external-files-in-media-library' ),
					'nothing_imported'  => __( 'No file has been added.', 'external-files-in-media-library' ),
					'error'             => __( 'The request failed. Please try again.', 'external-files-in-media-library' ),
					'frame_title'       => __( 'External sources', 'external-files-in-media-library' ),
					'window_button'     => __( 'Open the external sources in a new window', 'external-files-in-media-library' ),
					'window_hint'       => __( 'The external sources could not be shown in this dialog. Open them in a new window: files you add there are selected here.', 'external-files-in-media-library' ),
					'window_hint_block' => __( 'Your website prevents showing its administration in this dialog (header "X-Frame-Options" or "Content-Security-Policy: frame-ancestors").', 'external-files-in-media-library' ),
				),
			)
		);

		// add the logos of the services and our styles for the media dialog.
		Helper::enqueue_components_style();
		wp_add_inline_style( 'media-views', $this->get_dialog_styles() );
	}

	/**
	 * Return the styles of the functions in the media dialog.
	 *
	 * The filter is placed beside the filters of WordPress in their grid (labels in the first row, fields in the
	 * second). The logos of the services in the marks come from the shared components (admin/components.css).
	 *
	 * @return string
	 */
	private function get_dialog_styles(): string {
		return '.media-frame .media-toolbar-secondary{grid-auto-columns:minmax(0,1fr)}'
			. '@media (min-width:901px){.media-frame .attachments-browser .media-toolbar-secondary:has(>select#media-attachment-efml-filter){width:66%}.media-frame .attachments-browser .media-toolbar-secondary:has(>select#media-attachment-efml-filter)>select.attachment-filters{margin-right:0}}'
			. '.media-frame .media-toolbar-secondary>label[for="media-attachment-efml-filter"]{grid-row:1}'
			. '.media-frame .media-toolbar-secondary>select#media-attachment-efml-filter{grid-row:2}'
			. '.attachment-preview .efml-media-mark{position:absolute;left:4px;top:4px;z-index:2;display:flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,.95);box-shadow:0 0 0 1px rgba(0,0,0,.15);color:#2271b1;pointer-events:none;--efml-service-icon-size:16px}'
			. '.attachment-preview .efml-media-mark:after{position:absolute;right:-5px;bottom:-5px;width:14px;height:14px;border-radius:50%;background:#fff;box-shadow:0 0 0 1px rgba(0,0,0,.15);font:12px/14px dashicons;text-align:center}'
			. '.attachment-preview .efml-media-mark-private:after{content:"\f160";color:#b32d2e}'
			. '.attachment-preview .efml-media-mark-exported:after{content:"\f13b";color:#2271b1}'
			. '.efml-media-dialog{padding:16px;height:100%;box-sizing:border-box;overflow:auto}'
			. '.efml-media-dialog textarea{width:100%;max-width:800px;min-height:10em;font-family:monospace}'
			. '.efml-media-dialog .efml-media-dialog-result{margin-top:1em}'
			. '.efml-media-dialog .efml-media-dialog-result ul{list-style:disc;margin-left:2em}'
			. '.efml-media-dialog-sources{padding:0;overflow:hidden}'
			. '.efml-media-dialog-sources iframe{display:block;width:100%;height:100%;border:0}'
			. '.efml-media-dialog-sources .efml-media-dialog-fallback{padding:16px}';
	}

	/**
	 * Return the label of the given service (or protocol), if it is a service.
	 *
	 * @param string $name The name of the service.
	 *
	 * @return string
	 */
	private function get_service_label( string $name ): string {
		// use the cache.
		if ( isset( $this->service_labels[ $name ] ) ) {
			return $this->service_labels[ $name ];
		}

		// get the service.
		$listing_obj = '' !== $name ? Services::get_instance()->get_service_by_name( $name ) : false;

		// save and return its label.
		$this->service_labels[ $name ] = $listing_obj instanceof Directory_Listing_Base ? wp_strip_all_tags( $listing_obj->get_label() ) : '';
		return $this->service_labels[ $name ];
	}

	/**
	 * Add the mark of external files to the data of an attachment for the media dialog: the logo of its service and
	 * whether it is private or exported.
	 *
	 * Reads only post meta, which WordPress has already loaded for the files of the media dialog: no further query.
	 *
	 * @param array<string,mixed> $response   The data of the attachment.
	 * @param WP_Post|mixed       $attachment The attachment.
	 *
	 * @return array<string,mixed>
	 */
	public function add_mark( array $response, mixed $attachment ): array {
		// bail if this is no attachment.
		if ( ! $attachment instanceof WP_Post ) {
			return $response;
		}

		// bail if the user could not use it.
		if ( ! $this->is_allowed() ) {
			return $response;
		}

		// get the external file.
		$external_file_obj = Files::get_instance()->get_file( $attachment->ID );

		// bail if it is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			return $response;
		}

		// get its service.
		$service = $external_file_obj->get_saved_service_name();
		$label   = $this->get_service_label( $service );

		// add the mark with its label.
		$response['efml_mark']         = 'external';
		$response['efml_mark_service'] = sanitize_html_class( $service );
		/* translators: %1$s will be replaced by the name of a service, e.g. "Dropbox". */
		$response['efml_mark_label'] = '' !== $label ? sprintf( __( 'External file from %1$s', 'external-files-in-media-library' ), $label ) : __( 'External file', 'external-files-in-media-library' );
		if ( $external_file_obj->is_private() ) {
			$response['efml_mark'] = 'private';
			/* translators: %1$s will be replaced by the name of a service, e.g. "Dropbox". */
			$response['efml_mark_label'] = '' !== $label ? sprintf( __( 'Private external file from %1$s', 'external-files-in-media-library' ), $label ) : __( 'Private external file', 'external-files-in-media-library' );
		} elseif ( $external_file_obj->is_exported() ) {
			$response['efml_mark'] = 'exported';
			/* translators: %1$s will be replaced by the name of a service, e.g. "Dropbox". */
			$response['efml_mark_label'] = '' !== $label ? sprintf( __( 'Exported to %1$s', 'external-files-in-media-library' ), $label ) : __( 'Exported to an external source', 'external-files-in-media-library' );
		}

		// return the data.
		return $response;
	}

	/**
	 * Check the request of the media dialog: the nonce and the capabilities.
	 *
	 * Ends the request with an error, if it could not be used.
	 *
	 * @return void
	 */
	private function check_request(): void {
		// check the nonce.
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'The request is not valid. Please reload the page.', 'external-files-in-media-library' ) ), 403 );
		}

		// check the capabilities.
		if ( ! $this->is_allowed() ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to use external files here.', 'external-files-in-media-library' ) ), 403 );
		}
	}

	/**
	 * Send all options of the filter for external files (they are loaded on first use of the filter).
	 *
	 * @return void
	 */
	public function send_filter_options(): void {
		// check the request.
		$this->check_request();

		// collect the options in their order.
		$options = array();
		foreach ( Tables::get_instance()->get_filter_options() as $value => $label ) {
			$options[] = array(
				'value' => (string) $value,
				'label' => wp_strip_all_tags( $label ),
			);
		}

		// send them.
		wp_send_json_success( array( 'options' => $options ) );
	}

	/**
	 * Import the given URLs (of the tab "Insert from URL" or a file of an external source) and send the IDs of the
	 * files.
	 *
	 * Uses the import of the abilities, which checks the permission for the external source and the import of each
	 * URL. URLs, which are already in the media library, are not imported again: their file is selected.
	 *
	 * @return void
	 */
	public function import_via_ajax(): void {
		// check the request.
		$this->check_request();

		// get the URLs: one per line.
		$urls = array();
		$raw  = isset( $_POST['urls'] ) && is_string( $_POST['urls'] ) ? wp_unslash( $_POST['urls'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- The nonce is checked above, each URL is sanitized below.
		foreach ( (array) preg_split( '/\r\n|\r|\n/', $raw ) as $url ) {
			$url = trim( wp_strip_all_tags( (string) $url ) );
			if ( '' !== $url ) {
				$urls[] = $url;
			}
		}

		// bail if no URL is given.
		if ( empty( $urls ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter at least one URL.', 'external-files-in-media-library' ) ) );
		}

		// get the external source, if given.
		$source_id = isset( $_POST['source'] ) ? absint( wp_unslash( $_POST['source'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce is checked above.

		// bail if the external source could not be used by the actual user.
		if ( $source_id > 0 ) {
			$listing_obj = Directory_Listing::get_instance()->check_source_for_listing( $source_id );
			if ( $listing_obj instanceof WP_Error ) {
				wp_send_json_error( array( 'message' => $listing_obj->get_error_message() ), 403 );
			}
		}

		// use the files, which are already in the media library.
		$ids = array();
		foreach ( $urls as $index => $url ) {
			$external_file_obj = Files::get_instance()->get_file_by_url( $url );
			if ( $external_file_obj instanceof File && $external_file_obj->is_valid() ) {
				$ids[] = $external_file_obj->get_id();
				unset( $urls[ $index ] );
			}
		}

		// import the others.
		$result = array(
			'imported_count' => 0,
			'imported'       => array(),
			'queued'         => 0,
			'errors'         => array(),
		);
		if ( ! empty( $urls ) ) {
			$input = array( 'urls' => array_values( $urls ) );
			if ( $source_id > 0 ) {
				$input['source_id'] = $source_id;
			}
			$result = Abilities::get_instance()->import( $input );
		}

		// bail on error.
		if ( $result instanceof WP_Error ) {
			wp_send_json_error( array( 'message' => wp_strip_all_tags( $result->get_error_message() ) ) );
		}

		// collect the IDs of the imported files.
		foreach ( is_array( $result['imported'] ) ? $result['imported'] : array() as $file ) {
			if ( is_array( $file ) && ! empty( $file['attachment_id'] ) ) {
				$ids[] = absint( $file['attachment_id'] );
			}
		}

		// collect the errors.
		$errors = array();
		foreach ( is_array( $result['errors'] ) ? $result['errors'] : array() as $error ) {
			if ( is_array( $error ) && ! empty( $error['message'] ) ) {
				$errors[] = wp_strip_all_tags( ( ! empty( $error['url'] ) ? $error['url'] . ': ' : '' ) . $error['message'] );
			}
		}

		// send the result.
		wp_send_json_success(
			array(
				'ids'    => array_values( array_unique( array_filter( $ids ) ) ),
				'queued' => absint( $result['queued'] ),
				'errors' => $errors,
			)
		);
	}

	/**
	 * Return the origin of the media dialog, which uses the page in the iframe, if it is allowed.
	 *
	 * @return string The origin, or the origin of the administration if the given one is not allowed.
	 */
	public function get_requested_origin(): string {
		// get the requested origin.
		$origin = isset( $_GET['origin'] ) && is_string( $_GET['origin'] ) ? strtolower( sanitize_text_field( wp_unslash( $_GET['origin'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only compared with the allowed origins.

		// return it, if it is allowed.
		if ( in_array( $origin, $this->get_allowed_origins(), true ) ) {
			return $origin;
		}

		// return the origin of the administration otherwise.
		return $this->get_origin( admin_url() );
	}

	/**
	 * Return the external sources, which the actual user could use in the directory listing.
	 *
	 * The same external sources as in the backend: those of the user (or all for administrators or if this is enabled
	 * in the settings), whose service the user may use.
	 *
	 * @return array<int,array{term: WP_Term, listing: Directory_Listing_Base}>
	 */
	private function get_sources(): array {
		$sources = array();
		foreach ( Directory_Listing::get_instance()->get_external_sources() as $term ) {
			// bail if the user could not use it.
			$listing_obj = Directory_Listing::get_instance()->check_source_for_listing( $term->term_id );
			if ( ! $listing_obj instanceof Directory_Listing_Base ) {
				continue;
			}

			// add it.
			$sources[] = array(
				'term'    => $term,
				'listing' => $listing_obj,
			);
		}

		// sort them by their service and their name.
		usort(
			$sources,
			static function ( array $a, array $b ): int {
				return strnatcasecmp( wp_strip_all_tags( $a['listing']->get_label() ) . ' ' . $a['term']->name, wp_strip_all_tags( $b['listing']->get_label() ) . ' ' . $b['term']->name );
			}
		);

		return $sources;
	}

	/**
	 * Show the page in the iframe of the tab "From external source": the list of the external sources of the actual
	 * user or the directory listing of one of them.
	 *
	 * @return void
	 */
	public function show_frame(): void {
		// mark this request as page in an iframe like WordPress does it for its own (e.g. media-upload.php): without
		// it, the page would get the space for the admin bar, which is not shown here.
		if ( ! defined( 'IFRAME_REQUEST' ) ) {
			define( 'IFRAME_REQUEST', true );
		}

		// output the page.
		$this->render_frame();
		exit;
	}

	/**
	 * Output the page in the iframe of the tab "From external source" (see show_frame()).
	 *
	 * Only for logged-in users with the capabilities for the media dialog: others get an error (403).
	 *
	 * @return void
	 */
	public function render_frame(): void {
		// bail if the user could not use it.
		if ( ! $this->is_allowed() ) {
			wp_die( esc_html__( 'You are not allowed to use external files here.', 'external-files-in-media-library' ), '', array( 'response' => 403 ) );
		}

		// get the requested external source and the origin of the media dialog.
		$source_id = isset( $_GET['source'] ) ? absint( wp_unslash( $_GET['source'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only, the permissions are checked for the external source.
		$origin    = $this->get_requested_origin();

		// get the configuration of the directory listing of the external source.
		$config = array();
		$error  = '';
		$title  = '';
		$icon   = '';
		if ( $source_id > 0 ) {
			$result = Directory_Listing::get_instance()->get_listing_config_for_source( $source_id );
			if ( $result instanceof WP_Error ) {
				$error = $result->get_error_message();
			} else {
				$config = $this->get_frame_listing_config( $result['config'] );
				$term   = get_term( $source_id );
				$title  = $term instanceof WP_Term ? $term->name : '';
				$icon   = Helper::get_service_icon( $result['listing']->get_name() );
			}
		}

		// prepare the page.
		global $hook_suffix;
		$hook_suffix = self::FRAME_HOOK; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The iframe page needs its own hook for its scripts.
		set_current_screen( self::FRAME_HOOK );

		// remember the configuration for the scripts.
		$this->frame = array(
			'source' => '' === $error ? $source_id : 0,
			'origin' => $origin,
		);

		// output the page.
		iframe_header( __( 'External sources', 'external-files-in-media-library' ) );
		?>
		<div class="efml-media-dialog-frame">
			<?php
			if ( $source_id > 0 ) {
				?>
				<p><a href="<?php echo esc_url( $this->get_frame_url( 0, $origin ) ); ?>">&larr; <?php echo esc_html__( 'All external sources', 'external-files-in-media-library' ); ?></a></p>
				<?php
				if ( '' !== $error ) {
					echo '<div class="notice notice-error inline"><p>' . esc_html( $error ) . '</p></div>';
				} else {
					?>
					<h2 class="efml-media-dialog-title"><?php echo wp_kses( $icon, Helper::get_allowed_html_for_icons() ); ?> <?php echo esc_html( $title ); ?></h2>
					<div id="easy-directory-listing-for-wordpress" data-config="<?php echo esc_attr( Directory_Listing::get_instance()->get_config_json( $config ) ); ?>"></div>
					<?php
				}
			} else {
				$this->show_frame_sources( $origin );
			}
			?>
			<div id="efml-media-dialog-status" aria-live="polite"></div>
		</div>
		<?php
		iframe_footer();
	}

	/**
	 * Show the list of external sources of the actual user in the iframe.
	 *
	 * @param string $origin The origin of the media dialog.
	 *
	 * @return void
	 */
	private function show_frame_sources( string $origin ): void {
		// get the external sources.
		$sources = $this->get_sources();

		// show a hint, if the user has none.
		if ( empty( $sources ) ) {
			?>
			<h2><?php echo esc_html__( 'No external source available', 'external-files-in-media-library' ); ?></h2>
			<p><?php echo esc_html__( 'You have no saved external source, which you could use here.', 'external-files-in-media-library' ); ?></p>
			<div class="efml-cards">
				<?php echo wp_kses( $this->get_add_card(), Helper::get_allowed_html_for_cards() ); ?>
			</div>
			<?php
			return;
		}

		// show the cards of the external sources.
		?>
		<h2><?php echo esc_html__( 'Choose an external source', 'external-files-in-media-library' ); ?></h2>
		<div class="efml-cards">
			<?php
			foreach ( $sources as $source ) {
				echo wp_kses( Directory_Listing::get_instance()->get_source_card( $source['term'], $source['listing'], $this->get_frame_url( $source['term']->term_id, $origin ) ), Helper::get_allowed_html_for_cards() );
			}
			echo wp_kses( $this->get_add_card(), Helper::get_allowed_html_for_cards() );
			?>
		</div>
		<?php
	}

	/**
	 * Return the card to add an external source (opens the list of services in a new window).
	 *
	 * @return string
	 */
	private function get_add_card(): string {
		return Helper::get_card(
			Directory_Listing::get_instance()->get_view_directory_url( false ),
			'<span class="efml-service-icon efml-service-icon-add" aria-hidden="true"></span>',
			__( 'Add an external source', 'external-files-in-media-library' ),
			__( 'Opens the list of services in a new window. This list is updated, when you come back.', 'external-files-in-media-library' ),
			array(
				'target' => '_blank',
				'class'  => 'efml-card-add',
			)
		);
	}

	/**
	 * Return the configuration of the directory listing for the iframe.
	 *
	 * Only the actions to import a file and to reload the listing are offered: saving, testing and importing whole
	 * directories stay in the list of external sources.
	 *
	 * @param array<string,mixed> $config The configuration of the directory listing.
	 *
	 * @return array<string,mixed>
	 */
	private function get_frame_listing_config( array $config ): array {
		// keep only the actions to import single files.
		$actions = array();
		foreach ( isset( $config['actions'] ) && is_array( $config['actions'] ) ? $config['actions'] : array() as $action ) {
			if ( is_array( $action ) && isset( $action['action'] ) && is_string( $action['action'] ) && str_contains( $action['action'], 'efml_get_import_dialog' ) ) {
				$actions[] = $action;
			}
		}
		$config['actions'] = $actions;

		// keep only the reload of the listing.
		$global_actions = array();
		foreach ( isset( $config['global_actions'] ) && is_array( $config['global_actions'] ) ? $config['global_actions'] : array() as $action ) {
			if ( is_array( $action ) && isset( $action['action'] ) && is_string( $action['action'] ) && str_starts_with( $action['action'], 'setTree(' ) ) {
				$global_actions[] = $action;
			}
		}
		$config['global_actions'] = $global_actions;

		// the directories are not saved as external source here.
		$config['archive'] = false;

		// return the configuration.
		return $config;
	}

	/**
	 * Add the scripts of the page in the iframe.
	 *
	 * @param string $hook The hook of the actual page.
	 *
	 * @return void
	 */
	public function add_frame_scripts( string $hook ): void {
		// bail if this is not the page in the iframe.
		if ( self::FRAME_HOOK !== $hook || empty( $this->frame ) ) {
			return;
		}

		// add the scripts of the directory listing, if an external source is shown.
		$dependencies = array();
		if ( $this->frame['source'] > 0 ) {
			Init::get_instance()->add_scripts( Init::get_instance()->get_page_hook() );
			if ( wp_script_is( 'easy-directory-listing-for-wordpress', 'enqueued' ) ) {
				$dependencies[] = 'easy-directory-listing-for-wordpress';
			}
		}

		// add the styles of this plugin and the logos of the services of extensions.
		Helper::enqueue_components_style();
		wp_enqueue_style(
			'eml-admin',
			Helper::get_plugin_url() . 'admin/style.css',
			array( Helper::register_components_style() ),
			Helper::get_file_version( Helper::get_plugin_dir() . 'admin/style.css' ),
		);

		// add the script.
		wp_enqueue_script(
			'efml-media-dialog-frame',
			Helper::get_plugin_url() . 'admin/media-dialog-frame.js',
			$dependencies,
			Helper::get_file_version( Helper::get_plugin_dir() . 'admin/media-dialog-frame.js' ),
			true
		);

		// add its configuration.
		wp_localize_script(
			'efml-media-dialog-frame',
			'efmlMediaDialogFrame',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( self::NONCE ),
				'source'        => $this->frame['source'],
				'target_origin' => $this->frame['origin'],
				'texts'         => array(
					'importing'       => __( 'The file is added …', 'external-files-in-media-library' ),
					'imported'        => __( 'The file has been added and selected in the media dialog.', 'external-files-in-media-library' ),
					'imported_window' => __( 'The file has been added and selected in the media dialog. You could close this window.', 'external-files-in-media-library' ),
					/* translators: %1$d will be replaced by a number. */
					'queued'          => __( '%1$d URLs are imported in the background. They appear in your media library afterward.', 'external-files-in-media-library' ),
					'error'           => __( 'The request failed. Please try again.', 'external-files-in-media-library' ),
				),
			)
		);
	}
}
