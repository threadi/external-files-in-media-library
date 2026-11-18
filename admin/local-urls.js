/**
 * Show exported files in the editors, also if their old local URL is still used and the web server does not pass
 * requests for missing files to WordPress (so they could not be redirected to their actual URL).
 *
 * Only the display is changed: the attributes of blocks and the contents are not changed.
 *
 * @package
 */
( function () {
	'use strict';

	// get the configuration.
	const config = window.efmlLocalUrlsConfig;
	if ( ! config || ! config.endpoint || ! config.baseUrl || ! window.fetch ) {
		return;
	}

	// get the URL of the uploads directory without scheme, e.g. "//example.com/wp-content/uploads/".
	let base = '';
	try {
		const url = new URL( config.baseUrl );
		base = '//' + url.host + url.pathname.replace( /\/?$/, '/' );
	} catch {
		return;
	}

	// the actual URLs of local files (empty, if they have none), the callbacks waiting for them and the queue.
	const urls = {};
	const waiting = {};
	const queue = [];
	let timer = 0;

	// the handled URL of each image and the handled style of each element (no attributes, as editors could save them).
	const handledImages = new WeakMap();
	const handledStyles = new WeakMap();

	/**
	 * Return the file relative to the uploads directory of the given URL, if it is in the uploads directory.
	 *
	 * @param {string} url The URL (also without domain).
	 * @return {string} The file or an empty string.
	 */
	function getFile( url ) {
		if ( ! url || /^(data|blob):/i.test( url ) ) {
			return '';
		}
		let path = '';
		try {
			const parsed = new URL( url, config.siteUrl );
			path = '//' + parsed.host + parsed.pathname;
		} catch {
			return '';
		}
		if ( 0 !== path.indexOf( base ) ) {
			return '';
		}
		let file = '';
		try {
			file = decodeURIComponent( path.substring( base.length ) );
		} catch {
			return '';
		}
		if (
			'' === file ||
			-1 !== file.indexOf( '..' ) ||
			'/' === file.slice( -1 )
		) {
			return '';
		}
		return file;
	}

	/**
	 * Request the actual URLs of the queued files.
	 *
	 * @return {void}
	 */
	function flush() {
		timer = 0;
		const files = queue.splice( 0, 100 );
		if ( queue.length ) {
			timer = window.setTimeout( flush, 50 );
		}
		if ( ! files.length ) {
			return;
		}
		window
			.fetch( config.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce,
				},
				body: JSON.stringify( { files } ),
			} )
			.then( ( response ) => ( response.ok ? response.json() : {} ) )
			.catch( () => ( {} ) )
			.then( ( result ) => {
				files.forEach( ( file ) => {
					const url =
						result && 'string' === typeof result[ file ]
							? result[ file ]
							: '';
					urls[ file ] = url;
					const callbacks = waiting[ file ] || [];
					delete waiting[ file ];
					callbacks.forEach( ( callback ) => callback( url ) );
				} );
			} );
	}

	/**
	 * Get the actual URLs of the given URLs. The callback gets an object with the URLs to replace.
	 *
	 * @param {Array<string>}          list     List of URLs.
	 * @param {function(Object): void} callback The callback.
	 * @return {boolean} False, if none of the URLs is in the uploads directory.
	 */
	function resolve( list, callback ) {
		const entries = [];
		list.forEach( ( url ) => {
			const file = getFile( url );
			if ( '' !== file ) {
				entries.push( { url, file } );
			}
		} );
		if ( ! entries.length ) {
			return false;
		}
		const replacements = {};
		let open = entries.length;
		const done = ( entry, actual ) => {
			if ( actual ) {
				replacements[ entry.url ] = actual;
			}
			open--;
			if ( 0 === open ) {
				callback( replacements );
			}
		};
		entries.forEach( ( entry ) => {
			if ( Object.prototype.hasOwnProperty.call( urls, entry.file ) ) {
				done( entry, urls[ entry.file ] );
				return;
			}
			if ( ! waiting[ entry.file ] ) {
				waiting[ entry.file ] = [];
				queue.push( entry.file );
			}
			waiting[ entry.file ].push( ( actual ) => done( entry, actual ) );
			if ( ! timer ) {
				timer = window.setTimeout( flush, 50 );
			}
		} );
		return true;
	}

	/**
	 * Return whether the given element could be changed: in editable areas only, if the editor keeps the original
	 * value for saving (TinyMCE with its "data-mce-*" attributes), so the contents are not changed.
	 *
	 * @param {Element} element   The element.
	 * @param {string}  attribute The attribute to change.
	 * @return {boolean} True, if it could be changed.
	 */
	function isChangeable( element, attribute ) {
		if (
			! element.closest(
				'[contenteditable="true"], body[contenteditable="true"]'
			)
		) {
			return true;
		}
		return element.hasAttribute( 'data-mce-' + attribute );
	}

	/**
	 * Show the image with its actual URL, if it could not be loaded via its local URL.
	 *
	 * @param {HTMLImageElement} img The image.
	 * @return {void}
	 */
	function fixImage( img ) {
		const src = img.getAttribute( 'src' ) || '';

		// bail if this URL has been handled already (e.g. the actual URL could not be loaded, too).
		if ( handledImages.get( img ) === src ) {
			return;
		}

		// bail if the image could not be changed.
		if ( ! isChangeable( img, 'src' ) ) {
			return;
		}

		// collect the URLs of the image.
		const list = [ src ];
		const srcset = img.getAttribute( 'srcset' ) || '';
		srcset.split( ',' ).forEach( ( part ) => {
			const url = part.trim().split( /\s+/ )[ 0 ];
			if ( url ) {
				list.push( url );
			}
		} );

		// mark it and replace its URLs.
		handledImages.set( img, src );
		resolve( list, ( replacements ) => {
			if (
				! Object.keys( replacements ).length ||
				img.getAttribute( 'src' ) !== src
			) {
				return;
			}
			if ( srcset ) {
				img.setAttribute(
					'srcset',
					srcset
						.split( ',' )
						.map( ( part ) => {
							const pieces = part.trim().split( /\s+/ );
							if ( replacements[ pieces[ 0 ] ] ) {
								pieces[ 0 ] = replacements[ pieces[ 0 ] ];
							}
							return pieces.join( ' ' );
						} )
						.join( ', ' )
				);
			}
			if ( replacements[ src ] ) {
				handledImages.set( img, replacements[ src ] );
				img.setAttribute( 'src', replacements[ src ] );
			}
		} );
	}

	/**
	 * Show background images and other files in inline styles with their actual URL.
	 *
	 * @param {HTMLElement} element The element.
	 * @return {void}
	 */
	function fixStyle( element ) {
		const style = element.getAttribute( 'style' ) || '';

		// bail if this style has been handled already or if it could not be changed.
		if (
			handledStyles.get( element ) === style ||
			! isChangeable( element, 'style' )
		) {
			return;
		}
		handledStyles.set( element, style );

		// collect the URLs in the style.
		const list = [];
		style.replace(
			/url\(\s*(['"]?)([^'")]+)\1\s*\)/gi,
			( match, quote, url ) => {
				list.push( url );
				return match;
			}
		);

		// replace them.
		resolve( list, ( replacements ) => {
			if (
				! Object.keys( replacements ).length ||
				element.getAttribute( 'style' ) !== style
			) {
				return;
			}
			const changed = style.replace(
				/url\(\s*(['"]?)([^'")]+)\1\s*\)/gi,
				( match, quote, url ) =>
					replacements[ url ]
						? 'url(' + quote + replacements[ url ] + quote + ')'
						: match
			);
			handledStyles.set( element, changed );
			element.setAttribute( 'style', changed );
		} );
	}

	/**
	 * Check the given document for images, which could not be loaded, and styles with local URLs.
	 *
	 * @param {Document} doc The document.
	 * @return {void}
	 */
	function scan( doc ) {
		doc.querySelectorAll( 'img[src]' ).forEach( ( img ) => {
			if ( img.complete && 0 === img.naturalWidth ) {
				fixImage( img );
			}
		} );
		doc.querySelectorAll( '[style*="url("]' ).forEach( fixStyle );
	}

	/**
	 * Watch the given document.
	 *
	 * @param {Document} doc The document.
	 * @return {void}
	 */
	function attach( doc ) {
		if ( ! doc || doc.efmlLocalUrls ) {
			return;
		}
		doc.efmlLocalUrls = true;

		// images, which could not be loaded.
		doc.addEventListener(
			'error',
			( event ) => {
				if ( event.target && 'IMG' === event.target.tagName ) {
					fixImage( event.target );
				}
			},
			true
		);

		// styles of new and changed elements.
		let scheduled = 0;
		const observer = new window.MutationObserver( () => {
			if ( scheduled ) {
				return;
			}
			scheduled = window.setTimeout( () => {
				scheduled = 0;
				scan( doc );
			}, 200 );
		} );
		const start = () => {
			observer.observe( doc.documentElement, {
				childList: true,
				subtree: true,
				attributes: true,
				attributeFilter: [ 'style', 'src' ],
			} );
			scan( doc );
		};
		if ( 'loading' === doc.readyState ) {
			doc.addEventListener( 'DOMContentLoaded', start );
		} else {
			start();
		}
	}

	// watch this document (e.g. the iframe of the block editor).
	attach( document );

	// watch the documents of the classic editor (TinyMCE).
	let tinyMceWatched = false;
	const watchTinyMce = () => {
		if ( tinyMceWatched || ! window.tinymce || ! window.tinymce.on ) {
			return;
		}
		tinyMceWatched = true;
		window.tinymce.on( 'AddEditor', ( event ) => {
			event.editor.on( 'init', () => attach( event.editor.getDoc() ) );
		} );
		( window.tinymce.editors || [] ).forEach( ( editor ) => {
			if ( editor.initialized ) {
				attach( editor.getDoc() );
			}
		} );
	};
	watchTinyMce();
	window.addEventListener( 'load', watchTinyMce );
} )();
