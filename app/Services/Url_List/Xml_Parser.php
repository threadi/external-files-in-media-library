<?php
/**
 * File to parse XML files with URLs.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\Url_List;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use XMLReader;

// the properties of XMLReader are in camelCase.
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

/**
 * Object to parse XML files with URLs.
 *
 * The file is read as stream (also large files like product feeds could be read). The records are the repeated
 * elements, which contain the URLs (e.g., "url" in sitemaps, "item" in RSS feeds or "product" in product feeds).
 * The fields are the paths of the nodes and attributes relative to the record element (e.g., "image:image/image:loc"
 * or "enclosure/@url").
 *
 * Files with a DOCTYPE are not read, which prevents any XML external entity (XXE) or entity expansion attacks.
 */
class Xml_Parser extends Parser_Base {
	/**
	 * Namespace of WordPress exports (WXR).
	 */
	private const NS_WXR = 'http://wordpress.org/export/';

	/**
	 * Namespace of Google product feeds.
	 */
	private const NS_GOOGLE = 'http://base.google.com/ns/1.0';

	/**
	 * Namespace of Atom feeds.
	 */
	private const NS_ATOM = 'http://www.w3.org/2005/Atom';

	/**
	 * The path of the record element (e.g. "/rss/channel/item").
	 *
	 * @var string
	 */
	private string $record_path = '';

	/**
	 * Parse the given file.
	 *
	 * @param string $file The absolute path to the file.
	 *
	 * @return bool True if the file could be parsed.
	 */
	public function parse( string $file ): bool {
		// bail if XMLReader is not available.
		if ( ! class_exists( 'XMLReader' ) ) {
			$this->error = __( 'The PHP extension XMLReader is not available in your hosting.', 'external-files-in-media-library' );
			return false;
		}

		// bail if file is not readable.
		if ( ! is_readable( $file ) ) {
			$this->error = __( 'The file could not be read.', 'external-files-in-media-library' );
			return false;
		}

		// collect the structure of the file.
		$structure = $this->get_structure( $file );

		// bail if structure could not be read.
		if ( false === $structure ) {
			return false;
		}

		// detect the format.
		$this->format = $this->detect_format( $structure['root'], $structure['root_namespace'], $structure['namespaces'] );

		// detect the record element.
		$this->record_path = $this->detect_record_path( $structure['counts'], $structure['url_paths'], $structure['root'] );

		// read the records.
		return $this->read_records( $file );
	}

	/**
	 * Return a reader for the given file.
	 *
	 * @param string $file The absolute path to the file.
	 *
	 * @return XMLReader|false
	 */
	private function get_reader( string $file ): XMLReader|false {
		// create the reader without network access.
		$reader = new XMLReader();
		if ( ! $reader->open( $file, null, LIBXML_NONET | LIBXML_COMPACT ) ) {
			$this->error = __( 'The file is not a valid XML file.', 'external-files-in-media-library' );
			return false;
		}

		// return the reader.
		return $reader;
	}

