<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ThirdParty\InstantImages.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ThirdParty;

use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ThirdParty\InstantImages.
 */
class InstantImages extends externalFilesTests {
	/**
	 * The URL of the image on the platform, like Instant Images sends it.
	 *
	 * @var string
	 */
	private string $url = 'https://images.unsplash.com/photo-1234567890?ixid=abc&fit=clip&w=1600&h=1200';

	/**
	 * Prepare each test: images could be external with proxy, admin is logged in.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// allow external images with proxy.
		update_option( 'eml_images_mode', 'external' );
		update_option( 'eml_images_proxy', 1 );

		// enable the platforms.
		update_option( 'eml_instant_images_providers', array( 'unsplash', 'pexels', 'giphy' ) );

		// login as admin with the capability to use external files.
		get_role( 'administrator' )->add_cap( EFML_CAP_NAME );
		$this->do_login();
	}

	/**
	 * Return the object to test.
	 *
	 * @return \ExternalFilesInMediaLibrary\ThirdParty\InstantImages
	 */
	private function get_object(): \ExternalFilesInMediaLibrary\ThirdParty\InstantImages {
		return \ExternalFilesInMediaLibrary\ThirdParty\InstantImages::get_instance();
	}

	/**
	 * Create a local image, like Instant Images saves it, and return the data of its hook.
	 *
	 * @param string $provider The platform.
	 *
	 * @return array<string,mixed>
	 */
	private function create_image( string $provider = 'unsplash' ): array {
		$attachment_id = self::factory()->attachment->create_upload_object( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/example-red.jpg' );
		$this->assertIsInt( $attachment_id );

		// set a title without file extension, like Instant Images does it.
		wp_update_post(
			array(
				'ID'         => $attachment_id,
				'post_title' => 'Red example',
			)
		);

		return array(
			'filename'       => 'example-red.jpg',
			'id'             => 'abc',
			'title'          => 'Red example',
			'alt'            => 'A red example',
			'caption'        => '',
			'attachment_id'  => $attachment_id,
			'attachment_url' => wp_get_attachment_url( $attachment_id ),
			'provider'       => $provider,
			'original_url'   => $this->url,
		);
	}

	/**
	 * Test the switch of an image from Unsplash: it is external, the local files are deleted and the proxy is not used.
	 *
	 * @return void
	 */
	public function test_switch_to_external(): void {
		$data       = $this->create_image();
		$local_file = (string) get_attached_file( $data['attachment_id'], true );
		$this->assertFileExists( $local_file );

		$this->get_object()->switch_to_external( $data );

		// the file is an external file with the URL of the platform.
		$external_file_obj = Files::get_instance()->get_file( $data['attachment_id'] );
		$this->assertTrue( $external_file_obj->is_valid() );
		$this->assertFalse( $external_file_obj->is_locally_saved() );
		$this->assertSame( $this->url, $external_file_obj->get_url( true ) );
		$this->assertSame( $this->url, get_post_meta( $data['attachment_id'], '_wp_attached_file', true ) );
		$this->assertSame( 'unsplash', get_post_meta( $data['attachment_id'], \ExternalFilesInMediaLibrary\ThirdParty\InstantImages::META_PROVIDER, true ) );

		// the local file is deleted.
		$this->assertFileDoesNotExist( $local_file );

		// the title from Instant Images is kept.
		$this->assertSame( 'Red example', $external_file_obj->get_title() );

		// the metadata does not contain the deleted sizes anymore (unfiltered, as the plugin could replace "file" with the permalink).
		$meta_data = wp_get_attachment_metadata( $data['attachment_id'], true );
		$this->assertIsArray( $meta_data );
		$this->assertArrayNotHasKey( 'sizes', $meta_data );
		$this->assertSame( $this->url, $meta_data['file'] );

		// the proxy is not used for Unsplash, even if it is enabled for images.
		$this->assertFalse( $external_file_obj->get_file_type_obj()->is_proxy_enabled() );

		// a later import as local file uses a valid file name (the URL of Unsplash has no extension and a query string).
		$this->assertSame( 'Red-example.jpg', $external_file_obj->get_local_file_name() );
	}

	/**
	 * Test that images of platforms, which are not enabled or not possible (like Pixabay), remain local files.
	 *
	 * @return void
	 */
	public function test_no_switch_for_not_enabled_providers(): void {
		update_option( 'eml_instant_images_providers', array( 'unsplash', 'pixabay' ) );

		foreach ( array( 'pixabay', 'pexels', 'openverse' ) as $provider ) {
			$data = $this->create_image( $provider );
			$this->get_object()->switch_to_external( $data );
			$this->assertFalse( Files::get_instance()->get_file( $data['attachment_id'] )->is_valid(), 'File of ' . $provider . ' has been switched.' );
			$this->assertFileExists( (string) get_attached_file( $data['attachment_id'], true ) );
		}

		// pixabay is removed from the enabled platforms, as it is not possible.
		$this->assertSame( array( 'unsplash' ), $this->get_object()->get_enabled_providers() );
	}

	/**
	 * Test that nothing is switched, if the setting for images requires local files.
	 *
	 * @return void
	 */
	public function test_no_switch_if_images_are_saved_local(): void {
		update_option( 'eml_images_mode', 'local' );

		$data = $this->create_image();
		$this->get_object()->switch_to_external( $data );
		$this->assertFalse( Files::get_instance()->get_file( $data['attachment_id'] )->is_valid() );
		$this->assertFileExists( (string) get_attached_file( $data['attachment_id'], true ) );
	}

	/**
	 * Test that nothing is switched, if the user is not allowed to use external files.
	 *
	 * @return void
	 */
	public function test_no_switch_without_capability(): void {
		$data = $this->create_image();
		$this->do_logout();

		$this->get_object()->switch_to_external( $data );
		$this->assertFalse( Files::get_instance()->get_file( $data['attachment_id'] )->is_valid() );
	}

	/**
	 * Test that nothing is switched and nothing fails with unexpected data from the hook.
	 *
	 * @return void
	 */
	public function test_no_switch_with_invalid_data(): void {
		$data = $this->create_image();

		// test 1: no array.
		$this->get_object()->switch_to_external( 'invalid' );

		// test 2: missing values.
		foreach ( array( 'attachment_id', 'original_url', 'provider' ) as $key ) {
			$invalid_data = $data;
			unset( $invalid_data[ $key ] );
			$this->get_object()->switch_to_external( $invalid_data );
		}

		// test 3: not an HTTPS-URL.
		$this->get_object()->switch_to_external( array_merge( $data, array( 'original_url' => 'http://images.unsplash.com/photo-1234567890' ) ) );
		$this->get_object()->switch_to_external( array_merge( $data, array( 'original_url' => 'file:///etc/passwd' ) ) );

		// test 4: not an attachment.
		$this->get_object()->switch_to_external( array_merge( $data, array( 'attachment_id' => self::factory()->post->create() ) ) );

		$this->assertFalse( Files::get_instance()->get_file( $data['attachment_id'] )->is_valid() );
		$this->assertFileExists( (string) get_attached_file( $data['attachment_id'], true ) );
	}

	/**
	 * Test that an image, which is already an external file, is not changed.
	 *
	 * @return void
	 */
	public function test_no_switch_for_external_file(): void {
		$data = $this->create_image();
		Files::get_instance()->get_file( $data['attachment_id'] )->set_url( 'https://example.com/other.jpg' );

		$this->get_object()->switch_to_external( $data );
		$this->assertSame( 'https://example.com/other.jpg', Files::get_instance()->get_file( $data['attachment_id'] )->get_url( true ) );
		$this->assertFileExists( (string) get_attached_file( $data['attachment_id'], true ) );
	}

	/**
	 * Test the switch of an image from Pexels, if the proxy is disabled: the proxy is not used.
	 *
	 * @return void
	 */
	public function test_switch_to_external_without_proxy(): void {
		update_option( 'eml_images_proxy', 0 );
		$this->url = 'https://images.pexels.com/photos/123/example.jpeg?dpr=1&w=1600&h=1200';

		$data = $this->create_image( 'pexels' );
		$this->get_object()->switch_to_external( $data );

		$external_file_obj = Files::get_instance()->get_file( $data['attachment_id'] );
		$this->assertTrue( $external_file_obj->is_valid() );
		$this->assertFalse( $external_file_obj->is_locally_saved() );
		$this->assertFalse( $external_file_obj->get_file_type_obj()->is_proxy_enabled() );
	}

	/**
	 * Test the decision about the proxy for files of the platforms.
	 *
	 * @return void
	 */
	public function test_disable_proxy(): void {
		$object = $this->get_object();

		// test 1: disabled proxy is never enabled.
		$this->assertFalse( $object->disable_proxy( false, false ) );

		// test 2: without file the global setting is used.
		$this->assertTrue( $object->disable_proxy( true, false ) );

		// test 3: other files use the global setting.
		$attachment_id = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );
		$this->assertTrue( $object->disable_proxy( true, Files::get_instance()->get_file( $attachment_id ) ) );

		// test 4: files of platforms, which allow the proxy.
		update_post_meta( $attachment_id, \ExternalFilesInMediaLibrary\ThirdParty\InstantImages::META_PROVIDER, 'pexels' );
		$this->assertTrue( $object->disable_proxy( true, Files::get_instance()->get_file( $attachment_id ) ) );

		// test 5: files of platforms, which do not allow the proxy.
		foreach ( array( 'unsplash', 'giphy' ) as $provider ) {
			update_post_meta( $attachment_id, \ExternalFilesInMediaLibrary\ThirdParty\InstantImages::META_PROVIDER, $provider );
			$this->assertFalse( $object->disable_proxy( true, Files::get_instance()->get_file( $attachment_id ) ), 'Proxy is used for ' . $provider );
		}
	}

