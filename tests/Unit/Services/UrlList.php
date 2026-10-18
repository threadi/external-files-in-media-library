<?php
/**
 * Tests for the services to import URLs from lists: ExternalFilesInMediaLibrary\Services\Csv and ExternalFilesInMediaLibrary\Services\Xml.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services;

use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;
use ExternalFilesInMediaLibrary\Services\Csv;
use ExternalFilesInMediaLibrary\Services\Url_List\Protocol;
use ExternalFilesInMediaLibrary\Services\Url_List_Base;
use ExternalFilesInMediaLibrary\Services\Xml;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use WP_Error;

/**
 * Object to test the services to import URLs from lists.
 */
class UrlList extends externalFilesTests {
	/**
	 * The URL of the list on the simulated server.
	 */
	private const LIST_URL = 'https://lists.example.com/list.csv';

	/**
	 * The content of the list on the simulated server.
	 *
	 * @var string
	 */
	private string $list_content = '';

	/**
	 * The HTTP status of the list on the simulated server.
	 *
	 * @var int
	 */
	private int $list_status = 200;

	/**
	 * The requests to the simulated server.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private array $requests = array();

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->do_login();

		// initialize the services for the logged-in user (they use their hooks only for users with their capability).
		Csv::get_instance()->init();
		Xml::get_instance()->init();
		$this->list_content = (string) file_get_contents( UNIT_TESTS_DATA_PLUGIN_DIR . 'url-list.csv' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->list_status  = 200;
		$this->requests     = array();
		add_filter( 'pre_http_request', array( $this, 'answer_list_request' ), 5, 3 );
		add_filter( 'efml_url_list_check_interval', '__return_zero' );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( $this, 'answer_list_request' ), 5 );
		remove_filter( 'efml_url_list_check_interval', '__return_zero' );
		$this->do_logout();
		parent::tear_down();
	}

	/**
	 * Answer requests to the simulated server of the list.
	 *
	 * The server answers with ETag and "304 Not Modified" for unchanged content. "/protected/" requires
	 * Basic authentication with "user:secret". "/redirect/" redirects to another host.
	 *
	 * @param false|array<string,mixed>|WP_Error $response The response.
	 * @param array<string,mixed>                $parsed_args The arguments.
	 * @param string                             $url The URL.
	 *
	 * @return false|array<string,mixed>|WP_Error
	 */
	public function answer_list_request( false|array|WP_Error $response, array $parsed_args, string $url ): false|array|WP_Error {
		// get the host.
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );

