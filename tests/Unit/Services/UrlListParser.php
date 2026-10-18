<?php
/**
 * Tests for the parsers of lists with URLs in ExternalFilesInMediaLibrary\Services\Url_List.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services;

use ExternalFilesInMediaLibrary\Services\Url_List\Csv_Parser;
use ExternalFilesInMediaLibrary\Services\Url_List\Xml_Parser;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test the parsers of lists with URLs.
 */
class UrlListParser extends externalFilesTests {
	/**
	 * List of temporary files.
	 *
	 * @var array<int,string>
	 */
	private array $files = array();

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		foreach ( $this->files as $file ) {
			wp_delete_file( $file );
		}
		$this->files = array();
		parent::tear_down();
	}

	/**
	 * Return a temporary file with the given content.
	 *
	 * @param string $content The content.
	 *
	 * @return string
	 */
	private function get_file( string $content ): string {
		$file          = wp_tempnam( 'efml-url-list' );
		$this->files[] = $file;
		file_put_contents( $file, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return $file;
	}

	/**
	 * Test the detection of delimiter and header and the splitting of cells with multiple URLs in CSV files.
	 *
	 * @return void
	 */
	public function test_csv_with_header_and_semicolon(): void {
		$parser = new Csv_Parser();
		$this->assertTrue( $parser->parse( $this->get_file( "Name;Image;Gallery\r\n\"Table; red\";https://example.com/a.jpg;https://example.com/b.jpg, https://example.com/c.jpg|https://example.com/d.jpg\r\n\r\nChair;https://example.com/e.jpg;\r\n" ) ) );
		$this->assertSame( ';', $parser->get_delimiter() );
		$this->assertTrue( $parser->has_header() );

		$result = $parser->get_result();
		$this->assertSame( 'csv', $result['format'] );
		$this->assertCount( 2, $result['records'] );
		$this->assertSame( array( 'Table; red' ), $result['records'][0]['Name'] );
		$this->assertSame( array( 'https://example.com/b.jpg', 'https://example.com/c.jpg', 'https://example.com/d.jpg' ), $result['records'][0]['Gallery'] );
		$this->assertArrayNotHasKey( 'Gallery', $result['records'][1] );
	}

	/**
	 * Test a CSV file without header, with UTF-8 BOM and only one column.
	 *
	 * @return void
	 */
	public function test_csv_without_header(): void {
		$parser = new Csv_Parser();
		$this->assertTrue( $parser->parse( $this->get_file( "\xEF\xBB\xBFhttps://example.com/a.pdf\nhttps://example.com/b.pdf\n" ) ) );
		$this->assertFalse( $parser->has_header() );

		$result = $parser->get_result();
		$this->assertCount( 2, $result['records'] );
		$this->assertSame( array( 'https://example.com/a.pdf' ), reset( $result['records'][0] ) );
	}

	/**
	 * Test the conversion of values in Windows-1252 (e.g., from Excel) to UTF-8.
	 *
	 * @return void
	 */
	public function test_csv_with_windows_1252(): void {
		$parser = new Csv_Parser();
		$this->assertTrue( $parser->parse( $this->get_file( "url,title\nhttps://example.com/a.jpg,\xC4rger\n" ) ) );
		$result = $parser->get_result();
		$this->assertSame( array( 'Ärger' ), $result['records'][0]['title'] );
	}

	/**
	 * Test the limit of records.
	 *
	 * @return void
	 */
	public function test_csv_limit(): void {
		$parser = new Csv_Parser();
		$parser->set_max_records( 2 );
		$this->assertTrue( $parser->parse( $this->get_file( "url\nhttps://example.com/a.jpg\nhttps://example.com/b.jpg\nhttps://example.com/c.jpg\n" ) ) );
		$result = $parser->get_result();
		$this->assertCount( 2, $result['records'] );
		$this->assertTrue( $result['limit_reached'] );
	}

	/**
	 * Test an empty CSV file.
	 *
	 * @return void
	 */
	public function test_csv_empty(): void {
		$parser = new Csv_Parser();
		$this->assertFalse( $parser->parse( $this->get_file( '' ) ) );
		$this->assertNotEmpty( $parser->get_error() );
	}

	/**
	 * Test the detection of URLs.
	 *
	 * @return void
	 */
	public function test_is_url(): void {
		$parser = new Csv_Parser();
		$urls   = array(
			'https://example.com/a.jpg'         => true,
			'http://example.com/ärger.jpg'      => true,
			'https://example.com/a b.jpg'       => false,
			'ftp://example.com/a.jpg'           => false,
			'file:///etc/passwd'                => false,
			'javascript:alert(1)'               => false,
			'example.com/a.jpg'                 => false,
			''                                  => false,
		);
		foreach ( $urls as $url => $expected ) {
			$this->assertSame( $expected, $parser->is_url( (string) $url ), (string) $url );
		}
	}

	/**
	 * Test a sitemap with images.
	 *
	 * @return void
	 */
	public function test_xml_sitemap(): void {
		$parser = new Xml_Parser();
		$this->assertTrue( $parser->parse( $this->get_file( '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"><url><loc>https://example.com/page</loc><image:image><image:loc>https://example.com/a.jpg</image:loc><image:title>A</image:title></image:image><image:image><image:loc>https://example.com/b.jpg</image:loc></image:image></url><url><loc>https://example.com/page2</loc></url></urlset>' ) ) );
		$this->assertSame( '/urlset/url', $parser->get_record_path() );

		$result = $parser->get_result();
		$this->assertSame( 'sitemap', $result['format'] );
		$this->assertCount( 2, $result['records'] );
		$this->assertSame( array( 'https://example.com/a.jpg', 'https://example.com/b.jpg' ), $result['records'][0]['image:image/image:loc'] );
		$this->assertSame( array( 'A' ), $result['records'][0]['image:image/image:title'] );
	}

	/**
	 * Test a RSS feed with enclosures as attributes.
	 *
	 * @return void
	 */
	public function test_xml_rss(): void {
		$parser = new Xml_Parser();
		$this->assertTrue( $parser->parse( UNIT_TESTS_DATA_PLUGIN_DIR . 'url-list.xml' ) );
		$this->assertSame( '/rss/channel/item', $parser->get_record_path() );

		$result = $parser->get_result();
		$this->assertSame( 'rss', $result['format'] );
		$this->assertSame( array( 'https://example.com/example.pdf' ), $result['records'][0]['enclosure/@url'] );
		$this->assertSame( array( 'https://files.example.com/second.pdf' ), $result['records'][1]['media:content/@url'] );
	}

	/**
	 * Test a WordPress export with the alternative text as pair of meta key and value.
	 *
	 * @return void
	 */
	public function test_xml_wordpress_export(): void {
		$parser = new Xml_Parser();
		$this->assertTrue( $parser->parse( $this->get_file( '<?xml version="1.0"?><rss version="2.0" xmlns:wp="http://wordpress.org/export/1.2/"><channel><item><title>Post</title><link>https://example.com/post</link></item><item><title>Mountain</title><wp:attachment_url><![CDATA[https://example.com/mountain.jpg]]></wp:attachment_url><wp:postmeta><wp:meta_key><![CDATA[_wp_attachment_image_alt]]></wp:meta_key><wp:meta_value><![CDATA[A mountain]]></wp:meta_value></wp:postmeta></item></channel></rss>' ) ) );

		$result = $parser->get_result();
		$this->assertSame( 'wxr', $result['format'] );
		$this->assertSame( array( 'A mountain' ), $result['records'][1]['wp:postmeta/_wp_attachment_image_alt'] );
	}

	/**
	 * Test that XML files with DOCTYPE are not read (protection against XXE).
	 *
	 * @return void
	 */
	public function test_xml_with_doctype(): void {
		$parser = new Xml_Parser();
		$this->assertFalse( $parser->parse( $this->get_file( '<?xml version="1.0"?><!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><files><file><url>&xxe;</url></file></files>' ) ) );
		$this->assertNotEmpty( $parser->get_error() );
		$this->assertEmpty( $parser->get_result()['records'] );
	}

	/**
	 * Test an invalid XML file.
	 *
	 * @return void
	 */
	public function test_xml_invalid(): void {
		$parser = new Xml_Parser();
		$this->assertFalse( $parser->parse( $this->get_file( '<files><file><url>https://example.com/a.pdf</url>' ) ) );
		$this->assertNotEmpty( $parser->get_error() );
	}
}
