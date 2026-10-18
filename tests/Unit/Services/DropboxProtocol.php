<?php
/**
 * Tests for the DropBox protocol with a simulated DropBox client.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services;

use ExternalFilesInMediaLibrary\Services\DropBox\Protocol;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Spatie\Dropbox\Client;

/**
 * Simulated DropBox client with files in memory.
 */
class DropboxFakeClient extends Client {
	/**
	 * The files with their path and content.
	 *
	 * @var array<string,string>
	 */
	private array $files;

	/**
	 * The maximum number of entries per part of a folder listing.
	 *
	 * @var int
	 */
	private int $page_size;

	/**
	 * Constructor.
	 *
	 * @param array<string,string> $files The files with their path and content.
	 * @param int                  $page_size The maximum number of entries per part of a folder listing.
	 */
	public function __construct( array $files, int $page_size = 1000 ) {
		parent::__construct( 'test-token' );
		$this->files     = $files;
		$this->page_size = $page_size;
	}

	/**
	 * Return the entry of a file like DropBox.
	 *
	 * @param string $path The path.
	 *
	 * @return array<string,mixed>
	 */
	private function get_file_entry( string $path ): array {
		return array(
			'.tag'            => 'file',
			'name'            => basename( $path ),
			'path_display'    => $path,
			'path_lower'      => strtolower( $path ),
			'client_modified' => '2026-10-01T10:00:00Z',
			'size'            => strlen( $this->files[ $path ] ),
		);
	}

	/**
	 * Return the entry of a folder like DropBox.
	 *
	 * @param string $path The path.
	 *
	 * @return array<string,mixed>
	 */
	private function get_folder_entry( string $path ): array {
		return array(
			'.tag'         => 'folder',
			'name'         => basename( $path ),
			'path_display' => $path,
			'path_lower'   => strtolower( $path ),
		);
	}

	/**
	 * Return the metadata of a file or folder.
	 *
	 * @param string $path The path.
	 *
	 * @return array<string,mixed>
	 * @throws ClientException If the path does not exist.
	 */
	public function getMetadata( string $path ): array {
		if ( isset( $this->files[ $path ] ) ) {
			return $this->get_file_entry( $path );
		}
		foreach ( array_keys( $this->files ) as $file ) {
			if ( str_starts_with( $file, rtrim( $path, '/' ) . '/' ) ) {
				return $this->get_folder_entry( rtrim( $path, '/' ) );
			}
		}
		throw new ClientException( 'path/not_found', new Request( 'POST', 'https://api.dropboxapi.com/2/files/get_metadata' ), new Response( 409 ) );
	}

	/**
	 * Return the entries of a folder (always with its subfolders), in parts like DropBox.
	 *
	 * @param string $path The path.
	 * @param bool   $recursive Not used.
	 *
	 * @return array<string,mixed>
	 */
	public function listFolder( string $path = '', bool $recursive = false ): array {
		$prefix  = '/' === $path ? '/' : rtrim( $path, '/' ) . '/';
		$entries = array();
		$folders = array();
		foreach ( array_keys( $this->files ) as $file ) {
			if ( ! str_starts_with( $file, $prefix ) ) {
				continue;
			}
			$folder = dirname( $file );
			if ( '/' !== $folder && ! isset( $folders[ $folder ] ) && $folder . '/' !== $prefix ) {
				$folders[ $folder ] = true;
				$entries[]          = $this->get_folder_entry( $folder );
			}
			$entries[] = $this->get_file_entry( $file );
		}
		return $this->get_part( $entries, 0 );
	}

