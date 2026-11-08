<?php
/**
 * File to detect copies of this website (e.g. staging sites).
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;

/**
 * Object to detect copies of this website (e.g. staging sites) and to protect the external sources from them.
 *
 * The website saves a fingerprint of itself (its normalized URL and its environment type). If the URL of the
 * website changes, or the website saved as production is now used as staging or development, it is a copy.
 *
 * On a copy:
 * - files are not exported to external sources,
 * - exported files are not deleted on external sources,
 * - scheduled synchronizations are paused (manual synchronizations are possible).
 *
 * Reading, delivering and importing of files are not affected.
 */
class Site_Copy {
	/**
	 * The name of the option with the fingerprint of the main website.
	 *
	 * @var string
	 */
	private const OPTION_FINGERPRINT = 'efml_site_fingerprint';

	/**
	 * The name of the option with the fingerprint of the copy, which has been confirmed as copy.
	 *
	 * @var string
	 */
	private const OPTION_CONFIRMED_COPY = 'efml_site_copy_confirmed';

	/**
	 * The name of the transient for the hint in the backend.
	 *
	 * @var string
	 */
	private const TRANSIENT_HINT = 'efml_site_copy';

	/**
	 * The environment types of non-production websites.
	 *
	 * @var array<int,string>
	 */
	private const NON_PRODUCTION_ENVIRONMENTS = array( 'staging', 'development', 'local' );

	/**
	 * Marker whether a blocked action has already been logged in this request.
	 *
	 * @var bool
	 */
	private bool $blocked_action_logged = false;

	/**
	 * Instance of actual object.
	 *
	 * @var ?Site_Copy
	 */
	private static ?Site_Copy $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	protected function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Site_Copy
	 */
	public static function get_instance(): Site_Copy {
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
		// pause scheduled synchronizations on copies.
		add_filter( 'efml_prevent_sync', array( $this, 'prevent_scheduled_sync' ), 10, 2 );

		// show the hint in the backend.
		add_action( 'admin_init', array( $this, 'add_hint' ) );

		// add the actions for the hint.
		add_action( 'admin_action_efml_site_is_main', array( $this, 'set_as_main_by_request' ) );
		add_action( 'admin_action_efml_site_is_copy', array( $this, 'confirm_copy_by_request' ) );
	}

	/**
	 * Return the fingerprint of the actual website: the hash of its normalized URL.
	 *
	 * The URL is used without scheme and without trailing slash, so a change from HTTP to HTTPS is not a copy.
	 *
	 * @return string
	 */
	public function get_current_fingerprint(): string {
		// get the URL.
		$url = strtolower( untrailingslashit( (string) preg_replace( '/^https?:\/\//i', '', trim( home_url() ) ) ) );

		// return the hash.
		return hash( 'sha256', $url );
	}

	/**
	 * Return the environment type of the actual website.
	 *
	 * @return string
	 */
	protected function get_current_environment(): string {
		return wp_get_environment_type();
	}

	/**
	 * Return the saved fingerprint of the main website.
	 *
	 * If no fingerprint is saved, the actual website is saved as main website (without any question).
	 *
	 * @return array<string,string>
	 */
	public function get_saved_fingerprint(): array {
		// get the saved fingerprint.
		$fingerprint = get_option( self::OPTION_FINGERPRINT );

		// save the actual website as main website, if no fingerprint is saved.
		if ( ! is_array( $fingerprint ) || empty( $fingerprint['hash'] ) ) {
			return $this->save_fingerprint();
		}

		// return the saved fingerprint.
		return array(
			'hash'        => (string) $fingerprint['hash'],
			'environment' => isset( $fingerprint['environment'] ) ? (string) $fingerprint['environment'] : '',
		);
	}

	/**
	 * Save the actual website as main website.
	 *
	 * @return array<string,string> The saved fingerprint.
	 */
	public function set_as_main(): array {
		// save the fingerprint.
		$fingerprint = $this->save_fingerprint();

		// remove the confirmation as copy.
		delete_option( self::OPTION_CONFIRMED_COPY );

		// remove the hint.
		$this->delete_hint();

		// return the fingerprint.
		return $fingerprint;
	}