	/**
	 * Read the structure of the file: the count of each element path and the paths of elements with URLs.
	 *
	 * @param string $file The absolute path to the file.
	 *
	 * @return array{root: string, root_namespace: string, namespaces: array<string,bool>, counts: array<string,int>, url_paths: array<string,int>}|false
	 */
	private function get_structure( string $file ): array|false {
		// get the reader.
		$reader = $this->get_reader( $file );

		// bail if reader could not be loaded.
		if ( ! $reader instanceof XMLReader ) {
			return false;
		}

		// collect the structure.
		$structure = array(
			'root'           => '',
			'root_namespace' => '',
			'namespaces'     => array(),
			'counts'         => array(),
			'url_paths'      => array(),
		);
		$stack     = array();

		// prevent output of libxml warnings for invalid files.
		$use_errors = libxml_use_internal_errors( true );

		// read the file.
		while ( $this->read( $reader ) ) {
			switch ( $reader->nodeType ) {
				case XMLReader::DOC_TYPE:
					// bail on any DOCTYPE to prevent XXE and entity expansion attacks.
					$reader->close();
					libxml_use_internal_errors( $use_errors );
					$this->error = __( 'XML files with a DOCTYPE are not supported for security reasons.', 'external-files-in-media-library' );
					return false;

				case XMLReader::ELEMENT:
					// add the element to the path.
					$stack[] = $reader->name;
					$path    = '/' . implode( '/', $stack );

					// count this element.
					$structure['counts'][ $path ] = ( $structure['counts'][ $path ] ?? 0 ) + 1;

					// remember the root and the used namespaces.
					if ( '' === $structure['root'] ) {
						$structure['root']           = $reader->localName;
						$structure['root_namespace'] = (string) $reader->namespaceURI;
					}
					// hint: empty() could not be used for properties of XMLReader.
					$namespace = (string) $reader->namespaceURI;
					if ( '' !== $namespace ) {
						$structure['namespaces'][ $namespace ] = true;
					}

					// check the attributes for URLs.
					if ( $reader->hasAttributes ) {
						while ( $reader->moveToNextAttribute() ) {
							if ( $this->contains_url( $reader->value ) ) {
								$structure['url_paths'][ $path ] = ( $structure['url_paths'][ $path ] ?? 0 ) + 1;
							}
						}
						$reader->moveToElement();
					}

					// remove empty elements from the path.
					if ( $reader->isEmptyElement ) {
						array_pop( $stack );
					}
					break;

				case XMLReader::TEXT:
				case XMLReader::CDATA:
					// count the element of this text if it is a URL.
					if ( ! empty( $stack ) && $this->contains_url( trim( $reader->value ) ) ) {
						$path                            = '/' . implode( '/', $stack );
						$structure['url_paths'][ $path ] = ( $structure['url_paths'][ $path ] ?? 0 ) + 1;
					}
					break;

				case XMLReader::END_ELEMENT:
					array_pop( $stack );
					break;
			}
		}

		// close the reader.
		$reader->close();
		$errors = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors( $use_errors );

		// bail if the file is not valid XML.
		if ( '' === $structure['root'] || ( ! empty( $errors ) && $this->is_fatal( $errors ) ) ) {
			$this->error = __( 'The file is not a valid XML file.', 'external-files-in-media-library' );
			return false;
		}

		// return the structure.
		return $structure;
	}

