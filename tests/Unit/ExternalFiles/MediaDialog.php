<?php
/**
 * Tests for the functions of this plugin in the media dialog.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Media_Dialog;
use ExternalFilesInMediaLibrary\ExternalFiles\Tools;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use WPAjaxDieContinueException;
use WP_Error;

/**
 * Object to test the functions in the media dialog.
 */
class MediaDialog extends externalFilesTests {
	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// use an administrator with the capabilities of the plugin and of the media dialog.
		wp_set_current_user( $this->get_user( 'administrator', true ) );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		global $pagenow;
		$pagenow = 'index.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		wp_dequeue_script( 'efml-media-dialog' );
		wp_dequeue_script( 'efml-media-filter' );
		wp_deregister_script( 'efml-media-dialog' );
		wp_deregister_script( 'efml-media-filter' );
		wp_dequeue_style( 'efml-components' );
		unset( $_POST['nonce'], $_POST['urls'], $_POST['source'], $_REQUEST['nonce'], $_GET['origin'] );
		remove_all_filters( 'wp_die_ajax_handler' );
		remove_filter( 'wp_doing_ajax', '__return_true' );
		remove_filter( 'efml_file_base', array( $this, 'set_plugin_path_as_base' ) );
		parent::tear_down();
	}

	/**
	 * Create a user with the given role and return its ID.
	 *
	 * @param string $role        The role.
	 * @param bool   $media_dialog True to give the user the capabilities for the media dialog.
	 *
	 * @return int
	 */
	private function get_user( string $role, bool $media_dialog ): int {
		$user_id = self::factory()->user->create( array( 'role' => $role ) );
		$user    = get_user_by( 'id', $user_id );
		$this->assertInstanceOf( \WP_User::class, $user );
		if ( $media_dialog ) {
			$user->add_cap( EFML_CAP_NAME );
			$user->add_cap( Media_Dialog::CAPABILITY );
			$user->add_cap( 'efml_cap_local' );
		} else {
			$user->add_cap( Media_Dialog::CAPABILITY, false );
		}
		return $user_id;
	}

	/**
	 * Run an AJAX request of the media dialog and return its JSON.
	 *
	 * @param callable            $callback The callback.
	 * @param array<string,mixed> $values   The values of the request.
	 *
	 * @return array<string,mixed>
	 */
	private function run_ajax( callable $callback, array $values ): array {
		// set the values.
		foreach ( $values as $name => $value ) {
			$_POST[ $name ]    = $value;
			$_REQUEST[ $name ] = $value;
		}

		// stop at the end of the request instead of exit.
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function ( $message ) {
					throw new WPAjaxDieContinueException( is_scalar( $message ) ? (string) $message : '' );
				};
			}
		);

		// run the request.
		ob_start();
		try {
			$callback();
		} catch ( WPAjaxDieContinueException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// the request has been finished.
		}
		$output = (string) ob_get_clean();

		// return the result.
		$result = json_decode( $output, true );
		$this->assertIsArray( $result, 'No JSON returned: ' . $output );
		return $result;
	}

	/**
	 * Test the tool with its capability, which is chosen per role in the settings.
	 *
	 * @return void
	 */
	public function test_tool(): void {
		$this->assertContains( Media_Dialog::get_instance(), Tools::get_instance()->get_tools_as_objects() );
		$this->assertTrue( Media_Dialog::get_instance()->has_capability() );
		$this->assertSame( 'efml_cap_tools_' . Media_Dialog::get_instance()->get_name(), Media_Dialog::CAPABILITY );
		$this->assertSame( array( 'administrator', 'editor' ), Media_Dialog::get_instance()->get_capability_default() );

		// the update gives the roles the capability.
		$role = get_role( 'editor' );
		$this->assertInstanceOf( \WP_Role::class, $role );
		$role->remove_cap( Media_Dialog::CAPABILITY );
		delete_option( 'eml_tools_settings_tools_media_dialog_allowed_roles' );
		\ExternalFilesInMediaLibrary\Plugin\Update::get_instance()->add_media_dialog_capability();
		$this->assertTrue( get_role( 'editor' )->has_cap( Media_Dialog::CAPABILITY ) );
		$this->assertSame( array( 'administrator', 'editor' ), get_option( 'eml_tools_settings_tools_media_dialog_allowed_roles' ) );
	}

	/**
	 * Test that the capability is given to the roles, if the setting with the roles is saved with its default value.
	 *
	 * This happens without any update routine, e.g. in development mode: the settings showed the roles as chosen, but
	 * they did not get the capability, so the media dialog stayed unchanged.
	 *
	 * @return void
	 */
	public function test_capability_with_missing_setting(): void {
		$option = 'eml_tools_settings_tools_media_dialog_allowed_roles';

		// the state of a site, which got the new tool without update routine.
		$role = get_role( 'editor' );
		$this->assertInstanceOf( \WP_Role::class, $role );
		$role->remove_cap( Media_Dialog::CAPABILITY );
		delete_option( $option );
		$checked = get_option( \ExternalFilesInMediaLibrary\Plugin\Settings::DEFAULTS_OPTION, array() );
		if ( is_array( $checked ) ) {
			unset( $checked[ $option ] );
			update_option( \ExternalFilesInMediaLibrary\Plugin\Settings::DEFAULTS_OPTION, $checked, true );
		}
		wp_cache_delete( 'alloptions', 'options' );

		// save the missing settings like on each request.
		\ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->save_missing_defaults();

		// the setting and the capability are saved.
		$this->assertSame( array( 'administrator', 'editor' ), get_option( $option ) );
		$this->assertTrue( get_role( 'editor' )->has_cap( Media_Dialog::CAPABILITY ) );
		$this->assertTrue( get_role( 'administrator' )->has_cap( Media_Dialog::CAPABILITY ) );
	}

	/**
	 * Test that the scripts are only added for users with the capability and without any query.
	 *
	 * @return void
	 */
	public function test_scripts(): void {
		global $wpdb, $pagenow;

		// with the capability: both scripts, without any query, and extensions could add the logos of their services.
		$logos = did_action( 'efml_enqueue_service_logos' );
		$queries = $wpdb->num_queries;
		Media_Dialog::get_instance()->add_scripts();
		$this->assertSame( $queries, $wpdb->num_queries );
		$this->assertSame( $logos + 1, did_action( 'efml_enqueue_service_logos' ) );
		$this->assertTrue( wp_script_is( 'efml-media-dialog', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'efml-media-filter', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'efml-components', 'enqueued' ) );

		// the filter has only the options, which need no query, and loads the others.
		$data = (string) wp_scripts()->get_data( 'efml-media-filter', 'data' );
		$this->assertStringContainsString( '"non-external"', $data );
		$this->assertStringContainsString( 'admin-ajax.php', $data );

		// not the filter in the media library, which has it already.
		wp_dequeue_script( 'efml-media-filter' );
		wp_deregister_script( 'efml-media-filter' );
		$pagenow = 'upload.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		Media_Dialog::get_instance()->add_scripts();
		$this->assertFalse( wp_script_is( 'efml-media-filter', 'enqueued' ) );
		$pagenow = 'index.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		// nothing without the capability.
		wp_dequeue_script( 'efml-media-dialog' );
		wp_deregister_script( 'efml-media-dialog' );
		wp_set_current_user( $this->get_user( 'administrator', false ) );
		Media_Dialog::get_instance()->add_scripts();
		$this->assertFalse( wp_script_is( 'efml-media-dialog', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'efml-media-filter', 'enqueued' ) );
	}

	/**
	 * Test the mark of external files.
	 *
	 * @return void
	 */
	public function test_mark(): void {
		// a local file has no mark.
		$local_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/Data/example.pdf' );
		$this->assertArrayNotHasKey( 'efml_mark', (array) wp_prepare_attachment_for_js( $local_id ) );

		// an external file.
		$external_id = self::factory()->attachment->create( array( 'post_mime_type' => 'application/pdf' ) );
		Files::get_instance()->get_file( $external_id )->set_url( 'https://example.com/example.pdf' );
		$this->assertSame( 'external', wp_prepare_attachment_for_js( $external_id )['efml_mark'] );

		// with the logo and the name of its service.
		Files::get_instance()->get_file( $external_id )->set_service_name( 'dropbox' );
		$data    = wp_prepare_attachment_for_js( $external_id );
		$service = \ExternalFilesInMediaLibrary\Services\Services::get_instance()->get_service_by_name( 'dropbox' );
		$this->assertInstanceOf( \easyDirectoryListingForWordPress\Directory_Listing_Base::class, $service );
		$this->assertSame( 'dropbox', $data['efml_mark_service'] );
		$this->assertStringContainsString( wp_strip_all_tags( $service->get_label() ), $data['efml_mark_label'] );

		// a protocol without service has no name in its label.
		Files::get_instance()->get_file( $external_id )->set_service_name( 'http' );
		$data = wp_prepare_attachment_for_js( $external_id );
		$this->assertSame( 'http', $data['efml_mark_service'] );
		$this->assertSame( 'External file', $data['efml_mark_label'] );

		// the mark needs no query, if the data of the file are loaded (like in the media dialog).
		global $wpdb;
		update_postmeta_cache( array( $external_id ) );
		$queries = $wpdb->num_queries;
		Media_Dialog::get_instance()->add_mark( array(), get_post( $external_id ) );
		$this->assertSame( $queries, $wpdb->num_queries );

		// an exported and a private file.
		update_post_meta( $external_id, 'eml_exported_file', time() );
		$this->assertSame( 'exported', wp_prepare_attachment_for_js( $external_id )['efml_mark'] );
		update_post_meta( $external_id, 'efml_private_file', 1 );
		$this->assertSame( 'private', wp_prepare_attachment_for_js( $external_id )['efml_mark'] );

		// no mark without the capability.
		wp_set_current_user( $this->get_user( 'administrator', false ) );
		$this->assertArrayNotHasKey( 'efml_mark', (array) wp_prepare_attachment_for_js( $external_id ) );
	}

	/**
	 * Test the request for the options of the filter.
	 *
	 * @return void
	 */
	public function test_filter_options(): void {
		// with nonce and capability.
		$result = $this->run_ajax( array( Media_Dialog::get_instance(), 'send_filter_options' ), array( 'nonce' => wp_create_nonce( 'efml-media-dialog' ) ) );
		$this->assertTrue( $result['success'] );
		$this->assertContains( 'external', array_column( $result['data']['options'], 'value' ) );

		// without a valid nonce.
		$result = $this->run_ajax( array( Media_Dialog::get_instance(), 'send_filter_options' ), array( 'nonce' => 'invalid' ) );
		$this->assertFalse( $result['success'] );

		// without the capability.
		wp_set_current_user( $this->get_user( 'administrator', false ) );
		$result = $this->run_ajax( array( Media_Dialog::get_instance(), 'send_filter_options' ), array( 'nonce' => wp_create_nonce( 'efml-media-dialog' ) ) );
		$this->assertFalse( $result['success'] );
	}

	/**
	 * Test the import of a file of an external source: it is imported once and its ID is returned.
	 *
	 * @return void
	 */
	public function test_import_with_source(): void {
		add_filter( 'efml_file_base', array( $this, 'set_plugin_path_as_base' ) );

		// add the directory of our test files as external source.
		$url     = self::get_test_file( 'pdf', 'file' );
		$term_id = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'local', trailingslashit( dirname( $url ) ), array() );
		$this->assertGreaterThan( 0, $term_id );

		// import the file.
		$values = array(
			'nonce'  => wp_create_nonce( 'efml-media-dialog' ),
			'urls'   => $url,
			'source' => $term_id,
		);
		$result = $this->run_ajax( array( Media_Dialog::get_instance(), 'import_via_ajax' ), $values );
		$this->assertTrue( $result['success'], (string) wp_json_encode( $result ) );
		$this->assertCount( 1, $result['data']['ids'] );
		$attachment_id = $result['data']['ids'][0];
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );

		// a second time it is not imported again, but its ID is returned.
		$result = $this->run_ajax( array( Media_Dialog::get_instance(), 'import_via_ajax' ), $values );
		$this->assertTrue( $result['success'] );
		$this->assertSame( array( $attachment_id ), $result['data']['ids'] );
		$this->assertSame( array(), $result['data']['errors'] );
	}

	/**
	 * Test that the import checks the permissions for the external source.
	 *
	 * @return void
	 */
	public function test_import_checks_permissions(): void {
		// an external source of an administrator.
		$term_id = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'local', trailingslashit( dirname( self::get_test_file( 'pdf', 'file' ) ) ), array() );
		$this->assertGreaterThan( 0, $term_id );

		// an editor with the capability for the media dialog could not use it (it has been saved by another user).
		wp_set_current_user( $this->get_user( 'editor', true ) );
		update_option( 'eml_show_all_external_sources', 0 );
		$this->assertInstanceOf( WP_Error::class, Directory_Listing::get_instance()->check_source_for_listing( $term_id ) );
		$result = $this->run_ajax(
			array( Media_Dialog::get_instance(), 'import_via_ajax' ),
			array(
				'nonce'  => wp_create_nonce( 'efml-media-dialog' ),
				'urls'   => self::get_test_file( 'pdf', 'file' ),
				'source' => $term_id,
			)
		);
		$this->assertFalse( $result['success'] );

		// an administrator without the permission for the service could not use it.
		$user_id = $this->get_user( 'administrator', true );
		get_user_by( 'id', $user_id )->add_cap( 'efml_cap_local', false );
		wp_set_current_user( $user_id );
		$this->assertInstanceOf( WP_Error::class, Directory_Listing::get_instance()->check_source_for_listing( $term_id ) );

		// without the capability for the media dialog nothing is imported.
		wp_set_current_user( $this->get_user( 'administrator', false ) );
		$result = $this->run_ajax(
			array( Media_Dialog::get_instance(), 'import_via_ajax' ),
			array(
				'nonce' => wp_create_nonce( 'efml-media-dialog' ),
				'urls'  => self::get_test_file( 'pdf', 'http' ),
			)
		);
		$this->assertFalse( $result['success'] );
		$this->assertFalse( Files::get_instance()->get_file_by_url( self::get_test_file( 'pdf', 'http' ) ) );
	}

	/**
	 * Test the configuration of the directory listing in the media dialog: only the import of files and the reload.
	 *
	 * @return void
	 */
	public function test_listing_config(): void {
		$term_id = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'local', trailingslashit( dirname( self::get_test_file( 'pdf', 'file' ) ) ), array() );
		$result  = Directory_Listing::get_instance()->get_listing_config_for_source( $term_id );
		$this->assertIsArray( $result );
		$this->assertSame( $term_id, $result['config']['term'] );
		$this->assertNotEmpty( $result['config']['nonce'] );

		// the service offers further actions, the media dialog only the import of files and the reload.
		$method = new \ReflectionMethod( Media_Dialog::class, 'get_frame_listing_config' );
		$config = $method->invoke( Media_Dialog::get_instance(), $result['config'] );
		foreach ( $config['actions'] as $action ) {
			$this->assertStringContainsString( 'efml_get_import_dialog', $action['action'] );
		}
		$this->assertNotEmpty( $config['actions'] );
		foreach ( $config['global_actions'] as $action ) {
			$this->assertStringStartsWith( 'setTree(', $action['action'] );
		}
		$this->assertLessThan( count( $result['config']['global_actions'] ), count( $config['global_actions'] ) );
		$this->assertFalse( $config['archive'] );
	}

	/**
	 * Test the card of a saved external source and the logo of a service.
	 *
	 * @return void
	 */
	public function test_source_card(): void {
		$path    = trailingslashit( dirname( self::get_test_file( 'pdf', 'file' ) ) );
		$term_id = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'local', $path, array() );
		$term    = get_term( $term_id );
		$this->assertInstanceOf( \WP_Term::class, $term );
		$listing_obj = Directory_Listing::get_instance()->check_source_for_listing( $term_id );
		$this->assertInstanceOf( \easyDirectoryListingForWordPress\Directory_Listing_Base::class, $listing_obj );

		$card = Directory_Listing::get_instance()->get_source_card( $term, $listing_obj, 'https://example.com/?source=1' );
		$this->assertStringContainsString( 'class="efml-card"', $card );
		$this->assertStringContainsString( 'efml-service-icon efml-local', $card );
		$this->assertStringContainsString( esc_html( $term->name ), $card );
		$this->assertStringContainsString( esc_html( wp_strip_all_tags( $listing_obj->get_label() ) ), $card );
		$this->assertSame( $card, wp_kses( $card, \ExternalFilesInMediaLibrary\Plugin\Helper::get_allowed_html_for_cards() ) );

		// the logo of a service.
		$this->assertSame( '<span class="efml-service-icon efml-dropbox" aria-hidden="true"></span>', \ExternalFilesInMediaLibrary\Plugin\Helper::get_service_icon( 'dropbox' ) );
		$this->assertStringContainsString( 'efml-scriptalertxscript', \ExternalFilesInMediaLibrary\Plugin\Helper::get_service_icon( '"><script>alert(x)</script>' ) );
	}

	/**
	 * Output the page of the iframe and return it (or the message of wp_die()).
	 *
	 * @param array<string,int> $get The parameters of the request.
	 *
	 * @return array{died: bool, output: string, code: int}
	 */
	private function render_frame( array $get = array() ): array {
		require_once ABSPATH . 'wp-admin/includes/admin.php';
		foreach ( $get as $name => $value ) {
			$_GET[ $name ] = $value;
		}
		set_current_screen( 'media' );
		ob_start();
		try {
			@Media_Dialog::get_instance()->render_frame(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- iframe_header() sends HTTP headers, which are not possible after the output of PHPUnit.
			$result = array(
				'died'   => false,
				'output' => (string) ob_get_contents(),
				'code'   => 200,
			);
		} catch ( \WPDieException $e ) {
			$result = array(
				'died'   => true,
				'output' => $e->getMessage(),
				'code'   => 403,
			);
		}
		ob_end_clean();
		foreach ( array_keys( $get ) as $name ) {
			unset( $_GET[ $name ] );
		}
		return $result;
	}

	/**
	 * Test that the page of the iframe could only be used by logged-in users with the capabilities and shows only
	 * the external sources, which the user could use.
	 *
	 * @return void
	 */
	public function test_frame_permissions(): void {
		// an external source of an administrator.
		$term_id = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'local', trailingslashit( dirname( self::get_test_file( 'pdf', 'file' ) ) ), array() );
		$this->assertGreaterThan( 0, $term_id );
		update_option( 'eml_show_all_external_sources', 0 );

		// the page is an action of the administration: WordPress requires the login (auth_redirect()) and prevents
		// its use in iframes of other websites (header X-Frame-Options: SAMEORIGIN).
		$this->assertNotFalse( has_action( 'admin_action_' . Media_Dialog::FRAME_ACTION, array( Media_Dialog::get_instance(), 'show_frame' ) ) );
		$this->assertNotFalse( has_action( 'admin_init', 'send_frame_options_header' ) );

		// not for visitors.
		wp_set_current_user( 0 );
		$this->assertTrue( $this->render_frame()['died'] );
		$this->assertTrue( $this->render_frame( array( 'source' => $term_id ) )['died'] );

		// not for users without the capability for the media dialog, also not for administrators.
		wp_set_current_user( $this->get_user( 'administrator', false ) );
		$this->assertTrue( $this->render_frame()['died'] );

		// not for users without the capability for the plugin.
		$user_id = $this->get_user( 'editor', true );
		get_user_by( 'id', $user_id )->add_cap( EFML_CAP_NAME, false );
		wp_set_current_user( $user_id );
		$this->assertTrue( $this->render_frame()['died'] );

		// the administrator with the capabilities sees the external source and could open it.
		wp_set_current_user( $this->get_user( 'administrator', true ) );
		$result = $this->render_frame();
		$this->assertFalse( $result['died'] );
		$this->assertStringContainsString( 'source=' . $term_id, $result['output'] );
		$result = $this->render_frame( array( 'source' => $term_id ) );
		$this->assertFalse( $result['died'] );
		$this->assertStringContainsString( 'id="easy-directory-listing-for-wordpress"', $result['output'] );

		// an editor with the capabilities does neither see the external source of the administrator nor open it.
		wp_set_current_user( $this->get_user( 'editor', true ) );
		$result = $this->render_frame();
		$this->assertFalse( $result['died'] );
		$this->assertStringNotContainsString( 'source=' . $term_id, $result['output'] );
		$result = $this->render_frame( array( 'source' => $term_id ) );
		$this->assertFalse( $result['died'] );
		$this->assertStringNotContainsString( 'id="easy-directory-listing-for-wordpress"', $result['output'] );
		$this->assertStringContainsString( 'notice-error', $result['output'] );
	}

	/**
	 * Test the origins, to which the page in the iframe sends its messages.
	 *
	 * @return void
	 */
	public function test_origin(): void {
		$admin_origin = (string) wp_parse_url( admin_url(), PHP_URL_SCHEME ) . '://' . (string) wp_parse_url( admin_url(), PHP_URL_HOST );

		// the origin of the website is allowed.
		$_GET['origin'] = $admin_origin;
		$this->assertSame( strtolower( $admin_origin ), Media_Dialog::get_instance()->get_requested_origin() );

		// another origin is replaced by the origin of the administration.
		$_GET['origin'] = 'https://attacker.example';
		$this->assertSame( strtolower( $admin_origin ), Media_Dialog::get_instance()->get_requested_origin() );
	}
}