	/**
	 * Save the fingerprint of the actual website.
	 *
	 * @return array<string,string> The saved fingerprint.
	 */
	private function save_fingerprint(): array {
		// create the fingerprint.
		$fingerprint = array(
			'hash'        => $this->get_current_fingerprint(),
			'environment' => $this->get_current_environment(),
		);

		// save it.
		update_option( self::OPTION_FINGERPRINT, $fingerprint, true );

		// return the fingerprint.
		return $fingerprint;
	}

	/**
	 * Confirm the actual website as copy: the protection stays active, the hint is not shown anymore.
	 *
	 * @return void
	 */
	public function confirm_copy(): void {
		update_option( self::OPTION_CONFIRMED_COPY, $this->get_current_fingerprint(), true );

		// remove the hint.
		$this->delete_hint();
	}

	/**
	 * Return whether the actual website has been confirmed as copy.
	 *
	 * @return bool
	 */
	public function is_copy_confirmed(): bool {
		return get_option( self::OPTION_CONFIRMED_COPY ) === $this->get_current_fingerprint();
	}

	/**
	 * Return whether the actual website is a copy of the main website.
	 *
	 * @return bool
	 */
	public function is_copy(): bool {
		// get the saved fingerprint.
		$fingerprint = $this->get_saved_fingerprint();

		// it is a copy, if the URL is another one.
		$is_copy = $fingerprint['hash'] !== $this->get_current_fingerprint();

		// it is also a copy, if the main website is a production website and this website is not.
		if ( ! $is_copy && 'production' === $fingerprint['environment'] && in_array( $this->get_current_environment(), self::NON_PRODUCTION_ENVIRONMENTS, true ) ) {
			$is_copy = true;
		}

		/**
		 * Filter whether the actual website is a copy of the main website (e.g. a staging site).
		 *
		 * On a copy, files are not exported to or deleted on external sources, and scheduled
		 * synchronizations are paused.
		 *
		 * @since 5.7.0 Available since 5.7.0.
		 * @param bool $is_copy True if the website is a copy.
		 * @param array<string,string> $fingerprint The saved fingerprint of the main website.
		 */
		return (bool) apply_filters( 'efml_is_website_copy', $is_copy, $fingerprint );
	}

	/**
	 * Return whether actions on external sources are blocked, and log it once per request if they are.
	 *
	 * @param string $message The message for the log.
	 * @param string $url     The URL for the log.
	 *
	 * @return bool
	 */
	public function is_blocked( string $message, string $url = '' ): bool {
		// bail if this is not a copy.
		if ( ! $this->is_copy() ) {
			return false;
		}

		// log the event, the first one with the normal level, the following only in the extended log.
		/* translators: %1$s will be replaced by a URL. */
		Log::get_instance()->create( $message . ' ' . sprintf( __( 'This website (%1$s) is a copy of another website (e.g. a staging site), so the external sources are protected from changes.', 'external-files-in-media-library' ), '<em>' . esc_html( home_url() ) . '</em>' ), $url, 'info', $this->blocked_action_logged ? 2 : 0 );
		$this->blocked_action_logged = true;

		// return true as the action is blocked.
		return true;
	}

	/**
	 * Pause scheduled synchronizations on copies. Manual synchronizations are still possible.
	 *
	 * @param bool   $prevent True to prevent the synchronization.
	 * @param string $url     The URL to synchronize.
	 *
	 * @return bool
	 */
	public function prevent_scheduled_sync( bool $prevent, string $url ): bool {
		// bail if it is already prevented.
		if ( $prevent ) {
			return true;
		}

		// bail if this is not a scheduled synchronization.
		if ( ! wp_doing_cron() ) {
			return false;
		}

		// return whether the synchronization is blocked.
		return $this->is_blocked( __( 'Scheduled synchronization has been paused.', 'external-files-in-media-library' ), $url );
	}

