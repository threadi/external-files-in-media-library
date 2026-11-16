/**
 * Add the filter for external files to the grid view of the media library.
 *
 * The same options as in the list view: all files, only external URLs, no external URLs, the state of the export and
 * the external sources. The chosen value is sent with the query of the grid view and used by the plugin.
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
	 * The select field with the options of the filter.
	 */
	const EfmlMediaFilter = wp.media.view.AttachmentFilters.extend( {
		id: 'media-attachment-efml-filter',

		/**
		 * Create the filters from our options: "none" removes the filter.
		 */
		createFilters: function () {
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
	} );

	/**
	 * Extend the browser of the attachments with our filter.
	 */
	const AttachmentsBrowser = wp.media.view.AttachmentsBrowser;
	wp.media.view.AttachmentsBrowser = AttachmentsBrowser.extend( {
		createToolbar: function () {
			// create the toolbar of WordPress.
			AttachmentsBrowser.prototype.createToolbar.apply( this, arguments );

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