	/**
	 * Test the hint for administrators, which is only shown as long as no platform is enabled.
	 *
	 * @return void
	 */
	public function test_show_hint(): void {
		$transient_obj = \ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients::get_instance()->get_transient_by_name( \ExternalFilesInMediaLibrary\ThirdParty\InstantImages::HINT_NAME );

		// test 1: no platform is enabled, the hint is shown for administrators.
		update_option( 'eml_instant_images_providers', array() );
		$this->get_object()->show_hint();
		$this->assertTrue( $transient_obj->is_set() );
		$message = \ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients::get_instance()->get_transient_by_name( \ExternalFilesInMediaLibrary\ThirdParty\InstantImages::HINT_NAME )->get_message();
		$this->assertStringContainsString( 'Unsplash, Pexels, and GIPHY', $message );
		$this->assertStringContainsString( 'tab=instant_images', $message );

		// test 2: a platform is enabled, the hint is removed.
		update_option( 'eml_instant_images_providers', array( 'unsplash' ) );
		$this->get_object()->show_hint();
		$this->assertFalse( $transient_obj->is_set() );

		// test 3: the hint is not shown for users without the capability to change the settings.
		update_option( 'eml_instant_images_providers', array() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->get_object()->show_hint();
		$this->assertFalse( $transient_obj->is_set() );

		// test 4: the hint is removed, e.g. if Instant Images is not active anymore.
		$this->do_login();
		$this->get_object()->show_hint();
		$this->assertTrue( $transient_obj->is_set() );
		$this->get_object()->remove_hint();
		$this->assertFalse( $transient_obj->is_set() );
	}

	/**
	 * Test the text of the notice on the admin page of Instant Images.
	 *
	 * @return void
	 */
	public function test_get_notice_for_instant_images_page(): void {
		// test 1: the enabled platforms are named, incl. the link to the settings for administrators.
		update_option( 'eml_instant_images_providers', array( 'unsplash', 'pexels' ) );
		$notice = $this->get_object()->get_notice_for_instant_images_page();
		$this->assertStringContainsString( 'Unsplash and Pexels', $notice );
		$this->assertStringContainsString( 'tab=instant_images', $notice );

		// test 2: without platforms no notice.
		update_option( 'eml_instant_images_providers', array() );
		$this->assertSame( '', $this->get_object()->get_notice_for_instant_images_page() );

		// test 3: no notice if images are saved locally.
		update_option( 'eml_instant_images_providers', array( 'unsplash' ) );
		update_option( 'eml_images_mode', 'local' );
		$this->assertSame( '', $this->get_object()->get_notice_for_instant_images_page() );
		update_option( 'eml_images_mode', 'external' );

		// test 4: no notice for users, whose images are not switched.
		$this->do_logout();
		$this->assertSame( '', $this->get_object()->get_notice_for_instant_images_page() );

		// test 5: users with the capability, but without the right to change the settings, get no link.
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		get_role( 'editor' )->add_cap( EFML_CAP_NAME );
		wp_set_current_user( $user_id );
		$notice = $this->get_object()->get_notice_for_instant_images_page();
		$this->assertStringContainsString( 'Unsplash', $notice );
		$this->assertStringNotContainsString( 'tab=instant_images', $notice );
		get_role( 'editor' )->remove_cap( EFML_CAP_NAME );
	}

	/**
	 * Test that the notice is only shown on the admin page of Instant Images.
	 *
	 * @return void
	 */
	public function test_show_notice_on_instant_images_page(): void {
		update_option( 'eml_instant_images_providers', array( 'unsplash' ) );

		// test 1: on another page.
		set_current_screen( 'upload' );
		ob_start();
		$this->get_object()->show_notice_on_instant_images_page();
		$this->assertSame( '', ob_get_clean() );

		// test 2: on the page of Instant Images.
		set_current_screen( \ExternalFilesInMediaLibrary\ThirdParty\InstantImages::SCREEN_ID );
		ob_start();
		$this->get_object()->show_notice_on_instant_images_page();
		$output = (string) ob_get_clean();
		$this->assertStringContainsString( 'notice-info', $output );
		$this->assertStringContainsString( 'Unsplash', $output );

		// reset the screen.
		set_current_screen( 'front' );
	}
}
