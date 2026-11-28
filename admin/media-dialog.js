/**
 * Add the functions of this plugin to the dialog of WordPress to choose or upload media files.
 *
 * - A mark on external files with the logo of their service, private and exported files with a sign.
 * - The tab "Insert from URL" to import external URLs.
 * - The tab "From external source" with the directory listing of the saved external sources of the user. It is
 *   loaded in an iframe on its first use. If the iframe could not be used (other origin, blocked by headers), the
 *   external sources are opened in a new window.
 *
 * Imported files are added to the selection of the dialog. Nothing is requested before the user uses a tab.
 *
 * @package external-files-in-media-library
 */
( function ( $ ) {
	'use strict';

	// bail if the media views or our configuration are not available.
	if ( 'undefined' === typeof wp || ! wp.media || ! wp.media.view || 'undefined' === typeof efmlMediaDialog ) {
		return;
	}

	const config = efmlMediaDialog;
	const media = wp.media;

	/**
	 * Return a text with a number.
	 *
	 * @param {string} text   The text with "%1$d".
	 * @param {number} number The number.
	 * @return {string} The text.
	 */
	function withNumber( text, number ) {
		return String( text ).replace( '%1$d', String( number ) );
	}

	/**
	 * Return whether the given state of a dialog shows a library, in which files could be selected.
	 *
	 * @param {Object} state The state.
	 * @return {boolean} True if files could be selected.
	 */
	function isLibraryState( state ) {
		return !! state && media.controller.Library && state instanceof media.controller.Library && !! state.get( 'selection' ) && !! state.get( 'library' );
	}

	/**
	 * Add the given files to the library and the selection of the given dialog.
	 *
	 * @param {Object}        frame    The dialog.
	 * @param {Array<number>} ids      The IDs of the files.
	 * @param {boolean}       showThem True to show the library afterward.
	 */
	function selectFiles( frame, ids, showThem ) {
		const state = frame.state();

		// bail if nothing could be selected.
		if ( ! ids.length || ! isLibraryState( state ) ) {
			return;
		}

		const library = state.get( 'library' );
		const selection = state.get( 'selection' );

		// load the files: a failed request does not stop the others.
		const attachments = ids.map( ( id ) => media.attachment( id ) );
		const requests = attachments.map( ( attachment ) =>
			$.Deferred( ( deferred ) => {
				attachment.fetch().always( () => deferred.resolve() );
			} ).promise()
		);

		$.when.apply( $, requests ).then( () => {
			// use only the files, which could be loaded.
			const loaded = attachments.filter( ( attachment ) => !! attachment.get( 'url' ) );
			if ( ! loaded.length ) {
				return;
			}

			// add them to the library, if they match its actual filters.
			loaded.forEach( ( attachment ) => {
				if ( library.validator( attachment ) && ! library.get( attachment.id ) ) {
					library.add( attachment, { at: 0 } );
				}
			} );

			// select them.
			if ( selection.multiple ) {
				selection.add( loaded );
			} else {
				selection.reset( loaded.slice( 0, 1 ) );
			}

			// show the library with the selected files.
			if ( showThem ) {
				frame.content.mode( 'browse' );
			}
		} );
	}

	/**
	 * Send the given URLs to import them.
	 *
	 * @param {string} urls   The URLs, one per line.
	 * @param {number} source The ID of the external source (0 for none).
	 * @return {Promise} The request.
	 */
	function importUrls( urls, source ) {
		const body = new FormData();
		body.append( 'action', 'efml_media_dialog_import' );
		body.append( 'nonce', config.nonce );
		body.append( 'urls', urls );
		body.append( 'source', String( source || 0 ) );
		return fetch( config.ajax_url, { method: 'POST', credentials: 'same-origin', body } ).then( ( response ) => response.json() );
	}

	/**
	 * Add the mark to the files in the dialog.
	 */
	if ( media.view.Attachment ) {
		const render = media.view.Attachment.prototype.render;
		media.view.Attachment.prototype.render = function () {
			render.apply( this, arguments );

			// get the mark of this file.
			const mark = this.model ? this.model.get( 'efml_mark' ) : '';
			const preview = this.$( '.attachment-preview' );

			// add it to the preview of the file: the logo of its service, private and exported files with a sign.
			if ( mark && preview.length && ! preview.find( '.efml-media-mark' ).length ) {
				const label = this.model.get( 'efml_mark_label' ) || '';
				const service = String( this.model.get( 'efml_mark_service' ) || '' ).replace( /[^A-Za-z0-9_-]/g, '' );
				$( '<span></span>' )
					.addClass( 'efml-media-mark efml-media-mark-' + String( mark ).replace( /[^a-z]/g, '' ) )
					.attr( { title: label, role: 'img', 'aria-label': label } )
					.append( $( '<span class="efml-service-icon" aria-hidden="true"></span>' ).addClass( service ? 'efml-' + service : '' ) )
					.appendTo( preview );
			}

			return this;
		};
	}

	/**
	 * The content of the tab "Insert from URL".
	 */
	const UrlView = media.View.extend( {
		className: 'efml-media-dialog efml-media-dialog-url',

		events: {
			'click .efml-media-dialog-import': 'import',
		},

		render() {
			const id = 'efml-media-dialog-urls-' + this.cid;
			this.$el.html(
				'<p><label for="' + id + '"><strong></strong></label></p>' +
				'<p><textarea id="' + id + '" rows="8" placeholder="https://example.com/file.pdf"></textarea></p>' +
				'<p class="description"></p>' +
				'<p><button type="button" class="button button-primary efml-media-dialog-import"></button></p>' +
				'<div class="efml-media-dialog-result" aria-live="polite"></div>'
			);
			this.$( 'label strong' ).text( config.texts.url_label );
			this.$( '.description' ).text( config.texts.url_description );
			this.$( '.efml-media-dialog-import' ).text( config.texts.url_button );
			return this;
		},

		/**
		 * Show the result.
		 *
		 * @param {string}        text   The text.
		 * @param {Array<string>} errors The errors.
		 * @param {boolean}       failed True if it failed.
		 */
		showResult( text, errors, failed ) {
			const result = this.$( '.efml-media-dialog-result' ).empty();
			const notice = $( '<div class="notice inline"><p></p></div>' ).addClass( failed ? 'notice-error' : 'notice-success' );
			notice.find( 'p' ).text( text );
			if ( errors && errors.length ) {
				const list = $( '<ul></ul>' );
				errors.forEach( ( error ) => list.append( $( '<li></li>' ).text( error ) ) );
				notice.append( list );
			}
			result.append( notice );
		},

		import() {
			const urls = String( this.$( 'textarea' ).val() || '' ).trim();
			const button = this.$( '.efml-media-dialog-import' );

			// bail if nothing has been entered or the import is running.
			if ( '' === urls || button.prop( 'disabled' ) ) {
				return;
			}

			button.prop( 'disabled', true );
			this.$( '.efml-media-dialog-result' ).text( config.texts.importing );

			importUrls( urls, 0 )
				.then( ( response ) => {
					button.prop( 'disabled', false );

					// show the error.
					if ( ! response || ! response.success ) {
						this.showResult( response && response.data && response.data.message ? response.data.message : config.texts.error, [], true );
						return;
					}

					// show the result.
					const ids = ( response.data.ids || [] ).map( Number ).filter( Boolean );
					let text = config.texts.nothing_imported;
					if ( ids.length ) {
						text = withNumber( 1 === ids.length ? config.texts.imported : config.texts.imported_plural, ids.length );
					}
					if ( response.data.queued > 0 ) {
						text += ' ' + withNumber( config.texts.queued, response.data.queued );
					}
					this.showResult( text, response.data.errors || [], 0 === ids.length && 0 === response.data.queued );

					// select the files and show them, if all have been added.
					if ( ids.length ) {
						this.$( 'textarea' ).val( '' );
						selectFiles( this.controller, ids, ! ( response.data.errors || [] ).length );
					}
				} )
				.catch( () => {
					button.prop( 'disabled', false );
					this.showResult( config.texts.error, [], true );
				} );
		},
	} );

	/**
	 * Use the messages of the page in the iframe or in the window of the given dialog.
	 *
	 * Registered once per dialog, so files imported in the window are also selected, while another tab is shown.
	 *
	 * @param {Object}       frame The dialog.
	 * @param {MessageEvent} event The message.
	 */
	function onMessage( frame, event ) {
		// bail if the message does not come from the administration of this website.
		if ( event.origin !== config.admin_origin ) {
			return;
		}

		// bail if it is not a message of our page.
		const data = event.data;
		if ( ! data || 'object' !== typeof data || 'efml-media-dialog' !== data.source ) {
			return;
		}

		// bail if it does not come from the iframe or the window of this dialog.
		const fromFrame = !! frame.efmlFrame && event.source === frame.efmlFrame.contentWindow;
		const fromWindow = !! frame.efmlWindow && event.source === frame.efmlWindow;
		if ( ! fromFrame && ! fromWindow ) {
			return;
		}

		// the page has been loaded.
		if ( 'ready' === data.type ) {
			// remember the page, if it is a page of our iframe on this website.
			if ( 'string' === typeof data.url && 0 === data.url.indexOf( config.admin_origin + config.frame_path + '?' ) ) {
				frame.efmlSourcesUrl = data.url;
			}
			frame.trigger( 'efml:ready' );
			return;
		}

		// files have been imported: select them (and show them, if only one file could be selected).
		if ( 'imported' === data.type && Array.isArray( data.ids ) ) {
			const state = frame.state();
			selectFiles( frame, data.ids.map( Number ).filter( Boolean ), isLibraryState( state ) && ! state.get( 'selection' ).multiple );
		}
	}

	/**
	 * The content of the tab "From external source".
	 */
	const SourcesView = media.View.extend( {
		className: 'efml-media-dialog efml-media-dialog-sources',

		events: {
			'click .efml-media-dialog-window': 'openWindow',
		},

		initialize() {
			const frame = this.controller;

			// listen to the messages once per dialog.
			if ( ! frame.efmlListening ) {
				frame.efmlListening = true;
				window.addEventListener( 'message', ( event ) => onMessage( frame, event ) );
			}

			// stop waiting for the page, if it has been loaded. Bound with this view as context: WordPress removes it
			// together with the view (wp.media.View.dispose()).
			frame.on( 'efml:ready', this.onReady, this );
		},

		/**
		 * Stop waiting for the page, as it has been loaded.
		 */
		onReady() {
			window.clearTimeout( this.timer );
		},

		/**
		 * Return the URL of the page to show, with the origin of this dialog.
		 *
		 * @return {string} The URL.
		 */
		getUrl() {
			// use the last shown page of this dialog (e.g. the chosen external source).
			let url = this.controller.efmlSourcesUrl || config.frame_url;

			// add the origin of this dialog.
			url = url.replace( /([?&])origin=[^&]*&?/, '$1' ).replace( /[?&]$/, '' );
			return url + ( url.indexOf( '?' ) > -1 ? '&' : '?' ) + 'origin=' + encodeURIComponent( window.location.origin );
		},

		render() {
			// use a new window, if this page has another origin than the administration: the iframe could not be used.
			if ( window.location.origin !== config.admin_origin ) {
				this.showFallback( false );
				return this;
			}

			// show the page in the iframe.
			this.$el.empty();
			// remember the element: its window exists only after the view has been added to the page.
			this.controller.efmlFrame = $( '<iframe></iframe>' ).attr( { src: this.getUrl(), title: config.texts.frame_title } ).appendTo( this.$el )[ 0 ];

			// use a new window, if the page does not answer (e.g. as headers prevent it in an iframe).
			this.timer = window.setTimeout( () => this.showFallback( true ), config.timeout );

			return this;
		},

		/**
		 * Show the button to open the external sources in a new window.
		 *
		 * @param {boolean} blocked True if the iframe has been blocked.
		 */
		showFallback( blocked ) {
			this.controller.efmlFrame = null;
			this.$el.html( '<div class="efml-media-dialog-fallback"><p class="efml-media-dialog-fallback-hint"></p><p><button type="button" class="button button-primary efml-media-dialog-window"></button></p></div>' );
			this.$( '.efml-media-dialog-fallback-hint' ).text( config.texts.window_hint + ( blocked ? ' ' + config.texts.window_hint_block : '' ) );
			this.$( '.efml-media-dialog-window' ).text( config.texts.window_button );
		},

		/**
		 * Open the external sources in a new window.
		 */
		openWindow() {
			const popup = window.open( this.getUrl(), 'efml_media_dialog', 'width=1100,height=760,resizable=yes,scrollbars=yes' );
			if ( popup ) {
				this.controller.efmlWindow = popup;
				popup.focus();
			}
		},

		remove() {
			window.clearTimeout( this.timer );
			this.controller.efmlFrame = null;
			return media.View.prototype.remove.apply( this, arguments );
		},
	} );

	/**
	 * Add the tabs to the dialogs, which choose files from the library.
	 */
	const Select = media.view.MediaFrame && media.view.MediaFrame.Select;
	if ( Select ) {
		const browseRouter = Select.prototype.browseRouter;
		Select.prototype.browseRouter = function ( routerView ) {
			browseRouter.apply( this, arguments );

			// bail if the files of this state could not be selected.
			if ( ! isLibraryState( this.state() ) ) {
				return;
			}

			routerView.set( {
				efml_url: {
					text: config.texts.tab_url,
					priority: 60,
				},
				efml_sources: {
					text: config.texts.tab_sources,
					priority: 70,
				},
			} );
		};

		const bindHandlers = Select.prototype.bindHandlers;
		Select.prototype.bindHandlers = function () {
			bindHandlers.apply( this, arguments );

			this.on(
				'content:create:efml_url',
				function ( contentRegion ) {
					this.$el.removeClass( 'hide-toolbar' );
					contentRegion.view = new UrlView( { controller: this } );
				},
				this
			);

			this.on(
				'content:create:efml_sources',
				function ( contentRegion ) {
					this.$el.removeClass( 'hide-toolbar' );
					contentRegion.view = new SourcesView( { controller: this } );
				},
				this
			);
		};
	}
} )( jQuery );