	/**
	 * Add the hint in the backend via transient, if this website is a copy and has not been confirmed as copy.
	 *
	 * The hint is shown until the website is set as main website or confirmed as copy.
	 *
	 * @return void
	 */
	public function add_hint(): void {
		// bail if user has no permission to change it.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// remove the hint, if this is not a copy or it has been confirmed.
		if ( ! $this->is_copy() || $this->is_copy_confirmed() ) {
			$this->delete_hint();
			return;
		}

		// create the message.
		$message  = '<strong>' . __( 'This website looks like a copy of another website.', 'external-files-in-media-library' ) . '</strong> ';
		$message .= __( 'Its address or its environment has been changed, e.g. because it is a staging site. To protect your external sources, files are not exported to them and not deleted on them, and scheduled synchronizations are paused. Reading and delivering files is not affected.', 'external-files-in-media-library' ) . '<br><br>';
		$message .= '<a href="' . esc_url( $this->get_action_url( 'efml_site_is_main' ) ) . '" class="button button-primary">' . __( 'This website is now the main website', 'external-files-in-media-library' ) . '</a> ';
		$message .= '<a href="' . esc_url( $this->get_action_url( 'efml_site_is_copy' ) ) . '" class="button button-secondary">' . __( 'This website is a copy', 'external-files-in-media-library' ) . '</a>';

		// add the hint.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_name( self::TRANSIENT_HINT );
		$transient_obj->set_message( $message );
		$transient_obj->set_type( 'error' );
		$transient_obj->set_prioritized( true );
		$transient_obj->save();
	}

	/**
	 * Delete the hint in the backend.
	 *
	 * @return void
	 */
	private function delete_hint(): void {
		// get the transient.
		$transient_obj = Transients::get_instance()->get_transient_by_name( self::TRANSIENT_HINT );

		// bail if it is not set.
		if ( ! $transient_obj->is_set() ) {
			return;
		}

		// delete it.
		$transient_obj->delete();
	}

	/**
	 * Return the hint for the settings, if this website is a copy. Returns an empty string otherwise.
	 *
	 * @return string
	 */
	public function get_settings_hint(): string {
		// bail if this is not a copy.
		if ( ! $this->is_copy() ) {
			return '';
		}

		// create the hint.
		$hint = '<strong>' . esc_html__( 'This website is a copy of another website (e.g. a staging site): files are not exported and not deleted on external sources, scheduled synchronizations are paused.', 'external-files-in-media-library' ) . '</strong>';

		// add the link to change it, if the user is allowed to.
		if ( current_user_can( 'manage_options' ) ) {
			$hint .= ' <a href="' . esc_url( $this->get_action_url( 'efml_site_is_main' ) ) . '">' . esc_html__( 'This website is now the main website', 'external-files-in-media-library' ) . '</a>';
		}

		// return the hint.
		return $hint;
	}

	/**
	 * Return the URL for the given action.
	 *
	 * @param string $action The action.
	 *
	 * @return string
	 */
	private function get_action_url( string $action ): string {
		return add_query_arg(
			array(
				'action' => $action,
				'nonce'  => wp_create_nonce( $action ),
			),
			get_admin_url() . 'admin.php'
		);
	}

	/**
	 * Save the actual website as main website by request.
	 *
	 * @return void
	 */
	public function set_as_main_by_request(): void {
		// check the request.
		$this->check_request( 'efml_site_is_main' );

		// save this website as main website.
		$this->set_as_main();

		// log this event.
		/* translators: %1$s will be replaced by a URL. */
		Log::get_instance()->create( sprintf( __( 'The website %1$s has been set as main website.', 'external-files-in-media-library' ), '<em>' . esc_html( home_url() ) . '</em>' ), '', 'success' );

		// forward the user.
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : get_admin_url() );
		exit;
	}

	/**
	 * Confirm the actual website as copy by request.
	 *
	 * @return void
	 */
	public function confirm_copy_by_request(): void {
		// check the request.
		$this->check_request( 'efml_site_is_copy' );

		// confirm this website as copy.
		$this->confirm_copy();

		// forward the user.
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : get_admin_url() );
		exit;
	}

	/**
	 * Check the nonce and the permission for the given action.
	 *
	 * @param string $action The action.
	 *
	 * @return void
	 */
	private function check_request( string $action ): void {
		// check nonce.
		check_admin_referer( $action, 'nonce' );

		// bail if user has no permission.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'external-files-in-media-library' ) );
		}
	}
}
