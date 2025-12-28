<?php
/**
 * This file contains an object which handles the admin tasks of this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Admin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Forms;
use ExternalFilesInMediaLibrary\ExternalFiles\Tables;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Intro;
use ExternalFilesInMediaLibrary\Plugin\Languages;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Settings;

/**
 * Initialize the admin tasks for this plugin.
 */
class Admin {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Admin
	 */
	private static ?Admin $instance = null;

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
	 * @return Admin
	 */
	public static function get_instance(): Admin {
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
		// initialize the backend forms for external files.
		Forms::get_instance()->init();

		// initialize the table extensions.
		Tables::get_instance()->init();

		// initialize the files object.
		Files::get_instance()->init();

		// initialize the help system.
		Help_System::get_instance()->init();

		// initialize the directory listing support.
		Directory_Listing::get_instance()->init();

		// initialize the intro.
		Intro::get_instance()->init();

		// add admin hooks.
		add_action( 'admin_enqueue_scripts', array( $this, 'add_dialog_scripts' ) );
		add_action( 'admin_init', array( $this, 'trigger_mime_warning' ) );
		add_action( 'admin_init', array( $this, 'check_php' ) );
		add_action( 'admin_init', array( $this, 'check_gprd' ) );
		add_action( 'admin_init', array( $this, 'check_fs_method' ) );
		add_filter( 'admin_body_class', array( $this, 'add_hide_review_hint' ) );
		add_action( 'admin_action_eml_empty_log', array( $this, 'empty_log' ) );
		add_action( 'admin_action_eml_log_delete_entry', array( $this, 'delete_log_entry' ) );
		add_action( 'admin_action_efml_hide_welcome', array( $this, 'hide_welcome_by_request' ) );
		add_action( 'init', array( $this, 'configure_transients' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_styles_and_js_admin' ), 10, 0 );

		// misc.
		add_filter( 'plugin_action_links_' . plugin_basename( EFML_PLUGIN ), array( $this, 'add_setting_link' ) );
		add_filter( 'plugin_row_meta', array( $this, 'add_row_meta_links' ), 10, 2 );
		add_filter( 'admin_footer_text', array( $this, 'show_plugin_hint_in_footer' ) );
		add_action( 'efml_directory_listing_added', array( $this, 'mark_directory_listing_as_used' ) );
		add_action( 'delete_' . Taxonomy::get_instance()->get_name(), array( $this, 'check_if_directory_listing_is_used' ) );

		// register our own importer in backend.
		add_action( 'admin_init', array( $this, 'add_importer' ) );
		add_action( 'load-importer-efml-importer', array( $this, 'forward_importer_to_settings' ) );
	}

	/**
	 * Checks on each admin-initialization.
	 *
	 * @return void
	 */
	public function trigger_mime_warning(): void {
		// bail if mime types are allowed.
		if ( ! empty( Helper::get_allowed_mime_types() ) ) {
			return;
		}

		// trigger warning as no mime types are allowed.
		$transients_obj = Transients::get_instance();
		$transient_obj  = $transients_obj->add();
		$transient_obj->set_dismissible_days( 14 );
		$transient_obj->set_name( 'eml_missing_mime_types' );
		/* translators: %1$s will be replaced by the URL for the plugin configuration. */
		$transient_obj->set_message( sprintf( __( '<strong>External files could not be used as no mime-types are allowed.</strong> Go to <a href="%1$s">Settings</a> to choose mime-types you want to use.', 'external-files-in-media-library' ), esc_url( Helper::get_config_url() ) ) );
		$transient_obj->set_type( 'error' );
		$transient_obj->save();
	}

	/**
	 * Add Easy Dialog for WP scripts in wp-admin.
	 */
	public function add_dialog_scripts(): void {
		// define paths: adjust if necessary.
		$path = trailingslashit( plugin_dir_path( EFML_PLUGIN ) ) . 'vendor/threadi/easy-dialog-for-wordpress/';
		$url  = trailingslashit( plugin_dir_url( EFML_PLUGIN ) ) . 'vendor/threadi/easy-dialog-for-wordpress/';

		// bail if path does not exist.
		if ( ! file_exists( $path ) ) {
			return;
		}

		// embed the dialog-components JS-script.
		$script_asset_path = $path . 'build/index.asset.php';

		// bail if file does not exist.
		if ( ! file_exists( $script_asset_path ) ) {
			return;
		}

		$script_asset = require $script_asset_path;
		wp_enqueue_script(
			'easy-dialog-for-wordpress',
			$url . 'build/index.js',
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		// embed the dialog-components CSS-script.
		$admin_css      = $url . 'build/style-index.css';
		$admin_css_path = $path . 'build/style-index.css';
		wp_enqueue_style(
			'easy-dialog-for-wordpress',
			$admin_css,
			array( 'wp-components' ),
			Helper::get_file_version( $admin_css_path )
		);
	}

	/**
	 * Check if website is using PHP 8.1 or older and warn about it.
	 *
	 * @return void
	 */
	public function check_php(): void {
		// get transients object.
		$transients_obj = Transients::get_instance();

		// bail if PHP >= 8.2 is used.
		if ( PHP_VERSION_ID > 80200 ) {
			$transients_obj->get_transient_by_name( 'eml_php_hint' )->delete();
			return;
		}

		// bail if WordPress is in developer mode.
		if ( Helper::is_development_mode() ) {
			$transients_obj->get_transient_by_name( 'eml_php_hint' )->delete();
			return;
		}

		// show hint for old PHP-version.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_type( 'error' );
		$transient_obj->set_name( 'eml_php_hint' );
		$transient_obj->set_dismissible_days( 90 );
		$transient_obj->set_message( '<strong>' . __( 'Your website is using an old PHP-version!', 'external-files-in-media-library' ) . '</strong><br>' . __( 'Future versions of <i>External Files in Media Library</i> will no longer be compatible with PHP 8.1 or older. These versions <a href="https://www.php.net/supported-versions.php" target="_blank">will be outdated</a> after December 2025. To continue using the plugins new features, please update your PHP version.', 'external-files-in-media-library' ) . '<br>' . __( 'Talk to your hosting support team about this.', 'external-files-in-media-library' ) );
		$transient_obj->save();
	}

	/**
	 * Add link to settings and adding files in plugin list.
	 *
	 * @param array<string> $links List of links.
	 *
	 * @return array<string>
	 */
	public function add_setting_link( array $links ): array {
		// add link to settings.
		$links[] = "<a href='" . esc_url( Helper::get_config_url() ) . "'>" . __( 'Settings', 'external-files-in-media-library' ) . '</a>';

		// add link to add media.
		$links[] = "<a href='" . esc_url( Helper::get_add_media_url() ) . "' style='font-weight: bold'>" . __( 'Add external files', 'external-files-in-media-library' ) . '</a>';

		// return resulting list of links.
		return $links;
	}

	/**
	 * Add links in plugin lists row meta.
	 *
	 * @param array<string> $links List of links.
	 * @param string        $file The requested plugin file name.
	 *
	 * @return array<string>
	 */
	public function add_row_meta_links( array $links, string $file ): array {
		// bail if this is not our plugin.
		if ( EFML_PLUGIN !== WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $file ) {
			return $links;
		}

		// add our custom links.
		$row_meta = array(
			'support' => '<a href="' . esc_url( Helper::get_plugin_support_url() ) . '" target="_blank" title="' . esc_attr__( 'Support Forum', 'external-files-in-media-library' ) . '">' . esc_html__( 'Support Forum', 'external-files-in-media-library' ) . '</a>',
			'review'  => '<a href="' . esc_url( Helper::get_plugin_review_url() ) . '" target="_blank" title="' . esc_attr__( 'Add your review', 'external-files-in-media-library' ) . '" class="efml-review"><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span></a>',
		);

		// show deprecated warning for old hook name.
		$row_meta = apply_filters_deprecated( 'eml_plugin_row_meta', array( $row_meta ), '5.0.0', 'efml_plugin_row_meta' );

		/**
		 * Filter the links in row meta of our plugin in plugin list.
		 *
		 * @since 3.1.0 Available since 3.1.0.
		 * @param array $row_meta List of links.
		 */
		$row_meta = apply_filters( 'efml_plugin_row_meta', $row_meta );

		// return the resulting list of links.
		return array_merge( $links, $row_meta );
	}

	/**
	 * Empty the log per request.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function empty_log(): void {
		// check the nonce.
		check_admin_referer( 'eml-empty-log', 'nonce' );

		// empty the table.
		Log::get_instance()->truncate_log();

		// show ok message.
		$transients_obj = Transients::get_instance();
		$transient_obj  = $transients_obj->add();
		$transient_obj->set_name( 'eml_log_emptied' );
		$transient_obj->set_message( '<strong>' . __( 'The log has been deleted.', 'external-files-in-media-library' ) . '</strong>' );
		$transient_obj->set_type( 'success' );
		$transient_obj->save();

		// redirect user.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Delete single log entry.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function delete_log_entry(): void {
		// check the nonce.
		check_admin_referer( 'eml-log-delete-entry', 'nonce' );

		// get the ID from request.
		$id = absint( filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT ) );

		// empty the table.
		Log::get_instance()->delete_log( $id );

		// show ok message.
		$transients_obj = Transients::get_instance();
		$transient_obj  = $transients_obj->add();
		$transient_obj->set_name( 'eml_log_entry_deleted' );
		$transient_obj->set_message( '<strong>' . __( 'The log entry has been deleted.', 'external-files-in-media-library' ) . '</strong>' );
		$transient_obj->set_type( 'success' );
		$transient_obj->save();

		// redirect user.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Check if website is using a language which _might_ be underlying the GPRD in germany.
	 *
	 * Show a warning hint if it is the case.
	 *
	 * @return void
	 */
	public function check_gprd(): void {
		// get transients object.
		$transients_obj = Transients::get_instance();

		// bail if setting to hide this hint is enabled.
		if ( 1 === absint( get_option( 'eml_disable_gprd_warning' ) ) ) {
			$transients_obj->get_transient_by_name( 'eml_gprd_hint' )->delete();
			return;
		}

		// bail if language is not german.
		if ( ! Languages::get_instance()->is_german_language() ) {
			$transients_obj->get_transient_by_name( 'eml_gprd_hint' )->delete();
			return;
		}

		// bail if WordPress is in developer mode.
		if ( function_exists( 'wp_is_development_mode' ) && wp_is_development_mode( 'plugin' ) ) {
			$transients_obj->get_transient_by_name( 'eml_gprd_hint' )->delete();
			return;
		}

		// show hint for GPRD.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_type( 'error' );
		$transient_obj->set_name( 'eml_gprd_hint' );
		$transient_obj->set_dismissible_days( 180 );
		/* translators: %1$s will be replaced by a URL. */
		$transient_obj->set_message( '<strong>' . sprintf( __( 'Your website seems to be subject to the European Union rules of the <a href="%1$s" target="_blank">GPRD (opens new window)</a>!', 'external-files-in-media-library' ), esc_url( Helper::get_gprd_url() ) ) . '</strong><br><br>' . __( 'Please note that according to these rules, the use of external, directly loaded files (such as images or videos) in a website requires active information to the visitor before these files are loaded. We recommend that you use the proxy mode offered when using <i>External Files in Media Library</i>. This means that the files are not loaded directly from an external source but are cached locally. If you have any further questions about these rules, please contact your legal advisor.', 'external-files-in-media-library' ) . '<br><br>' . sprintf( __( 'The above-mentioned detection is based on the language you use in WordPress. If you are not affected by the GPRD-rules, we apologize for this information. You can hide it at any time <a href="%1$s">by click on this link</a>.', 'external-files-in-media-library' ), esc_url( Settings::get_instance()->disable_gprd_hint_url() ) ) );
		$transient_obj->save();
	}

	/**
	 * Check if constant "FS_METHOD" is set and if yes, it its configuration is complete.
	 *
	 * E.g. for the value ftpext we need also the constant "FS_CHMOD_FILE" to prevent errors.
	 *
	 * @return void
	 */
	public function check_fs_method(): void {
		// bail if FS_METHOD is not set.
		if ( ! defined( 'FS_METHOD' ) ) {
			return;
		}

		// if it has the value "ftpext" we also need "FS_CHMOD_FILE".
		if ( ! defined( 'FS_CHMOD_FILE' ) && 'ftpext' === get_filesystem_method() ) {
			// show warning.
			$transient_obj = Transients::get_instance()->add();
			$transient_obj->set_type( 'error' );
			$transient_obj->set_name( 'eml_fs_method_faulty' );
			$transient_obj->set_message( '<strong>' . __( 'Your website is using an incorrect file system setting!', 'external-files-in-media-library' ) . '</strong><br><br>' . __( 'The constant <em>FS_CHMOD_FILE</em> is set to <em>ftpext</em>. At the same time, the constant <em>FS_CHMOD_FILE</em> is missing. This means that you will not be able to save files to your media library with the plugin <i>External Files in Media Library</i>.<br><br>Correct this by editing the file <em>wp-config.php</em> of your WordPress project. If you have any questions, please contact your web administrator or your hosts support team.', 'external-files-in-media-library' ) );
			$transient_obj->save();
			return;
		}

		// if value is not "direct" show hint.
		if ( 'direct' !== get_filesystem_method() ) {
			// show hint.
			$transient_obj = Transients::get_instance()->add();
			$transient_obj->set_dismissible_days( 30 );
			$transient_obj->set_type( 'hint' );
			$transient_obj->set_name( 'eml_fs_method_faulty' );
			$transient_obj->set_message( '<strong>' . __( 'Your website is running in possible faulty file system mode!', 'external-files-in-media-library' ) . '</strong><br><br>' . __( 'The constant <em>FS_CHMOD_FILE</em> is set. This could lead to unexpected behaviours during the usage of the plugin <i>External Files in Media Library</i>.<br><br>Remove this mode by editing the file <em>wp-config.php</em> of your WordPress project. If you have any questions, please contact your web administrator or your hosts support team.', 'external-files-in-media-library' ) );
			$transient_obj->save();
			return;
		}
	}

	/**
	 * Add custom importer for positions under Tools > Import.
	 *
	 * @return void
	 */
	public function add_importer(): void {
		// bail if user has not the capability for it.
		if ( ! current_user_can( EFML_CAP_NAME ) ) {
			return;
		}

		register_importer(
			'efml-importer',
			__( 'External files for Media Library', 'external-files-in-media-library' ),
			__( 'Import of external files in your media library.', 'external-files-in-media-library' ),
			'__return_true'
		);
	}

	/**
	 * Forward user to settings-page.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function forward_importer_to_settings(): void {
		wp_safe_redirect( Helper::get_add_media_url() );
		exit;
	}

	/**
	 * Show hint in footer in backend on listing and single view of positions there.
	 *
	 * @param string $content The actual footer content.
	 *
	 * @return string
	 */
	public function show_plugin_hint_in_footer( string $content ): string {
		global $pagenow;

		// get requested page.
		$page = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// show specific text on media pages.
		if ( 'efml_local_directories' !== $page && 'eml_settings' !== $page && in_array( $pagenow, array( 'media-new.php', 'upload.php' ), true ) ) {
			// show hint for our plugin.
			/* translators: %1$s will be replaced by the plugin name. */
			return $content . ' ' . sprintf( __( 'This page has been expanded by the plugin %1$s.', 'external-files-in-media-library' ), '<em>' . Helper::get_plugin_name() . '</em>' );
		}

		// get requested taxonomy.
		$post_type = filter_input( INPUT_GET, 'taxonomy', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if this is not the listing or our page.
		if ( 'efml_local_directories' !== $page && 'eml_settings' !== $page && Taxonomy::get_instance()->get_name() !== $post_type ) {
			return $content;
		}

		// show hint for our plugin.
		/* translators: %1$s will be replaced by the plugin name. */
		return $content . ' ' . sprintf( __( 'This page is provided by the plugin %1$s.', 'external-files-in-media-library' ), '<em>' . Helper::get_plugin_name() . '</em>' );
	}

	/**
	 * Set base configuration for each transient.
	 *
	 * @return void
	 */
	public function configure_transients(): void {
		$transients_obj = Transients::get_instance();
		$transients_obj->set_slug( 'efml' );
		$transients_obj->set_url( Helper::get_plugin_url() . 'app/Dependencies/easyTransientsForWordPress/' );
		$transients_obj->set_path( Helper::get_plugin_path() . 'app/Dependencies/easyTransientsForWordPress/' );
		$transients_obj->set_capability( 'manage_options' );
		$transients_obj->set_template( 'grouped.php' );
		$transients_obj->set_display_method( 'grouped' );
		$transients_obj->set_translations(
			array(
				/* translators: %1$d will be replaced by the days this message will be hidden. */
				'hide_message' => __( 'Hide this message for %1$d days.', 'external-files-in-media-library' ),
				'dismiss'      => __( 'Dismiss', 'external-files-in-media-library' ),
			)
		);
		$transients_obj->init();
	}

	/**
	 * Hide welcome hint by request and forward user to target.
	 *
	 * Hint: we do not use a nonce here as this might also result from installing via WP CLI,
	 * which as no WP-user env.
	 *
	 * @return void
	 */
	public function hide_welcome_by_request(): void {
		// dismiss the welcome hint.
		Transients::get_instance()->get_transient_by_name( 'eml_welcome' )->add_dismiss( 365 );
		Transients::get_instance()->get_transient_by_name( 'eml_welcome' )->delete();

		// get URL from request.
		$url = filter_input( INPUT_GET, 'forward', FILTER_SANITIZE_URL );
		if ( is_null( $url ) ) {
			$url = (string) wp_get_referer();
		}

		// redirect the user.
		wp_safe_redirect( $url );
	}

	/**
	 * Enabled to play a sound if import finishes.
	 *
	 * @param string $classes List of classes as string.
	 *
	 * @return string
	 */
	public function add_hide_review_hint( string $classes ): string {
		// bail if setting is not enabled.
		if ( 1 !== absint( get_option( 'eml_hide_begging_for_review' ) ) ) {
			return $classes;
		}

		// add the class.
		$classes .= ' efml-hide-review-hint';

		// return resulting list of classes.
		return $classes;
	}

	/**
	 * Mark directory listing as used.
	 *
	 * This is a small cache to prevent database requests for directory listing terms.
	 *
	 * @return void
	 */
	public function mark_directory_listing_as_used(): void {
		update_option( 'efml_directory_listing_used', time() );
	}

	/**
	 * Check if directory listing is used if a term has been deleted.
	 *
	 * @return void
	 */
	public function check_if_directory_listing_is_used(): void {
		// get the terms.
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomy::get_instance()->get_name(),
				'hide_empty' => false,
			)
		);

		// bail if terms does exist.
		if ( ! empty( $terms ) ) {
			return;
		}

		// reset the marker.
		update_option( 'efml_directory_listing_used', 0 );
	}

	/**
	 * Add CSS- and JS-files for plugin listing in backend.
	 *
	 * @return void
	 */
	public function add_styles_and_js_admin(): void {
		global $pagenow;

		// bail if we are not on "plugins.php".
		if ( 'plugins.php' !== $pagenow ) {
			return;
		}

		// admin-specific styles.
		wp_enqueue_style(
			'eml-public-admin',
			Helper::get_plugin_url() . 'admin/public.css',
			array(),
			Helper::get_file_version( Helper::get_plugin_dir() . 'admin/public.css' ),
		);
	}
}
