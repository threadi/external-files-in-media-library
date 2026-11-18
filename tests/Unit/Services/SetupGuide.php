<?php
/**
 * Tests for the help for the setup of services.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services;

use ExternalFilesInMediaLibrary\Services\Service_Base;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test the help for the setup of services.
 */
class SetupGuide extends externalFilesTests {
	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_all_filters( 'efml_service_setup_guide' );
		parent::tear_down();
	}

	/**
	 * Test the output of the help for a service with access data.
	 *
	 * @return void
	 */
	public function test_guide_of_ftp(): void {
		$html = \ExternalFilesInMediaLibrary\Services\Ftp::get_instance()->get_setup_guide_html();
		$this->assertStringStartsWith( '<details class="efml-setup-guide">', $html );
		$this->assertStringContainsString( '<ol>', $html );
		$this->assertStringContainsString( '<code>ftps://ftp.example.com</code>', $html );
		$this->assertStringContainsString( 'Good to know:', $html );
	}

	/**
	 * Test the help in the form of the service.
	 *
	 * @return void
	 */
	public function test_guide_in_form(): void {
		$config = \ExternalFilesInMediaLibrary\Services\Ftp::get_instance()->get_config();
		$this->assertStringContainsString( 'efml-setup-guide', $config['form_description'] );
	}

	/**
	 * Test services without help, e.g. the REST API, which needs no access data.
	 *
	 * @return void
	 */
	public function test_service_without_guide(): void {
		$this->assertSame( '', \ExternalFilesInMediaLibrary\Services\Rest::get_instance()->get_setup_guide_html() );
	}

	/**
	 * Test the change of the help via hook, also with a text to copy and links.
	 *
	 * @return void
	 */
	public function test_filter(): void {
		add_filter(
			'efml_service_setup_guide',
			static function ( array $guide, Service_Base $service ): array {
				if ( 'rest' !== $service->get_name() ) {
					return $guide;
				}
				return array(
					'intro' => 'Intro <script>alert(1)</script><code>x</code>',
					'code'  => array(
						'label'   => 'Policy',
						'content' => "{\n  \"a\": \"<b>\"\n}",
						'hint'    => 'Code hint',
					),
					'links' => array(
						array(
							'label' => 'Docs',
							'url'   => 'https://example.com/docs',
						),
						array( 'label' => 'Incomplete' ),
					),
				);
			},
			10,
			2
		);
		$html = \ExternalFilesInMediaLibrary\Services\Rest::get_instance()->get_setup_guide_html();
		// the script is removed: since WordPress 7.2 KSES also removes its content.
		$this->assertMatchesRegularExpression( '#Intro (alert\(1\))?<code>x</code>#', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '<textarea readonly="readonly" rows="3">', $html );
		$this->assertStringContainsString( '&lt;b&gt;', $html );
		$this->assertStringContainsString( 'efml-setup-guide-copy', $html );
		$this->assertStringContainsString( 'href="https://example.com/docs"', $html );
		$this->assertStringNotContainsString( 'Incomplete', $html );
	}

	/**
	 * Test the help of all services of this plugin: each one is valid HTML with the expected parts.
	 *
	 * @return void
	 */
	public function test_guides_of_services(): void {
		foreach ( array( 'Ftp', 'DropBox', 'Youtube', 'Csv', 'Xml', 'Zip' ) as $name ) {
			$class = '\\ExternalFilesInMediaLibrary\\Services\\' . $name;
			$html  = $class::get_instance()->get_setup_guide_html();
			$this->assertStringContainsString( 'efml-setup-guide-content', $html, $name );
			$this->assertSame( substr_count( $html, '<p>' ), substr_count( $html, '</p>' ), $name );
		}
	}
}
