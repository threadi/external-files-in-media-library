<?php
/**
 * File as base for each parser of lists with URLs.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\Url_List;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object as base for each parser of lists with URLs.
 *
 * Each parser converts its file in the same structure:
 * - a list of fields (e.g., the columns of a CSV or the nodes of an XML)
 * - a list of records (e.g., the rows of a CSV or the repeated elements of an XML), each with its values per field
 *
 * A field could have multiple values in a single record (e.g., a CSV cell with multiple URLs or repeated XML nodes).
 */
abstract class Parser_Base {
	/**
	 * The detected format.
	 *
	 * @var string
	 */
	protected string $format = '';

	/**
	 * List of fields with their labels (key => label).
	 *
	 * @var array<string,string>
	 */
	protected array $fields = array();

	/**
	 * List of records.
	 *
	 * @var array<int,array<string,array<int,string>>>
	 */
	protected array $records = array();

	/**
	 * The error message, if parsing failed.
	 *
	 * @var string
	 */
	protected string $error = '';

	/**
	 * The maximum count of records to read.
	 *
	 * @var int
	 */
	protected int $max_records = 100000;

	/**
	 * Marker whether the maximum count of records has been reached.
	 *
	 * @var bool
	 */
	protected bool $limit_reached = false;

	/**
	 * List of allowed URL schemes.
	 *
	 * @var array<int,string>
	 */
	protected array $schemes = array( 'http', 'https' );

	/**
	 * Parse the given file.
	 *
	 * @param string $file The absolute path to the file.
	 *
	 * @return bool True if the file could be parsed.
	 */
	abstract public function parse( string $file ): bool;

	/**
	 * Set the maximum count of records to read.
	 *
	 * @param int $max_records The maximum.
	 *
	 * @return void
	 */
	public function set_max_records( int $max_records ): void {
		$this->max_records = max( 1, $max_records );
	}

	/**
	 * Set the allowed URL schemes.
	 *
	 * @param array<int,string> $schemes List of schemes (e.g. "https").
	 *
	 * @return void
	 */
	public function set_schemes( array $schemes ): void {
		$this->schemes = array_map( 'strtolower', $schemes );
	}

	/**
	 * Return the result of the parsing.
	 *
	 * @return array{format: string, fields: array<string,string>, records: array<int,array<string,array<int,string>>>, limit_reached: bool}
	 */
	public function get_result(): array {
		return array(
			'format'        => $this->format,
			'fields'        => $this->fields,
			'records'       => $this->records,
			'limit_reached' => $this->limit_reached,
		);
	}

	/**
	 * Return the error message.
	 *
	 * @return string
	 */
	public function get_error(): string {
		return $this->error;
	}

	/**
	 * Return whether the given value is a URL with an allowed scheme.
	 *
	 * @param string $value The value to check.
	 *
	 * @return bool
	 */
	public function is_url( string $value ): bool {
		// bail if value is empty or contains whitespace.
		if ( '' === $value || preg_match( '/\s/', $value ) ) {
			return false;
		}

		// bail if the value does not start with an allowed scheme.
		$scheme = strtolower( (string) strstr( $value, '://', true ) );
		if ( ! in_array( $scheme, $this->schemes, true ) ) {
			return false;
		}

		// return whether this is a valid URL (with encoded special chars, e.g. umlauts in the path).
		return false !== filter_var( $this->encode_url( $value ), FILTER_VALIDATE_URL );
	}

	/**
	 * Return the URL with encoded chars, which are not allowed in URLs (e.g. umlauts or spaces).
	 *
	 * @param string $url The URL.
	 *
	 * @return string
	 */
	protected function encode_url( string $url ): string {
		return (string) preg_replace_callback(
			'/[^\x21-\x7E]/',
			function ( array $matches ): string {
				return rawurlencode( $matches[0] );
			},
			$url
		);
	}

	/**
	 * Split the given value into multiple URLs, if it contains only URLs separated by comma, semicolon, pipe or whitespace.
	 *
	 * Other values are returned unchanged as single value.
	 *
	 * @param string $value The value.
	 *
	 * @return array<int,string>
	 */
	protected function split_value( string $value ): array {
		// bail if the value does not contain multiple schemes (it is a single URL or no URL).
		if ( substr_count( $value, '://' ) < 2 ) {
			return array( $value );
		}

		// split the value by possible separators between URLs.
		$parts = preg_split( '/(?:\s*[,;|]\s*|\s+)(?=[a-z][a-z0-9+.-]*:\/\/)/i', $value );

		// bail if value could not be split.
		if ( ! is_array( $parts ) || count( $parts ) < 2 ) {
			return array( $value );
		}

		// remove separators at the end of each part.
		$parts = array_map(
			function ( string $part ): string {
				return trim( $part, " \t\n\r\0\x0B,;|" );
			},
			$parts
		);

		// bail if any part is not a URL.
		foreach ( $parts as $part ) {
			if ( ! $this->is_url( $part ) ) {
				return array( $value );
			}
		}

		// return the list of URLs.
		return $parts;
	}

	/**
	 * Add a value to the given record.
	 *
	 * @param array<string,array<int,string>> $record The record.
	 * @param string                          $key The key of the field.
	 * @param string                          $value The value.
	 *
	 * @return void
	 */
	protected function add_value( array &$record, string $key, string $value ): void {
		// remove whitespace around the value.
		$value = trim( $value );

		// bail if value is empty.
		if ( '' === $value ) {
			return;
		}

		// add the field, if it is not known.
		if ( ! isset( $this->fields[ $key ] ) ) {
			$this->fields[ $key ] = $key;
		}

		// add the values (a value could contain multiple URLs).
		foreach ( $this->split_value( $value ) as $single_value ) {
			$record[ $key ][] = $single_value;
		}
	}

	/**
	 * Add a record to the list, if the limit is not reached.
	 *
	 * @param array<string,array<int,string>> $record The record.
	 *
	 * @return bool False if the limit is reached.
	 */
	protected function add_record( array $record ): bool {
		// bail if record is empty.
		if ( empty( $record ) ) {
			return true;
		}

		// bail if limit is reached.
		if ( count( $this->records ) >= $this->max_records ) {
			$this->limit_reached = true;
			return false;
		}

		// add the record.
		$this->records[] = $record;

		// return true to read the next record.
		return true;
	}
}
