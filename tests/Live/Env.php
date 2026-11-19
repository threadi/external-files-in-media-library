<?php
/**
 * File to read the configuration of the live tests from ".env.live".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Live;

/**
 * Object to read ".env.live" and the services of the live tests.
 *
 * Without WordPress, as it is used in the bootstrap before WordPress is loaded.
 */
class Env {
	/**
	 * The values of the file.
	 *
	 * @var array<string,string>
	 */
	private array $values = array();

	/**
	 * The path of the used file, empty if none exists.
	 *
	 * @var string
	 */
	private string $file = '';

	/**
	 * The instance of this object.
	 *
	 * @var Env|null
	 */
	private static ?Env $instance = null;

	/**
	 * Return the instance of this object.
	 *
	 * @return Env
	 */
	public static function get_instance(): Env {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
			self::$instance->load( self::get_file_path() );
		}

		return self::$instance;
	}

	/**
	 * Return the path of ".env.live": from the environment variable EFML_LIVE_ENV, otherwise in the plugin directory.
	 *
	 * @return string
	 */
	public static function get_file_path(): string {
		$path = getenv( 'EFML_LIVE_ENV' );
		if ( is_string( $path ) && '' !== $path ) {
			return $path;
		}

		return dirname( __DIR__, 2 ) . '/.env.live';
	}

	/**
	 * Load the given file.
	 *
	 * @param string $file The path of the file.
	 *
	 * @return void
	 */
	public function load( string $file ): void {
		$this->values = array();
		$this->file   = '';

		// bail if the file does not exist.
		if ( ! is_readable( $file ) ) {
			return;
		}

		$this->file   = $file;
		$this->values = self::parse( (string) file_get_contents( $file ), dirname( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/**
	 * Return the values of the given content of a ".env" file.
	 *
	 * Lines with "KEY=value", optionally with "export " in front and with the value in quotes. Lines starting with
	 * "#" are comments. Keys ending with "_FILE" set the key without this suffix to the content of the given file
	 * (relative paths are relative to the directory of the ".env" file), if this key is not set itself.
	 *
	 * @param string $content   The content.
	 * @param string $directory The directory of the file.
	 *
	 * @return array<string,string>
	 */
	public static function parse( string $content, string $directory = '' ): array {
		$values = array();
		$files  = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $content ) as $line ) {
			$line = trim( (string) $line );

			// bail if this is an empty line or a comment.
			if ( '' === $line || str_starts_with( $line, '#' ) ) {
				continue;
			}

			// remove "export ".
			if ( str_starts_with( $line, 'export ' ) ) {
				$line = trim( substr( $line, 7 ) );
			}

			// bail if this is no assignment.
			if ( 1 !== preg_match( '/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches ) ) {
				continue;
			}

			// get the value without quotes, without quotes a comment after the value is removed.
			$value = $matches[2];
			if ( strlen( $value ) >= 2 && ( ( '"' === $value[0] && str_ends_with( $value, '"' ) ) || ( "'" === $value[0] && str_ends_with( $value, "'" ) ) ) ) {
				$value = substr( $value, 1, -1 );
				if ( '"' === $matches[2][0] ) {
					$value = str_replace( array( '\\n', '\\"' ), array( "\n", '"' ), $value );
				}
			} else {
				$value = trim( (string) preg_replace( '/\s+#.*$/', '', $value ) );
			}

			// remember files.
			if ( str_ends_with( $matches[1], '_FILE' ) ) {
				$files[ substr( $matches[1], 0, -5 ) ] = $value;
			}

			$values[ $matches[1] ] = $value;
		}

		// read the files.
		foreach ( $files as $key => $path ) {
			// bail if the key is set itself or the path is empty.
			if ( isset( $values[ $key ] ) || '' === $path ) {
				continue;
			}

			// use paths relative to the directory of the file.
			if ( '' !== $directory && ! str_starts_with( $path, '/' ) && 1 !== preg_match( '/^[A-Za-z]:[\\\\\/]/', $path ) ) {
				$path = rtrim( $directory, '/\\' ) . '/' . $path;
			}

			if ( is_readable( $path ) ) {
				$values[ $key ] = trim( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}

		return $values;
	}

	/**
	 * Return whether a ".env.live" exists.
	 *
	 * @return bool
	 */
	public function exists(): bool {
		return '' !== $this->file;
	}

	/**
	 * Return the path of the used file.
	 *
	 * @return string
	 */
	public function get_file(): string {
		return $this->file;
	}

	/**
	 * Return the value of the given key: from the environment, otherwise from the file.
	 *
	 * @param string $key     The key.
	 * @param string $default_value The default value.
	 *
	 * @return string
	 */
	public function get( string $key, string $default_value = '' ): string {
		$value = getenv( $key );
		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}

		return isset( $this->values[ $key ] ) && '' !== $this->values[ $key ] ? $this->values[ $key ] : $default_value;
	}

	/**
	 * Return whether the given key has a value.
	 *
	 * @param string $key The key.
	 *
	 * @return bool
	 */
	public function has( string $key ): bool {
		return '' !== $this->get( $key );
	}

	/**
	 * Return the values, which are constants of the plugin and its extensions: all keys of the file and the environment
	 * starting with "EFML_" except the settings of the live tests ("EFML_LIVE_…") and of the extensions ("EFML_ADDON…").
	 * Values of the environment win.
	 *
	 * @return array<string,string>
	 */
	public function get_constants(): array {
		$constants = array();
		foreach ( array_unique( array_merge( array_keys( $this->values ), array_keys( getenv() ) ) ) as $key ) {
			$key   = (string) $key;
			$value = $this->get( $key );

			// bail if this is no constant of the plugin.
			if ( ! str_starts_with( $key, 'EFML_' ) || str_starts_with( $key, 'EFML_LIVE_' ) || str_starts_with( $key, 'EFML_ADDON' ) || str_ends_with( $key, '_FILE' ) || '' === $value ) {
				continue;
			}

			$constants[ $key ] = $value;
		}

		return $constants;
	}

	/**
	 * Return the definitions of the services from "services.json".
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_service_definitions(): array {
		$data = json_decode( (string) file_get_contents( __DIR__ . '/services.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return is_array( $data ) && isset( $data['services'] ) && is_array( $data['services'] ) ? $data['services'] : array();
	}

	/**
	 * Return the repositories of the extensions from "services.json".
	 *
	 * @return array<string,string>
	 */
	public static function get_addons(): array {
		$data = json_decode( (string) file_get_contents( __DIR__ . '/services.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return is_array( $data ) && isset( $data['addons'] ) && is_array( $data['addons'] ) ? $data['addons'] : array();
	}

	/**
	 * Return the missing keys of the given service.
	 *
	 * @param array<string,mixed> $service The definition of the service.
	 *
	 * @return array<int,string>
	 */
	public function get_missing_keys( array $service ): array {
		$missing = array();
		foreach ( (array) ( $service['required'] ?? array() ) as $key ) {
			if ( ! $this->has( (string) $key ) ) {
				$missing[] = (string) $key;
			}
		}

		return $missing;
	}

	/**
	 * Return the services with all required keys.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_configured_services(): array {
		return array_values( array_filter( self::get_service_definitions(), fn( array $service ) => empty( $this->get_missing_keys( $service ) ) ) );
	}

	/**
	 * Return whether anything is configured for the live tests: a service or single URLs.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return ! empty( $this->get_configured_services() ) || $this->has( 'EFML_LIVE_URLS' );
	}
}