	/**
	 * Read the next node and return false on errors or at the end.
	 *
	 * @param XMLReader $reader The reader.
	 *
	 * @return bool
	 */
	private function read( XMLReader $reader ): bool {
		try {
			return (bool) @$reader->read(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Return whether the given libxml errors contain a fatal error.
	 *
	 * @param array<int,\LibXMLError> $errors List of errors.
	 *
	 * @return bool
	 */
	private function is_fatal( array $errors ): bool {
		foreach ( $errors as $error ) {
			if ( LIBXML_ERR_FATAL === $error->level ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Return whether the given value is a URL or contains only URLs.
	 *
	 * @param string $value The value.
	 *
	 * @return bool
	 */
	private function contains_url( string $value ): bool {
		// bail if value does not contain a scheme.
		if ( ! str_contains( $value, '://' ) ) {
			return false;
		}

		// return whether the (first) value is a URL.
		$values = $this->split_value( trim( $value ) );
		return $this->is_url( $values[0] );
	}

	/**
	 * Detect the format of the file.
	 *
	 * @param string              $root The local name of the root element.
	 * @param string              $root_namespace The namespace of the root element.
	 * @param array<string,mixed> $namespaces List of used namespaces.
	 *
	 * @return string
	 */
	private function detect_format( string $root, string $root_namespace, array $namespaces ): string {
		// check the used namespaces.
		foreach ( array_keys( $namespaces ) as $namespace ) {
			// WordPress export (the namespace contains the version, e.g. "http://wordpress.org/export/1.2/").
			if ( str_starts_with( (string) $namespace, self::NS_WXR ) ) {
				return 'wxr';
			}

			// Google product feed.
			if ( str_starts_with( (string) $namespace, self::NS_GOOGLE ) ) {
				return 'google-shopping';
			}
		}

		// sitemaps.
		if ( 'urlset' === $root ) {
			return 'sitemap';
		}
		if ( 'sitemapindex' === $root ) {
			return 'sitemap-index';
		}

		// feeds.
		if ( 'rss' === $root || 'RDF' === $root ) {
			return 'rss';
		}
		if ( 'feed' === $root && self::NS_ATOM === $root_namespace ) {
			return 'atom';
		}

		// any other XML.
		return 'xml';
	}

	/**
	 * Detect the path of the record element.
	 *
	 * For each element with URLs we use its shallowest repeated ancestor (or itself) as record.
	 * The record with the most URLs wins. If no element is repeated, the whole document is one record.
	 *
	 * @param array<string,int> $counts The count of each element path.
	 * @param array<string,int> $url_paths The count of URLs per element path.
	 * @param string            $root The name of the root element.
	 *
	 * @return string
	 */
	private function detect_record_path( array $counts, array $url_paths, string $root ): string {
		// collect the URLs per possible record path.
		$weights = array();
		foreach ( $url_paths as $path => $url_count ) {
			// get the shallowest repeated ancestor or the element itself.
			$parts       = explode( '/', ltrim( $path, '/' ) );
			$record_path = '';
			$ancestor    = '';
			foreach ( $parts as $part ) {
				$ancestor .= '/' . $part;
				if ( ( $counts[ $ancestor ] ?? 0 ) > 1 ) {
					$record_path = $ancestor;
					break;
				}
			}

			// bail if no repeated element has been found.
			if ( '' === $record_path ) {
				continue;
			}

			// add the URLs to this record path.
			$weights[ $record_path ] = ( $weights[ $record_path ] ?? 0 ) + $url_count;
		}

		// use the root element as single record, if no repeated element contains URLs.
		if ( empty( $weights ) ) {
			// get the path of the root element.
			foreach ( array_keys( $counts ) as $path ) {
				if ( substr_count( $path, '/' ) === 1 ) {
					return $path;
				}
			}
			return '/' . $root;
		}

		// return the path with the most URLs.
		arsort( $weights );
		return (string) array_key_first( $weights );
	}

	/**
	 * Read the records from the file.
	 *
	 * @param string $file The absolute path to the file.
	 *
	 * @return bool
	 */
	private function read_records( string $file ): bool {
		// get the reader.
		$reader = $this->get_reader( $file );

		// bail if reader could not be loaded.
		if ( ! $reader instanceof XMLReader ) {
			return false;
		}

		// prevent output of libxml warnings for invalid files.
		$use_errors = libxml_use_internal_errors( true );

		// the actual path of elements, the texts and children texts of each open element.
		$stack         = array();
		$texts         = array();
		$children      = array();
		$record        = array();
		$in_record     = false;
		$record_depth  = 0;
		$record_prefix = '';

		// read the file.
		while ( $this->read( $reader ) ) {
			switch ( $reader->nodeType ) {
				case XMLReader::ELEMENT:
					// add the element to the path.
					$stack[]    = $reader->name;
					$texts[]    = '';
					$children[] = array();
					$path       = '/' . implode( '/', $stack );

					// start a new record.
					if ( ! $in_record && $path === $this->record_path ) {
						$in_record     = true;
						$record        = array();
						$record_depth  = count( $stack );
						$record_prefix = $path . '/';
					}

					// add the attributes of elements in a record.
					if ( $in_record && $reader->hasAttributes ) {
						// get the relative path of this element.
						$relative = $path === $this->record_path ? '' : substr( $path, strlen( $record_prefix ) ) . '/';

						// add each attribute as value.
						while ( $reader->moveToNextAttribute() ) {
							// bail on namespace definitions.
							if ( str_starts_with( $reader->name, 'xmlns' ) ) {
								continue;
							}
							$this->add_value( $record, $relative . '@' . $reader->name, $reader->value );
						}
						$reader->moveToElement();
					}

					// end empty elements directly.
					if ( $reader->isEmptyElement ) {
						$this->end_element( $stack, $texts, $children, $record, $in_record, $record_depth, $record_prefix, $reader->localName );
					}
					break;

				case XMLReader::TEXT:
				case XMLReader::CDATA:
					// add the text to the actual element.
					if ( ! empty( $texts ) ) {
						$texts[ count( $texts ) - 1 ] .= $reader->value;
					}
					break;

				case XMLReader::END_ELEMENT:
					// end this element.
					$continue = $this->end_element( $stack, $texts, $children, $record, $in_record, $record_depth, $record_prefix, $reader->localName );

					// bail if the limit of records is reached.
					if ( ! $continue ) {
						break 2;
					}
					break;
			}
		}

		// close the reader.
		$reader->close();
		libxml_clear_errors();
		libxml_use_internal_errors( $use_errors );

		// return true as the file has been read.
		return true;
	}

	/**
	 * Handle the end of an element.
	 *
	 * - The text of the element is added as value of the record.
	 * - Pairs of "meta_key" and "meta_value" (e.g., in WordPress exports) are added with the key as field.
	 * - The record is added to the list if its element ends.
	 *
	 * @param array<int,string>               $stack The path of elements.
	 * @param array<int,string>               $texts The texts of the open elements.
	 * @param array<int,array<string,string>> $children The texts of the children of the open elements (by their local name).
	 * @param array<string,array<int,string>> $record The actual record.
	 * @param bool                            $in_record Whether we are in a record.
	 * @param int                             $record_depth The depth of the record element.
	 * @param string                          $record_prefix The path of the record element with "/" at the end.
	 * @param string                          $local_name The local name of the element.
	 *
	 * @return bool False if the limit of records is reached.
	 */
	private function end_element( array &$stack, array &$texts, array &$children, array &$record, bool &$in_record, int &$record_depth, string &$record_prefix, string $local_name ): bool {
		// get the path of this element.
		$path = '/' . implode( '/', $stack );

		// get the text and the children texts of this element.
		$text           = trim( (string) array_pop( $texts ) );
		$children_texts = (array) array_pop( $children );

		// remember the text for the parent element.
		if ( ! empty( $children ) ) {
			$children[ count( $children ) - 1 ][ $local_name ] = $text;
		}

		// add the values of elements in a record.
		if ( $in_record ) {
			// get the relative path of this element.
			$relative = $path === $this->record_path ? '' : substr( $path, strlen( $record_prefix ) );

			// add the text of this element (the text of the record element itself uses its name as key).
			if ( '' !== $text ) {
				$this->add_value( $record, '' !== $relative ? $relative : (string) end( $stack ), $text );
			}

			// add a pair of meta key and value with the key as field.
			if ( ! empty( $children_texts['meta_key'] ) && isset( $children_texts['meta_value'] ) ) {
				$this->add_value( $record, ( '' !== $relative ? $relative . '/' : '' ) . $children_texts['meta_key'], $children_texts['meta_value'] );
			}
		}

		// remove the element from the path.
		array_pop( $stack );

		// end the record if this is the record element.
		if ( $in_record && count( $stack ) < $record_depth ) {
			$in_record = false;
			return $this->add_record( $record );
		}

		// return true to read the next element.
		return true;
	}

	/**
	 * Return the path of the record element.
	 *
	 * @return string
	 */
	public function get_record_path(): string {
		return $this->record_path;
	}
}