	/**
	 * Return the next part of a folder listing.
	 *
	 * @param string $cursor The cursor.
	 *
	 * @return array<string,mixed>
	 */
	public function listFolderContinue( string $cursor = '' ): array {
		$data = (array) json_decode( (string) base64_decode( $cursor ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return $this->get_part( $data['entries'], absint( $data['offset'] ) );
	}

	/**
	 * Return a part of the entries.
	 *
	 * @param array<int,array<string,mixed>> $entries The entries.
	 * @param int                            $offset The start of the part.
	 *
	 * @return array<string,mixed>
	 */
	private function get_part( array $entries, int $offset ): array {
		$next = $offset + $this->page_size;
		return array(
			'entries'  => array_slice( $entries, $offset, $this->page_size ),
			'has_more' => $next < count( $entries ),
			'cursor'   => base64_encode( (string) wp_json_encode( array( 'entries' => $entries, 'offset' => $next ) ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		);
	}

	/**
	 * Return the content of a file as stream.
	 *
	 * @param string $path The path (in lower case).
	 *
	 * @return resource
	 */
	public function download( string $path ) {
		foreach ( $this->files as $file => $content ) {
			if ( strtolower( $file ) === $path ) {
				$stream = fopen( 'php://memory', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
				fwrite( $stream, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
				rewind( $stream );
				return $stream;
			}
		}
		throw new ClientException( 'path/not_found', new Request( 'POST', 'https://content.dropboxapi.com/2/files/download' ), new Response( 409 ) );
	}
}

/**
 * Object to test the DropBox protocol: the import of files and folders.
 */
class DropboxProtocol extends externalFilesTests {
	/**
	 * The simulated client.
	 *
	 * @var DropboxFakeClient|null
	 */
	private ?DropboxFakeClient $client = null;

	/**
	 * Prepare each test: a DropBox with files in folders.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->use_client( 1000 );
		add_filter( 'efml_dropbox_client', array( $this, 'get_client' ) );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_dropbox_client', array( $this, 'get_client' ) );
		parent::tear_down();
	}

	/**
	 * Use a new simulated client with the given size of the parts of folder listings.
	 *
	 * @param int $page_size The size.
	 *
	 * @return void
	 */
	private function use_client( int $page_size ): void {
		$this->client = new DropboxFakeClient(
			array(
				'/Photos/Red.jpg'      => 'red',
				'/Photos/Sub/Blue.png' => 'blue',
				'/Other/Green.jpg'     => 'green',
				'/Root.jpg'            => 'root',
			),
			$page_size
		);
	}

	/**
	 * Return the simulated client.
	 *
	 * @return DropboxFakeClient|null
	 */
	public function get_client(): ?DropboxFakeClient {
		return $this->client;
	}

	/**
	 * Return the files for the given URL with their URL and content.
	 *
	 * @param string $url The URL.
	 *
	 * @return array<string,string>
	 */
	private function get_files( string $url ): array {
		$files = array();
		foreach ( @( new Protocol( $url ) )->get_url_infos() as $file ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$files[ $file['url'] ] = (string) file_get_contents( $file['tmp-file'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
		ksort( $files );
		return $files;
	}

	/**
	 * Test the import of a folder: all files in it and its subfolders, not the folders themselves.
	 *
	 * The URLs of imported files start with the name of the service.
	 *
	 * @return void
	 */
	public function test_folder(): void {
		$expected = array(
			'dropbox/Photos/Red.jpg'      => 'red',
			'dropbox/Photos/Sub/Blue.png' => 'blue',
		);
		$this->assertSame( $expected, $this->get_files( 'DropBox/Photos' ) );
		$this->assertSame( $expected, $this->get_files( 'DropBox/Photos/' ) );
		$this->assertSame( array( 'dropbox/Photos/Sub/Blue.png' => 'blue' ), $this->get_files( 'DropBox/Photos/Sub' ) );
	}

	/**
	 * Test the import of a large folder, which DropBox returns in multiple parts.
	 *
	 * @return void
	 */
	public function test_folder_in_parts(): void {
		$this->use_client( 1 );
		$this->assertCount( 4, $this->get_files( 'DropBox' ) );
		$this->assertCount( 2, $this->get_files( 'DropBox/Photos' ) );
	}

	/**
	 * Test the import of all files of the DropBox.
	 *
	 * @return void
	 */
	public function test_root(): void {
		$this->assertSame( array( 'dropbox/Other/Green.jpg', 'dropbox/Photos/Red.jpg', 'dropbox/Photos/Sub/Blue.png', 'dropbox/Root.jpg' ), array_keys( $this->get_files( 'DropBox' ) ) );
	}

	/**
	 * Test the import of a single file.
	 *
	 * @return void
	 */
	public function test_single_file(): void {
		$this->assertSame( array( 'dropbox/Photos/Red.jpg' => 'red' ), $this->get_files( 'dropbox/Photos/Red.jpg' ) );
	}

	/**
	 * Test the import of a path, which does not exist.
	 *
	 * @return void
	 */
	public function test_missing_path(): void {
		$this->assertSame( array(), $this->get_files( 'DropBox/Missing/File.jpg' ) );
		$this->assertNotEmpty( \ExternalFilesInMediaLibrary\Plugin\Log::get_instance()->get_logs( '/Missing/File.jpg', 'error' ) );
	}
}
