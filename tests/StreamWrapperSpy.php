<?php
/**
 * File for a stream wrapper, which records the access to URLs of a protocol (e.g. ftp://) during tests.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests;

/**
 * Object to record each access to a stream protocol instead of connecting to the server.
 */
class StreamWrapperSpy {
	/**
	 * The stream context (set by PHP).
	 *
	 * @var resource|null
	 */
	public $context;

	/**
	 * List of recorded accesses.
	 *
	 * @var array<int,string>
	 */
	public static array $calls = array();

	/**
	 * Replace the stream wrappers of the given protocols with this spy.
	 *
	 * @param array<int,string> $protocols List of protocols, e.g. ftp and ftps.
	 *
	 * @return void
	 */
	public static function start( array $protocols ): void {
		self::$calls = array();
		foreach ( $protocols as $protocol ) {
			if ( in_array( $protocol, stream_get_wrappers(), true ) ) {
				stream_wrapper_unregister( $protocol );
			}
			stream_wrapper_register( $protocol, self::class );
		}
	}

	/**
	 * Restore the original stream wrappers of the given protocols.
	 *
	 * @param array<int,string> $protocols List of protocols, e.g. ftp and ftps.
	 *
	 * @return void
	 */
	public static function stop( array $protocols ): void {
		foreach ( $protocols as $protocol ) {
			stream_wrapper_restore( $protocol );
		}
	}

	/**
	 * Record a "stat" (e.g. is_file()).
	 *
	 * @param string $path The URL.
	 * @param int    $flags The flags.
	 *
	 * @return array<int|string,int>|false
	 */
	public function url_stat( string $path, int $flags ): array|false {
		self::$calls[] = 'stat ' . $path;
		return false;
	}

	/**
	 * Record an unlink.
	 *
	 * @param string $path The URL.
	 *
	 * @return bool
	 */
	public function unlink( string $path ): bool {
		self::$calls[] = 'unlink ' . $path;
		return false;
	}

	/**
	 * Record an open.
	 *
	 * @param string      $path The URL.
	 * @param string      $mode The mode.
	 * @param int         $options The options.
	 * @param string|null $opened_path The opened path.
	 *
	 * @return bool
	 */
	public function stream_open( string $path, string $mode, int $options, ?string &$opened_path ): bool {
		self::$calls[] = 'open ' . $path;
		return false;
	}
}
