/**
 * Add the filter for external files to the grid view of the media library and to the media dialog.
 *
 * The same options as in the list view: all files, only external URLs, no external URLs, the state of the export and
 * the external sources. The chosen value is sent with the query of the grid view and used by the plugin.
 *
 * In the media dialog only the options, which need no query, are given with the page. The others are loaded once,
 * when the user points to the filter or focuses it, so opening the dialog does not need any further request.
 *
 * @package external-files-in-media-library
 */
( function () {
	'use strict';

	// bail if the media views or our options are not available.
	if ( 'undefined' === typeof wp || ! wp.media || ! wp.media.view || ! wp.media.view.AttachmentsBrowser || 'undefined' === typeof efmlMediaFilter ) {
		return;
	}

	/**
	 * The request for the further options, shared by all filters on this page.
	 *
	 * @type {Promise|null}
	 */
	let loading = null;

	/**
	 * Load the further options once.
	 *
	 * @return {Promise} Resolves with true if the options have been changed.
	 */
	function loadOptions() {
		// bail if nothing must be loaded.
		if ( ! efmlMediaFilter.ajax_url ) {
			return Promise.resolve( false );
		}

		// start the request once.
		if ( null === loading ) {
			const body = new FormData();
			body.append( 'action', 'efml_media_dialog_filter_options' );
			body.append( 'nonce', efmlMediaFilter.nonce );
			loading = fetch( efmlMediaFilter.ajax_url, { method: 'POST', credentials: 'same-origin', body } )
				.then( ( response ) => response.json() )
				.then( ( response ) => {
					if ( response && response.success && Array.isArray( response.data.options ) && response.data.options.length > 0 ) {
						efmlMediaFilter.options = response.data.options;
						return true;
					}
					return false;
				} )
				.catch( () => {
					// try it again on the next use.
					loading = null;
					return false;
				} );
		}

		// return the request.
		return loading;
	}

	/**
	 * The select field with the options of the filter.
	 */
	const EfmlMediaFilter = wp.media.view.AttachmentFilters.extend( {
		id: 'media-attachment-efml-filter',

		events: {
			change: 'change',
			mouseenter: 'loadOptions',
			focus: 'loadOptions',
		},

		/**
		 * Create the filters from our options: "none" removes the filter.
		 */
		createFilters() {
			const filters = {};
			efmlMediaFilter.options.forEach( function ( option, index ) {
				filters[ option.value ] = {
					text: option.label,
					props: {
						admin_filter_media_external_files: 'none' === option.value ? null : option.value,
					},
					priority: ( index + 1 ) * 10,
				};
			} );
			this.filters = filters;
		},

		/**
		 * Load the further options on the first use and show them.
		 */
		loadOptions() {
			loadOptions().then( ( changed ) => {
				if ( changed ) {
					this.refresh();
				}
			} );
		},

		/**
		 * Build the options of the field again, like WordPress does it during the initialization.
		 */
		refresh() {
			// bail if the field has been removed meanwhile.
			if ( ! this.el.isConnected ) {
				return;
			}

			this.createFilters();
			this.$el.html(
				_.chain( this.filters )
					.map( function ( filter, value ) {
						return {
							el: jQuery( '<option></option>' ).val( value ).html( filter.text )[ 0 ],
							priority: filter.priority || 50,
						};
					} )
					.sortBy( 'priority' )
					.pluck( 'el' )
					.value()
			);
			this.select();
		},
	} );

	/**
	 * Extend the browser of the attachments with our filter.
	 */
	const AttachmentsBrowser = wp.media.view.AttachmentsBrowser;
	wp.media.view.AttachmentsBrowser = AttachmentsBrowser.extend( {
		createToolbar() {
			// create the toolbar of WordPress.
			AttachmentsBrowser.prototype.createToolbar.apply( this, arguments );

			// bail if the toolbar has no filters (e.g. in dialogs which only show the selected files).
			if ( ! this.toolbar ) {
				return;
			}

			// add our filter with its label for screen readers beside the other filters.
			this.toolbar.set(
				'efmlMediaFilterLabel',
				new wp.media.view.Label( {
					value: efmlMediaFilter.label,
					attributes: {
						for: 'media-attachment-efml-filter',
					},
					priority: -74,
				} ).render()
			);
			this.toolbar.set(
				'efmlMediaFilter',
				new EfmlMediaFilter( {
					controller: this.controller,
					model: this.collection.props,
					priority: -74,
				} ).render()
			);
		},
	} );
} )();