		// answer requests to the server of the second file of the list with the example PDF.
		if ( 'files.example.com' === $host ) {
			$content = (string) file_get_contents( UNIT_TESTS_DATA_PLUGIN_DIR . 'example.pdf' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( ! empty( $parsed_args['filename'] ) ) {
				file_put_contents( $parsed_args['filename'], $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
			return self::get_http_response(
				$url,
				200,
				array(
					'content-type'   => 'application/pdf',
					'content-length' => (string) strlen( $content ),
				),
				'HEAD' === $parsed_args['method'] ? '' : $content
			);
		}

		// bail if this is not a request to the server of the list.
		if ( ! in_array( $host, array( 'lists.example.com', 'other.example.com' ), true ) ) {
			return $response;
		}

		// log the request.
		$headers          = isset( $parsed_args['headers'] ) && is_array( $parsed_args['headers'] ) ? $parsed_args['headers'] : array();
		$this->requests[] = array(
			'url'     => $url,
			'headers' => $headers,
		);

		// redirect to another host.
		if ( str_contains( $url, '/redirect/' ) ) {
			return self::get_http_response( $url, 302, array( 'location' => 'https://other.example.com/list.csv' ) );
		}

		// require authentication.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		if ( str_contains( $url, '/protected/' ) && ( $headers['Authorization'] ?? '' ) !== 'Basic ' . base64_encode( 'user:secret' ) ) {
			return self::get_http_response( $url, 401 );
		}

		// answer with configured status.
		if ( 200 !== $this->list_status ) {
			return self::get_http_response( $url, $this->list_status );
		}

		// answer unchanged content with 304.
		$etag = '"' . md5( $this->list_content ) . '"';
		if ( ( $headers['If-None-Match'] ?? '' ) === $etag ) {
			return self::get_http_response( $url, 304, array( 'etag' => $etag ) );
		}

		// save the content in the requested file.
		if ( ! empty( $parsed_args['filename'] ) ) {
			file_put_contents( $parsed_args['filename'], $this->list_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		// return the response.
		return self::get_http_response(
			$url,
			200,
			array(
				'content-type' => 'text/csv',
				'etag'         => $etag,
			),
			$this->list_content
		);
	}

	/**
	 * Return the fields for an uploaded list.
	 *
	 * @param string $service The service name.
	 * @param int    $attachment_id The ID of the list.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_fields_for_file( string $service, int $attachment_id ): array {
		return array(
			'list_type' => array( 'value' => $service ),
			'list_file' => array( 'value' => (string) $attachment_id ),
		);
	}

	/**
	 * Return the fields for a downloaded list.
	 *
	 * @param string $url The URL.
	 * @param string $login The login.
	 * @param string $password The password.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_fields_for_url( string $url, string $login = '', string $password = '' ): array {
		return array(
			'list_type' => array( 'value' => 'csv' ),
			'list_file' => array( 'value' => '' ),
			'list_url'  => array( 'value' => $url ),
			'login'     => array( 'value' => $login ),
			'password'  => array( 'value' => $password ),
		);
	}

	/**
	 * Return a list in the media library.
	 *
	 * @param string $file The name of the file in the test data.
	 *
	 * @return int
	 */
	private function get_list_attachment( string $file ): int {
		return self::factory()->attachment->create_upload_object( UNIT_TESTS_DATA_PLUGIN_DIR . $file );
	}

	/**
	 * Test the registration of both services.
	 *
	 * @return void
	 */
	public function test_services(): void {
		foreach ( array( Csv::get_instance(), Xml::get_instance() ) as $service_obj ) {
			$this->assertInstanceOf( Url_List_Base::class, \ExternalFilesInMediaLibrary\Services\Services::get_instance()->get_service_by_name( $service_obj->get_name() ) );
			$this->assertSame( 'efml_cap_' . $service_obj->get_name(), $service_obj->get_permission_name() );
			$fields = $service_obj->get_fields();
			$this->assertSame( 'file', $fields['list_file']['type'] );
			$this->assertSame( 'url', $fields['list_url']['type'] );
			$this->assertSame( 'source', $fields['list_file']['one_of'] );
			$this->assertSame( 'source', $fields['list_url']['one_of'] );
			$this->assertTrue( $fields['login']['not_required'] );
			$this->assertSame( 'list_url', $service_obj->get_config()['archive_requires_field'] );
		}
	}

	/**
	 * Test the list, mapping and directory listing of an uploaded CSV file.
	 *
	 * @return void
	 */
	public function test_uploaded_csv(): void {
		$attachment_id = $this->get_list_attachment( 'url-list.csv' );
		$fields        = $this->get_fields_for_file( 'csv', $attachment_id );
		$csv           = Csv::get_instance();

		// get the list.
		$list = $csv->get_list( $fields );
		$this->assertIsArray( $list );
		$this->assertSame( 1, $list['fields']['Datei']['files'] );
		$this->assertSame( 1, $list['fields']['Webseite']['urls'] );
		$this->assertSame( 0, $list['fields']['Webseite']['files'] );

		// the automatic mapping uses the fields with files and detects the other roles by their names.
		$mapping = $csv->get_automatic_mapping( $list );
		$this->assertSame( array( 'Datei', 'Weitere Dateien' ), $mapping['url'] );
		$this->assertSame( 'Alt', $mapping['alt'] );

		// the directory listing shows each field with URLs as directory.
		$csv->set_fields( $fields );
		$source = $csv->get_directory();
		$this->assertSame( wp_get_attachment_url( $attachment_id ), $source );
		$tree = $csv->get_directory_listing( $source );
		$this->assertTrue( $tree['completed'] );
		$this->assertArrayHasKey( $source . '#efml-field=Datei/', $tree );
		$this->assertArrayHasKey( $source . '#efml-field=Webseite/', $tree );
		$this->assertSame( 'https://example.com/example.pdf', $tree[ $source . '#efml-field=Datei/' ]['files'][0]['file'] );
		$this->assertFalse( $tree[ $source . '#efml-field=Datei/' ]['files'][0]['same_host'] );
		$this->assertSame( 'https://example.com/example.pdf', $tree[ $source . '#efml-field=Datei/' ]['files'][0]['title'] );

		// uploaded files could not be saved as external source.
		$this->assertFalse( $csv->is_archive_allowed() );
	}

	/**
	 * Test the addresses of a list.
	 *
	 * @return void
	 */
	public function test_parse_address(): void {
		$attachment_id = $this->get_list_attachment( 'url-list.csv' );
		$fields        = $this->get_fields_for_file( 'csv', $attachment_id );
		$source        = (string) wp_get_attachment_url( $attachment_id );
		$csv           = Csv::get_instance();

		$this->assertSame( 'all', $csv->parse_address( $source, $fields )['scope'] ?? '' );
		$this->assertSame( 'all', $csv->parse_address( $source . '/', $fields )['scope'] ?? '' );
		$this->assertSame( 'field', $csv->parse_address( str_replace( 'http://', 'https://', $source ) . '#efml-field=Datei/', $fields )['scope'] ?? '' );
		$this->assertSame( array( 'scope' => 'field', 'value' => 'Weitere Dateien' ), $csv->parse_address( $source . '#efml-field=' . rawurlencode( 'Weitere Dateien' ) . '/', $fields ) );
		$this->assertSame( array( 'scope' => 'url', 'value' => 'https://example.com/example.pdf' ), $csv->parse_address( $source . '#efml-url=' . rawurlencode( 'https://example.com/example.pdf' ), $fields ) );
		$this->assertFalse( $csv->parse_address( 'https://example.com/example.pdf', $fields ) );
		$this->assertFalse( $csv->parse_address( $source . '#other', $fields ) );

		// the fields of another service do not match.
		$this->assertFalse( Xml::get_instance()->parse_address( $source, $fields ) );

		// the protocol is only used for addresses of lists.
		$this->assertInstanceOf( Protocol::class, Protocols::get_instance()->get_protocol_object_for_url( $source, $fields ) );
		$this->assertNotInstanceOf( Protocol::class, Protocols::get_instance()->get_protocol_object_for_url( 'https://example.com/example.pdf', array() ) );
		$this->assertNotInstanceOf( Protocol::class, Protocols::get_instance()->get_protocol_object_for_url( 'https://example.com/example.pdf', $fields ) );
	}

	/**
	 * Test the import of all files of a list with their data from the list.
	 *
	 * @return void
	 */
	public function test_import_csv(): void {
		$attachment_id = $this->get_list_attachment( 'url-list.csv' );
		$fields        = $this->get_fields_for_file( 'csv', $attachment_id );
		$count         = $this->get_media_file_count();

		// import the list.
		$import = Import::get_instance();
		$import->set_fields( $fields );
		$this->assertTrue( $import->add_url( (string) wp_get_attachment_url( $attachment_id ) ) );

		// the fields of the import are restored after the import.
		$this->assertSame( $fields, $import->get_fields() );
		$import->set_fields( array() );

		// both files have been imported, the URL of the website not.
		$this->assertSame( $count + 2, $this->get_media_file_count() );

		// check the data of the imported file.
		$external_file_obj = Files::get_instance()->get_file_by_url( 'https://example.com/example.pdf' );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\File::class, $external_file_obj );
		$this->assertSame( 'example.pdf', get_the_title( $external_file_obj->get_id() ) );
		$this->assertSame( 'Ein Beispiel', get_post_meta( $external_file_obj->get_id(), '_wp_attachment_image_alt', true ) );
		$this->assertSame( 'http', get_post_meta( $external_file_obj->get_id(), 'eml_service', true ) );
		$this->assertSame( $attachment_id, absint( get_post_meta( $external_file_obj->get_id(), Url_List_Base::META_IMPORTED_FROM, true ) ) );
		$this->assertEmpty( $external_file_obj->get_fields() );

		// the list itself has not been changed.
		$this->assertSame( (string) file_get_contents( UNIT_TESTS_DATA_PLUGIN_DIR . 'url-list.csv' ), (string) file_get_contents( (string) get_attached_file( $attachment_id ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/**
	 * Test the import of a single URL and of a single field of a list.
	 *
	 * @return void
	 */
	public function test_import_single_url_and_field(): void {
		$attachment_id = $this->get_list_attachment( 'url-list.csv' );
		$fields        = $this->get_fields_for_file( 'csv', $attachment_id );
		$source        = (string) wp_get_attachment_url( $attachment_id );
		$count         = $this->get_media_file_count();

		$import = Import::get_instance();
		$import->set_fields( $fields );
		$this->assertTrue( $import->add_url( $source . '#efml-url=' . rawurlencode( 'https://files.example.com/second.pdf' ) ) );
		$this->assertSame( $count + 1, $this->get_media_file_count() );
		$this->assertFalse( Files::get_instance()->get_file_by_url( 'https://example.com/example.pdf' ) );

		$this->assertTrue( $import->add_url( $source . '#efml-field=Datei/' ) );
		$this->assertSame( $count + 2, $this->get_media_file_count() );
		$import->set_fields( array() );
	}

	/**
	 * Test the import from an uploaded XML file.
	 *
	 * @return void
	 */
	public function test_import_xml(): void {
		$attachment_id = $this->get_list_attachment( 'url-list.xml' );
		$fields        = $this->get_fields_for_file( 'xml', $attachment_id );
		$count         = $this->get_media_file_count();

		$list = Xml::get_instance()->get_list( $fields );
		$this->assertIsArray( $list );
		$this->assertSame( array( 'enclosure/@url', 'media:content/@url' ), Xml::get_instance()->get_automatic_mapping( $list )['url'] );

		$import = Import::get_instance();
		$import->set_fields( $fields );
		$this->assertTrue( $import->add_url( (string) wp_get_attachment_url( $attachment_id ) ) );
		$import->set_fields( array() );
		$this->assertSame( $count + 2, $this->get_media_file_count() );

		$external_file_obj = Files::get_instance()->get_file_by_url( 'https://example.com/example.pdf' );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\File::class, $external_file_obj );
		$this->assertSame( 'example.pdf', get_the_title( $external_file_obj->get_id() ) );
		$this->assertSame( 'The example PDF file', get_post_field( 'post_content', $external_file_obj->get_id() ) );
	}

	/**
	 * Test the download of a list into the media library and the detection of changes.
	 *
	 * @return void
	 */
	public function test_download_list(): void {
		$csv    = Csv::get_instance();
		$fields = $this->get_fields_for_url( self::LIST_URL );

		// the first request downloads the list into the media library.
		$attachment_id = $csv->get_list_attachment_id( $fields );
		$this->assertIsInt( $attachment_id );
		$this->assertSame( self::LIST_URL, get_post_meta( $attachment_id, Url_List_Base::META_SOURCE, true ) );
		$this->assertSame( $this->list_content, (string) file_get_contents( (string) get_attached_file( $attachment_id ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		update_post_meta( $attachment_id, Url_List_Base::META_MAPPING, array( 'url' => array( 'Datei' ) ) );

		// unchanged content results in "304 Not Modified" and the same file.
		$this->assertSame( $attachment_id, $csv->get_list_attachment_id( $fields ) );
		$this->assertSame( (string) get_post_meta( $attachment_id, Url_List_Base::META_ETAG, true ), end( $this->requests )['headers']['If-None-Match'] ?? '' );

		// changed content results in a new file with the mapping of the previous one.
		$this->list_content .= "Zweites PDF;https://example.com/example.pdf?2;;;\r\n";
		$new_attachment_id   = $csv->get_list_attachment_id( $fields );
		$this->assertIsInt( $new_attachment_id );
		$this->assertNotSame( $attachment_id, $new_attachment_id );
		$this->assertSame( array( 'url' => array( 'Datei' ) ), get_post_meta( $new_attachment_id, Url_List_Base::META_MAPPING, true ) );
		$this->assertFileExists( (string) get_attached_file( $attachment_id ) );

		// errors of the server are returned.
		$this->list_status = 404;
		$this->assertInstanceOf( WP_Error::class, $csv->get_list_attachment_id( $this->get_fields_for_url( 'https://lists.example.com/missing.csv' ) ) );

		// invalid URLs are not requested.
		$this->assertInstanceOf( WP_Error::class, $csv->get_list_attachment_id( $this->get_fields_for_url( 'file:///etc/passwd' ) ) );
	}

	/**
	 * Test that the credentials are only sent to the host of the list.
	 *
	 * @return void
	 */
	public function test_download_with_credentials(): void {
		$csv = Csv::get_instance();

		// without and with wrong credentials the access is denied.
		$this->assertInstanceOf( WP_Error::class, $csv->get_list_attachment_id( $this->get_fields_for_url( 'https://lists.example.com/protected/list.csv' ) ) );
		$this->assertInstanceOf( WP_Error::class, $csv->get_list_attachment_id( $this->get_fields_for_url( 'https://lists.example.com/protected/list.csv', 'user', 'wrong' ) ) );

		// with the right credentials the list is downloaded.
		$this->assertIsInt( $csv->get_list_attachment_id( $this->get_fields_for_url( 'https://lists.example.com/protected/list.csv', 'user', 'secret' ) ) );

		// a redirect to another host does not get the credentials.
		$this->requests = array();
		$this->assertIsInt( $csv->get_list_attachment_id( $this->get_fields_for_url( 'https://lists.example.com/redirect/list.csv', 'user', 'secret' ) ) );
		$this->assertCount( 2, $this->requests );
		$this->assertArrayHasKey( 'Authorization', $this->requests[0]['headers'] );
		$this->assertSame( 'https://other.example.com/list.csv', $this->requests[1]['url'] );
		$this->assertArrayNotHasKey( 'Authorization', $this->requests[1]['headers'] );
	}

	/**
	 * Test that downloaded lists could be saved as external source, uploaded lists not.
	 *
	 * @return void
	 */
	public function test_is_archive_allowed(): void {
		$csv = Csv::get_instance();

		$csv->set_fields( $this->get_fields_for_url( self::LIST_URL ) );
		$this->assertTrue( $csv->is_archive_allowed() );

		$csv->set_fields( $this->get_fields_for_file( 'csv', 1 ) );
		$this->assertFalse( $csv->is_archive_allowed() );
	}

	/**
	 * Test the option in the media library.
	 *
	 * @return void
	 */
	public function test_media_library_option(): void {
		$csv_id = $this->get_list_attachment( 'url-list.csv' );
		$xml_id = $this->get_list_attachment( 'url-list.xml' );
		$pdf_id = $this->get_list_attachment( 'example.pdf' );

		// the actions are added for lists of the matching format.
		update_option( 'eml_csv_media_library', 1 );
		$this->assertArrayHasKey( 'efml-url-list-import-csv', Csv::get_instance()->add_media_row_action( array(), get_post( $csv_id ) ) );
		$this->assertArrayNotHasKey( 'efml-url-list-import-csv', Csv::get_instance()->add_media_row_action( array(), get_post( $xml_id ) ) );
		$this->assertArrayHasKey( 'efml-url-list-import-xml', Xml::get_instance()->add_media_row_action( array(), get_post( $xml_id ) ) );
		$this->assertEmpty( Csv::get_instance()->add_media_row_action( array(), get_post( $pdf_id ) ) );
		$this->assertArrayHasKey( 'efml_url_list_csv', Csv::get_instance()->add_attachment_field( array(), get_post( $csv_id ) ) );

		// the action is not added if the setting is disabled.
		update_option( 'eml_csv_media_library', 0 );
		$this->assertEmpty( Csv::get_instance()->add_media_row_action( array(), get_post( $csv_id ) ) );
		update_option( 'eml_csv_media_library', 1 );

		// the action is not added for users without the capability.
		$this->do_logout();
		$this->assertEmpty( Csv::get_instance()->add_media_row_action( array(), get_post( $csv_id ) ) );
	}

	/**
	 * Test the mapping in the import dialog.
	 *
	 * @return void
	 */
	public function test_dialog(): void {
		$attachment_id = $this->get_list_attachment( 'url-list.csv' );
		$settings      = array(
			'service' => 'csv',
			'urls'    => (string) wp_get_attachment_url( $attachment_id ),
			'fields'  => $this->get_fields_for_file( 'csv', $attachment_id ),
		);

		$dialog = Csv::get_instance()->add_mapping_in_dialog( array( 'texts' => array() ), $settings );
		$html   = implode( '', $dialog['texts'] );
		$this->assertStringContainsString( 'name="efml_url_list_role[Datei]"', $html );
		$this->assertStringContainsString( 'name="efml_url_list_role[' . rawurlencode( 'Weitere Dateien' ) . ']"', $html );
		$this->assertStringContainsString( 'value="all"', $html );

		// a single URL uses the normal dialog.
		$settings_single         = $settings;
		$settings_single['urls'] = $settings['urls'] . '#efml-url=' . rawurlencode( 'https://example.com/example.pdf' );
		$this->assertSame( array( 'texts' => array() ), Csv::get_instance()->add_mapping_in_dialog( array( 'texts' => array() ), $settings_single ) );

		// other services do not change the dialog.
		$this->assertSame( array( 'texts' => array() ), Xml::get_instance()->add_mapping_in_dialog( array( 'texts' => array() ), $settings ) );
	}

	/**
	 * Test the setting of the capabilities for the services on their first use.
	 *
	 * @return void
	 */
	public function test_set_default_capability(): void {
		// remove the capabilities and the marker.
		foreach ( wp_roles()->role_objects as $role_obj ) {
			$role_obj->remove_cap( 'efml_cap_csv' );
			$role_obj->remove_cap( 'efml_cap_xml' );
		}
		delete_option( 'efml_csv_capability_set' );
		delete_option( 'efml_xml_capability_set' );

		// initialize the services like on each request.
		Csv::get_instance()->init();
		Xml::get_instance()->init();

		// check the capabilities, also for the actual user.
		$this->assertTrue( get_role( 'administrator' )->has_cap( 'efml_cap_csv' ) );
		$this->assertTrue( get_role( 'editor' )->has_cap( 'efml_cap_xml' ) );
		$this->assertFalse( get_role( 'author' )->has_cap( 'efml_cap_csv' ) );
		$this->assertTrue( current_user_can( 'efml_cap_csv' ) );

		// the capabilities are not set again, if they have been removed afterward (e.g. in the settings).
		get_role( 'editor' )->remove_cap( 'efml_cap_xml' );
		Xml::get_instance()->init();
		$this->assertFalse( get_role( 'editor' )->has_cap( 'efml_cap_xml' ) );
		get_role( 'editor' )->add_cap( 'efml_cap_xml' );
	}

	/**
	 * Test the automatic cleanup of lists used via the directory listing.
	 *
	 * @return void
	 */
	public function test_cleanup(): void {
		$csv = Csv::get_instance();

		// a list uploaded via the directory listing.
		$uploaded_id = $this->get_list_attachment( 'url-list.csv' );
		$csv->set_fields( $this->get_fields_for_file( 'csv', $uploaded_id ) );
		$this->assertTrue( $csv->do_login( '' ) );

		// a list the user added to the media library (never used via the directory listing).
		$own_id = $this->get_list_attachment( 'url-list.csv' );

		// a list downloaded from a URL, which is saved as external source.
		$downloaded_id = $csv->get_list_attachment_id( $this->get_fields_for_url( self::LIST_URL ) );
		$this->assertIsInt( $downloaded_id );
		\easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'csv', self::LIST_URL, $this->get_fields_for_url( self::LIST_URL ) );

		// nothing is deleted, as all lists have been used recently.
		update_option( 'eml_csv_cleanup', 1 );
		update_option( 'eml_csv_cleanup_days', 7 );
		$this->assertSame( array(), $csv->cleanup() );

		// the hint about the automatic deletion is only shown on the uploaded list.
		$this->assertTrue( $csv->is_deleted_by_cleanup( $uploaded_id ) );
		$this->assertFalse( $csv->is_deleted_by_cleanup( $own_id ) );
		$this->assertFalse( $csv->is_deleted_by_cleanup( $downloaded_id ) );
		$this->assertArrayHasKey( 'efml_url_list_cleanup_csv', $csv->add_cleanup_hint( array(), get_post( $uploaded_id ) ) );
		$this->assertArrayNotHasKey( 'efml_url_list_cleanup_csv', $csv->add_cleanup_hint( array(), get_post( $own_id ) ) );

		// on the edit page the hint is shown in a meta box.
		global $wp_meta_boxes;
		$csv->add_cleanup_meta_box( get_post( $own_id ) );
		$this->assertEmpty( $wp_meta_boxes['attachment']['side']['low']['efml_url_list_cleanup_csv'] ?? array() );
		$csv->add_cleanup_meta_box( get_post( $uploaded_id ) );
		$this->assertNotEmpty( $wp_meta_boxes['attachment']['side']['low']['efml_url_list_cleanup_csv'] );
		ob_start();
		$csv->show_cleanup_meta_box( get_post( $uploaded_id ) );
		$this->assertStringContainsString( 'dashicons-clock', (string) ob_get_clean() );

		// a shorter time span (e.g., from WP CLI) and a dry run do not delete anything.
		update_post_meta( $uploaded_id, Url_List_Base::META_USED, time() - 2 * DAY_IN_SECONDS );
		$this->assertSame( array( $uploaded_id ), $csv->cleanup( 1, false, true ) );
		$this->assertInstanceOf( \WP_Post::class, get_post( $uploaded_id ) );

		// after the configured days the uploaded list is deleted, the own list and the last version of the external source not.
		foreach ( array( $uploaded_id, $own_id, $downloaded_id ) as $attachment_id ) {
			update_post_meta( $attachment_id, Url_List_Base::META_USED, time() - 8 * DAY_IN_SECONDS );
		}
		$this->assertSame( array( $uploaded_id ), $csv->cleanup() );
		$this->assertNull( get_post( $uploaded_id ) );
		$this->assertInstanceOf( \WP_Post::class, get_post( $own_id ) );
		$this->assertInstanceOf( \WP_Post::class, get_post( $downloaded_id ) );

		// nothing is deleted if the cleanup is disabled.
		$uploaded_id = $this->get_list_attachment( 'url-list.csv' );
		update_post_meta( $uploaded_id, Url_List_Base::META_TEMPORARY, 'csv' );
		update_post_meta( $uploaded_id, Url_List_Base::META_USED, time() - 8 * DAY_IN_SECONDS );
		update_option( 'eml_csv_cleanup', 0 );
		$this->assertSame( array(), $csv->cleanup() );
		$this->assertArrayNotHasKey( 'efml_url_list_cleanup_csv', $csv->add_cleanup_hint( array(), get_post( $uploaded_id ) ) );

		// but with force (e.g., from WP CLI).
		$this->assertSame( array( $uploaded_id ), $csv->cleanup( 0, true ) );
		$this->assertNull( get_post( $uploaded_id ) );
		$this->assertFalse( ( new \ExternalFilesInMediaLibrary\Plugin\Schedules\Url_List_Cleanup() )->is_enabled() && ! Xml::get_instance()->is_cleanup_enabled() );
		update_option( 'eml_csv_cleanup', 1 );
	}
}
