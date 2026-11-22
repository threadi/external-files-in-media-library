/**
 * Script for the central media library of a multisite network.
 *
 * - Asks before a file is deleted in all websites of the network and names where it is used.
 * - Loads the usage of a file in the network on request.
 * - Shows the websites in the setting of the availability only if they are needed.
 */
( function( $ ) {
  'use strict';

  // bail if the variables are missing.
  if ( typeof efmlNetworkVars === 'undefined' ) {
    return;
  }

  /**
   * Return a promise with the usage of the given attachments.
   *
   * @param ids The attachment IDs.
   * @returns {Promise<Object|null>}
   */
  function efml_network_get_usage( ids ) {
    return new Promise( function( resolve ) {
      $.ajax( {
        url: efmlNetworkVars.ajax_url,
        type: 'post',
        data: {
          action: 'efml_network_usage',
          nonce: efmlNetworkVars.nonce,
          attachment_ids: ids
        }
      } ).done( function( response ) {
        resolve( response && response.success ? response.data : null );
      } ).fail( function() {
        resolve( null );
      } );
    } );
  }

  /**
   * Ask the user to confirm the deletion of the given attachments in the whole network.
   *
   * @param ids The attachment IDs.
   * @returns {Promise<boolean>}
   */
  function efml_network_confirm_deletion( ids ) {
    return efml_network_get_usage( ids ).then( function( data ) {
      return window.confirm( data && data.message ? data.message : efmlNetworkVars.confirm );
    } );
  }

  /**
   * Ask before the deletion via links (list of the media library, edit page of a file).
   *
   * Runs in the capture phase, before the confirmation of WordPress in the "onclick" attribute of the link.
   */
  document.addEventListener( 'click', function( event ) {
    let link = event.target.closest ? event.target.closest( 'a[data-efml-network-delete]' ) : null;
    if ( ! link ) {
      return;
    }

    // stop the deletion and the confirmation of WordPress.
    event.preventDefault();
    event.stopPropagation();

    // ask and delete, if confirmed.
    efml_network_confirm_deletion( [ link.getAttribute( 'data-efml-network-delete' ) ] ).then( function( confirmed ) {
      if ( confirmed ) {
        window.location.href = link.href;
      }
    } );
  }, true );

  /**
   * Ask before the deletion of multiple files via bulk action in the list of the media library.
   */
  document.addEventListener( 'submit', function( event ) {
    let form = event.target;
    if ( ! form || 'posts-filter' !== form.id || form.efmlNetworkConfirmed ) {
      return;
    }

    // bail if this is not the deletion.
    let action = form.querySelector( 'select[name="action"]' );
    let action2 = form.querySelector( 'select[name="action2"]' );
    if ( ! ( action && 'delete' === action.value ) && ! ( action2 && 'delete' === action2.value ) ) {
      return;
    }

    // get the selected files, which are deleted in the whole network.
    let ids = [];
    form.querySelectorAll( 'input[name="media[]"]:checked' ).forEach( function( checkbox ) {
      let row = checkbox.closest( 'tr' );
      if ( row && row.querySelector( 'a[data-efml-network-delete]' ) ) {
        ids.push( checkbox.value );
      }
    } );

    // bail if there are none.
    if ( 0 === ids.length ) {
      return;
    }

    // stop the deletion.
    event.preventDefault();
    event.stopPropagation();

    // ask and delete, if confirmed.
    efml_network_confirm_deletion( ids ).then( function( confirmed ) {
      if ( confirmed ) {
        form.efmlNetworkConfirmed = true;
        form.submit();
      }
    } );
  }, true );

  $( function() {
    // mark the deletion on the edit page of a file.
    if ( efmlNetworkVars.attachment_id > 0 ) {
      $( '#delete-action a.submitdelete' ).attr( 'data-efml-network-delete', efmlNetworkVars.attachment_id );
    }

    // load the usage of a file on request.
    $( document ).on( 'click', '.efml-network-usage-load', function( event ) {
      event.preventDefault();
      let container = $( this ).closest( '.efml-network-usage' );
      let result = container.find( '.efml-network-usage-result' );
      result.text( efmlNetworkVars.loading );
      efml_network_get_usage( [ container.data( 'id' ) ] ).then( function( data ) {
        if ( data ) {
          result.html( data.html );
        } else {
          result.text( efmlNetworkVars.error );
        }
      } );
    } );

    // save the availability of a file.
    $( document ).on( 'click', '.efml-network-visibility-save', function( event ) {
      event.preventDefault();
      let container = $( this ).closest( '.efml-network-visibility' );
      let result = container.find( '.efml-network-visibility-result' );
      let sites = [];
      container.find( '.efml-network-visibility-site:checked:enabled' ).each( function() {
        sites.push( $( this ).val() );
      } );
      result.text( efmlNetworkVars.loading );
      $.ajax( {
        url: efmlNetworkVars.ajax_url,
        type: 'post',
        data: {
          action: 'efml_network_save_visibility',
          nonce: efmlNetworkVars.nonce,
          attachment_id: container.data( 'id' ),
          mode: container.find( '.efml-network-visibility-mode' ).val(),
          sites: sites
        }
      } ).done( function( response ) {
        result.text( response && response.success ? efmlNetworkVars.saved : efmlNetworkVars.error_save );
      } ).fail( function() {
        result.text( efmlNetworkVars.error_save );
      } );
    } );

    // show the websites in the setting of the availability only if they are needed.
    $( document ).on( 'change', '.efml-network-visibility-mode', function() {
      $( this ).closest( '.efml-network-visibility' ).find( '.efml-network-visibility-sites' ).toggle( 'all' !== $( this ).val() );
    } );

    // bail if the media dialog is not used.
    if ( typeof wp === 'undefined' || ! wp.media || ! wp.media.view ) {
      return;
    }

    // ask before the deletion of a single file in the media dialog and in the grid of the media library.
    if ( wp.media.view.Attachment && wp.media.view.Attachment.Details ) {
      let details = wp.media.view.Attachment.Details.prototype;
      let delete_attachment = details.deleteAttachment;
      details.deleteAttachment = function( event ) {
        // use the deletion of WordPress for other files.
        if ( ! this.model.get( 'efmlNetworkDelete' ) ) {
          return delete_attachment.apply( this, arguments );
        }

        // stop the deletion.
        event.preventDefault();

        // ask and delete, if confirmed.
        let model = this.model;
        efml_network_confirm_deletion( [ model.get( 'id' ) ] ).then( function( confirmed ) {
          if ( confirmed ) {
            model.destroy( {
              wait: true,
              error: function() {
                window.alert( efmlNetworkVars.error_delete );
              }
            } );
          }
        } );
      };
    }

    // ask before the deletion of multiple files in the grid of the media library.
    [ 'DeleteSelectedButton', 'DeleteSelectedPermanentlyButton' ].forEach( function( name ) {
      let button = wp.media.view[ name ];
      if ( ! button ) {
        return;
      }
      let click = button.prototype.click;

      // bail if already changed (e.g. inherited from the other button).
      if ( click.efmlNetwork ) {
        return;
      }
      button.prototype.click = function() {
        // get the selected files, which are deleted in the whole network.
        let selection = this.controller && this.controller.state() ? this.controller.state().get( 'selection' ) : null;
        let ids = selection ? selection.filter( function( model ) { return model.get( 'efmlNetworkDelete' ); } ).map( function( model ) { return model.get( 'id' ); } ) : [];

        // use the deletion of WordPress, if there are none or if the user has already confirmed.
        if ( 0 === ids.length || this.efmlNetworkConfirmed ) {
          this.efmlNetworkConfirmed = false;
          return click.apply( this, arguments );
        }

        // ask and delete, if confirmed.
        let self = this;
        let args = arguments;
        efml_network_confirm_deletion( ids ).then( function( confirmed ) {
          if ( confirmed ) {
            self.efmlNetworkConfirmed = true;
            click.apply( self, args );
          }
        } );
      };
      button.prototype.click.efmlNetwork = true;
    } );
  } );
} )( jQuery );
