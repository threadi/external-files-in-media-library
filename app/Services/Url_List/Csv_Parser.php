<?php
/**
 * File to parse CSV files with URLs.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\Url_List;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to parse CSV files with URLs.
 *
 * - The delimiter is detected automatically (comma, semicolon, tab or pipe).
 * - A UTF-8 BOM is ignored, UTF-16 files and values in Windows-1252 are converted to UTF-8.
 * - The first row is used as header if it does not contain any URL, but the following rows do.
 */
class Csv_Parser extends Parser_Base {
	/**
	 * List of possible delimiters.
	 *
	 * @var array<int,string>
	 */
	private array $delimiters = array( ',', ';', "\t", '|' );

	/**
	 * The used delimiter.
	 *
	 * @var string
	 */
	private string $delimiter = ',';

	/**
	 * Marker whether the first row is used as header.
	 *
	 * @var bool
	 */
	private bool $has_header = false;

	/**
	 * Parse the given file.
	 *
	 * @param string $file The absolute path to the file.
	 *
	 * @return bool True if the file could be parsed.
	 */
	public function parse( string $file ): bool {
		// set the format.
		$this->format = 'csv';

		// bail if file is not readable.
		if ( ! is_readable( $file ) ) {
			$this->error = __( 'The file could not be read.', 'external-files-in-media-library' );
			return false;
		}

		// open the file.
		$handle = $this->get_handle( $file );

		// bail if file could not be opened.
		if ( ! is_resource( $handle ) ) {
			$this->error = __( 'The file could not be read.', 'external-files-in-media-library' );
			return false;
		}

		// detect the delimiter from the start of the file.
		$position        = (int) ftell( $handle );
		$this->delimiter = $this->detect_delimiter( (string) fread( $handle, 65536 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		fseek( $handle, $position );

		// read the first 20 rows of the file to detect the header.
		$rows       = array();
		$rows_count = 0;
		while ( $rows_count < 20 ) {
			$row = $this->read_row( $handle );

			// bail if end of file is reached.
			if ( false === $row ) {
				break;
			}

			// bail if row is empty.
			if ( empty( $row ) ) {
				continue;
			}

			// add the row to the list.
			$rows[] = $row;
			++$rows_count;
		}

		// bail if no rows could be read.
		if ( empty( $rows ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			$this->error = __( 'The file does not contain any data.', 'external-files-in-media-library' );
			return false;
		}

		// detect whether the first row is the header.
		$this->has_header = $this->is_header( $rows );

		// get the field keys from the header or by the position of the columns.
		$keys = $this->get_keys( $rows );

		// remove the header from the rows.
		if ( $this->has_header ) {
			array_shift( $rows );
		}

		// add the already read rows as records.
		$continue = true;
		foreach ( $rows as $row ) {
			$continue = $this->add_row( $row, $keys );
			if ( ! $continue ) {
				break;
			}
		}

		// read the other rows of the file.
		while ( $continue ) {
			$row = $this->read_row( $handle );

			// bail if end of file is reached.
			if ( false === $row ) {
				break;
			}

			// bail if row is empty.
			if ( empty( $row ) ) {
				continue;
			}

			// add the row.
			$continue = $this->add_row( $row, $keys );
		}

		// close the file.
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		// return true as the file has been parsed.
		return true;
	}

	/**
	 * Return the handle for the given file, positioned after a BOM.
	 *
	 * UTF-16 files are converted to UTF-8 in a temporary stream.
	 *
	 * @param string $file The absolute path to the file.
	 *
	 * @return resource|false
	 */
	private function get_handle( string $file ) {
		// open the file.
		$handle = fopen( $file, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		// bail if file could not be opened.
		if ( ! is_resource( $handle ) ) {
			return false;
		}

		// get the first bytes to check for a BOM.
		$bom = (string) fread( $handle, 3 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread

		// skip the UTF-8 BOM.
		if ( "\xEF\xBB\xBF" === $bom ) {
			return $handle;
		}

		// convert UTF-16 to UTF-8.
		$encoding = '';
		if ( str_starts_with( $bom, "\xFF\xFE" ) ) {
			$encoding = 'UTF-16LE';
		} elseif ( str_starts_with( $bom, "\xFE\xFF" ) ) {
			$encoding = 'UTF-16BE';
		}
		if ( ! empty( $encoding ) && function_exists( 'mb_convert_encoding' ) ) {
			// get the content without BOM.
			fseek( $handle, 2 );
			$content = (string) stream_get_contents( $handle );
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

			// write the converted content in a temporary stream.
			$temp = fopen( 'php://temp', 'w+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			if ( ! is_resource( $temp ) ) {
				return false;
			}
			fwrite( $temp, (string) mb_convert_encoding( $content, 'UTF-8', $encoding ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			rewind( $temp );
			return $temp;
		}

		// start from the beginning, as no BOM is used.
		rewind( $handle );
		return $handle;
	}

	/**
	 * Detect the delimiter from the given sample of the file.
	 *
	 * The delimiter, which results in the same count of columns (more than one) in the most lines, is used.
	 *
	 * @param string $sample The sample.
	 *
	 * @return string
	 */
	private function detect_delimiter( string $sample ): string {
		// get the first lines of the sample (the last one could be incomplete).
		$lines = preg_split( '/\r\n|\r|\n/', $sample );
		if ( ! is_array( $lines ) ) {
			return ',';
		}
		if ( count( $lines ) > 1 ) {
			array_pop( $lines );
		}
		$lines = array_slice(
			array_values(
				array_filter(
					$lines,
					function ( string $line ): bool {
						return '' !== $line;
					}
				)
			),
			0,
			10
		);

		// bail if no lines are given.
		if ( empty( $lines ) ) {
			return ',';
		}

		// check each delimiter.
		$best       = ',';
		$best_score = 0;
		foreach ( $this->delimiters as $delimiter ) {
			// get the count of columns per line.
			$counts = array();
			foreach ( $lines as $line ) {
				$counts[] = count( str_getcsv( $line, $delimiter, '"', '' ) );
			}

			// get the most used count of columns.
			$frequencies = array_count_values( $counts );
			arsort( $frequencies );
			$columns = (int) array_key_first( $frequencies );

			// bail if this delimiter does not separate anything.
			if ( $columns < 2 ) {
				continue;
			}

			// the score is the amount of lines with this count of columns, weighted by the count of columns.
			$score = $frequencies[ $columns ] * 1000 + $columns;
			if ( $score > $best_score ) {
				$best       = $delimiter;
				$best_score = $score;
			}
		}

		// return the best delimiter.
		return $best;
	}

	/**
	 * Read the next row from the file.
	 *
	 * @param resource $handle The file handle.
	 *
	 * @return array<int,string>|false List of values or false at the end of the file.
	 */
	private function read_row( $handle ): array|false {
		// read the row.
		$row = fgetcsv( $handle, 0, $this->delimiter, '"', '' );

		// bail at end of file.
		if ( false === $row ) {
			return false;
		}

		// convert the values.
		$values = array();
		foreach ( $row as $value ) {
			$values[] = $this->convert_value( (string) $value );
		}

		// return an empty list, if the line does not contain any value.
		if ( 1 === count( $values ) && '' === $values[0] ) {
			return array();
		}

		// return the values.
		return $values;
	}

	/**
	 * Convert a value to UTF-8 and remove whitespace around it.
	 *
	 * @param string $value The value.
	 *
	 * @return string
	 */
	private function convert_value( string $value ): string {
		// convert Windows-1252 (e.g., from Excel) to UTF-8.
		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $value, 'UTF-8' ) ) {
			$value = (string) mb_convert_encoding( $value, 'UTF-8', 'Windows-1252' );
		}

		// return the value without whitespace around it.
		return trim( $value );
	}

	/**
	 * Return whether the first of the given rows is a header.
	 *
	 * This is the case if the first row does not contain any URL.
	 *
	 * @param array<int,array<int,string>> $rows The first rows of the file.
	 *
	 * @return bool
	 */
	private function is_header( array $rows ): bool {
		// check each value of the first row.
		foreach ( $rows[0] as $value ) {
			foreach ( $this->split_value( $value ) as $single_value ) {
				// bail if this value is a URL.
				if ( $this->is_url( $single_value ) ) {
					return false;
				}
			}
		}

		// the first row is the header, if it is not the only row.
		return count( $rows ) > 1;
	}

	/**
	 * Return the keys of the columns.
	 *
	 * The names from the header are used as keys. Columns without header use their position.
	 *
	 * @param array<int,array<int,string>> $rows The first rows of the file.
	 *
	 * @return array<int,string>
	 */
	private function get_keys( array $rows ): array {
		// get the max count of columns.
		$columns = 0;
		foreach ( $rows as $row ) {
			$columns = max( $columns, count( $row ) );
		}

		// create the keys.
		$keys = array();
		for ( $i = 0; $i < $columns; $i++ ) {
			// use the header, if it is set.
			$key = $this->has_header && isset( $rows[0][ $i ] ) ? $rows[0][ $i ] : '';

			// use the position, if no header is set for this column.
			if ( '' === $key ) {
				/* translators: %1$d will be replaced by a number. */
				$key = sprintf( __( 'Column %1$d', 'external-files-in-media-library' ), $i + 1 );
			}

			// make the key unique.
			$unique_key = $key;
			$counter    = 2;
			while ( in_array( $unique_key, $keys, true ) ) {
				$unique_key = $key . ' (' . $counter . ')';
				++$counter;
			}

			// add the key.
			$keys[ $i ] = $unique_key;

			// add the field.
			$this->fields[ $unique_key ] = $unique_key;
		}

		// return the list of keys.
		return $keys;
	}

	/**
	 * Add a row as record.
	 *
	 * @param array<int,string> $row The values of the row.
	 * @param array<int,string> $keys The keys of the columns.
	 *
	 * @return bool False if the limit of records is reached.
	 */
	private function add_row( array $row, array &$keys ): bool {
		// collect the values.
		$record = array();
		foreach ( $row as $i => $value ) {
			// add a key for a column, which is not in the header.
			if ( ! isset( $keys[ $i ] ) ) {
				/* translators: %1$d will be replaced by a number. */
				$keys[ $i ] = sprintf( __( 'Column %1$d', 'external-files-in-media-library' ), $i + 1 );
			}

			// add the value.
			$this->add_value( $record, $keys[ $i ], $value );
		}

		// add the record.
		return $this->add_record( $record );
	}

	/**
	 * Return the used delimiter.
	 *
	 * @return string
	 */
	public function get_delimiter(): string {
		return $this->delimiter;
	}

	/**
	 * Return whether the first row has been used as header.
	 *
	 * @return bool
	 */
	public function has_header(): bool {
		return $this->has_header;
	}
}
