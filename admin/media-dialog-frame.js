/**
 * Script of the page with the external sources in the media dialog (in its iframe or in a new window).
 *
 * Replaces the import dialog of the directory listing: the chosen file is imported directly with the saved
 * credentials of the external source and its ID is sent to the media dialog, which selects it.
 *
 * @package external-files-in-media-library
 */
( function () {
	'use strict';

	// bail if our configuration is not available.
	if ( 'undefined' === typeof efmlMediaDialogFrame ) {
		return;
	}

	const config = efmlMediaDialogFrame;

	// the media dialog: the page around the iframe or the page, which opened this window.
	const target = window.parent !== window ? window.parent : window.opener;
	const inWindow = window.parent === window;

	/**
	 * Send a message to the media dialog.
	 *
	 * @param {Object} data The data.
	 */
	function send( data ) {
		if ( ! target || ! config.target_origin ) {
			return;
		}
		target.postMessage( Object.assign( { source: 'efml-media-dialog' }, data ), config.target_origin );
	}

	/**
	 * Show a status.
	 *
	 * @param {string}        text   The text.
	 * @param {string}        type   The type: "info", "success" or "error".
	 * @param {Array<string>} errors The errors.
	 */
	function showStatus( text, type, errors ) {
		const status = document.getElementById( 'efml-media-dialog-status' );
		if ( ! status ) {
			return;
		}
		status.textContent = '';
		const notice = document.createElement( 'div' );
		notice.className = 'notice inline notice-' + type;
		const paragraph = document.createElement( 'p' );
		paragraph.textContent = text;
		notice.appendChild( paragraph );
		if ( errors && errors.length ) {
			const list = document.createElement( 'ul' );
			errors.forEach( ( error ) => {
				const item = document.createElement( 'li' );
				item.textContent = error;
				list.appendChild( item );
			} );
			notice.appendChild( list );
		}
		status.appendChild( notice );
		status.scrollIntoView( { block: 'nearest' } );
	}

	/**
	 * Import a file of the directory listing: replaces the import dialog, which the actions of the listing open.
	 *
	 * @param {Object} settings The settings of the action (urls, term, …).
	 */
	window.efml_get_import_dialog = function ( settings ) {
		settings = settings || {};

		// get the URL: never the credentials of the request, the server uses the saved external source.
		const urls = 'string' === typeof settings.urls ? settings.urls : '';
		if ( '' === urls ) {
			return;
		}

		showStatus( config.texts.importing, 'info', [] );

		const body = new FormData();
		body.append( 'action', 'efml_media_dialog_import' );
		body.append( 'nonce', config.nonce );
		body.append( 'urls', urls );
		body.append( 'source', settings.term ? String( config.source ) : '0' );

		fetch( config.ajax_url, { method: 'POST', credentials: 'same-origin', body } )
			.then( ( response ) => response.json() )
			.then( ( response ) => {
				// show the error.
				if ( ! response || ! response.success ) {
					showStatus( response && response.data && response.data.message ? response.data.message : config.texts.error, 'error', [] );
					return;
				}

				const ids = ( response.data.ids || [] ).map( Number ).filter( Boolean );
				const errors = response.data.errors || [];

				// send the files to the media dialog.
				if ( ids.length ) {
					send( { type: 'imported', ids } );
					showStatus( inWindow ? config.texts.imported_window : config.texts.imported, 'success', errors );
					return;
				}

				// show that they are imported in the background.
				if ( response.data.queued > 0 ) {
					showStatus( String( config.texts.queued ).replace( '%1$d', String( response.data.queued ) ), 'info', errors );
					return;
				}

				showStatus( config.texts.error, 'error', errors );
			} )
			.catch( () => showStatus( config.texts.error, 'error', [] ) );
	};

	// reload the list of external sources, when the user comes back after adding one in a new window.
	let added = false;
	document.addEventListener( 'click', ( event ) => {
		if ( event.target instanceof Element && event.target.closest( '.efml-card-add' ) ) {
			added = true;
		}
	} );
	document.addEventListener( 'visibilitychange', () => {
		if ( added && 'visible' === document.visibilityState ) {
			window.location.reload();
		}
	} );
	window.addEventListener( 'focus', () => {
		if ( added ) {
			window.location.reload();
		}
	} );

	// tell the media dialog, that this page has been loaded.
	send( { type: 'ready', url: window.location.href } );
} )();
