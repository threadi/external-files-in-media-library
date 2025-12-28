jQuery(document).ready(function($) {
  /**
   * Save sync changes via toggle.
   */
  $('.synchronization .eml-switch-toggle input:not([readonly])').on("change", function() {
    // get the wrapping object.
    let wrapper_obj = $(this).parents('.eml-switch-toggle');

    // send request.
    jQuery.ajax( {
      url: efmlJsSyncVars.ajax_url,
      type: 'post',
      data: {
        term_id: $( this ).data( 'term-id' ),
        state: $( this ).val(),
        action: 'efml_change_sync_state',
        nonce: efmlJsSyncVars.sync_state_nonce
      },
      beforeSend: function() {
        wrapper_obj.find('input').attr('readonly', true );
      },
      error: function (jqXHR, textStatus, errorThrown) {
        efml_ajax_error_dialog( errorThrown )
      },
      success: function() {
        wrapper_obj.find('input').attr('readonly', false );
      }
    });
  });
});

/**
 * Start sync of entries from given directory.
 *
 * @param method
 * @param term_id
 */
function efml_sync_from_directory( method, term_id ) {
  // send request.
  jQuery.ajax({
    url: efmlJsSyncVars.ajax_url,
    type: 'post',
    data: {
      action: 'efml_sync_from_directory',
      method: method,
      term: term_id,
      nonce: efmlJsSyncVars.sync_nonce,
    },
    error: function( jqXHR, textStatus, errorThrown ) {
      efml_ajax_error_dialog( errorThrown )
    },
    beforeSend: function() {
      // show progress.
      let dialog_config = {
        detail: {
          className: 'eml',
          title: efmlJsSyncVars.title_sync_progress,
          progressbar: {
            active: true,
            progress: 0,
            id: 'progress',
            label_id: 'progress_status'
          },
        }
      }
      efml_create_dialog( dialog_config );

      // get info about progress.
      setTimeout(function() { efml_sync_get_info() }, efmlJsSyncVars.info_timeout);
    }
  });
}

/**
 * Get info about running sync of files.
 */
function efml_sync_get_info() {
  jQuery.ajax( {
    type: "POST",
    url: efmlJsSyncVars.ajax_url,
    data: {
      'action': 'efml_get_sync_info',
      'nonce': efmlJsSyncVars.get_info_sync_nonce
    },
    error: function( jqXHR, textStatus, errorThrown ) {
      efml_ajax_error_dialog( errorThrown )
    },
    success: function (data) {
      let count = parseInt( data[0] );
      let max = parseInt( data[1] );
      let running = parseInt( data[2] );
      let status = data[3];
      let dialog_config = data[4];

      // show progress.
      jQuery( '#progress' ).attr( 'value', (count / max) * 100 );
      jQuery( '#progress_status' ).html( status );

      /**
       * If import is still running, get next info in xy ms.
       * If import is not running and error occurred, show the error.
       * If import is not running and no error occurred, show ok-message.
       */
      if ( running >= 1 ) {
        setTimeout( function () {
          efml_sync_get_info()
        }, efmlJsSyncVars.info_timeout );
      }
      else {
        efml_create_dialog( dialog_config );
      }
    }
  });
}

/**
 * Update the synchronization config for single external directory.
 */
function efml_sync_save_config() {
  // bail if any required field is not checked.
  let required_fields = jQuery( '.efml-sync-config :input[required]:visible' );
  if( required_fields.length > 0 && ! required_fields.is(":checked") ) {
    required_fields.parent().addClass( 'error' )
    return;
  }
  required_fields.parent().removeClass( 'error' );

  // get fields from the form.
  let fields = {};
  jQuery('.efml-sync-config select, .efml-sync-config input[type="date"], .efml-sync-config input[type="email"]').each(function(){
    fields[jQuery(this).attr('id')] = jQuery(this).val();
  });
  jQuery('.efml-sync-config input[type="checkbox"]').each(function(){
    if( jQuery(this).is(':checked') ) {
      if( jQuery( this ).attr( 'name' ).indexOf('[') >= 0 ) {
        if (!fields[jQuery( this ).attr( 'name' ).replace( '[', '' ).replace( ']', '' )]) {
          fields[jQuery( this ).attr( 'name' ).replace( '[', '' ).replace( ']', '' )] = {};
        }
        fields[jQuery( this ).attr( 'name' ).replace( '[', '' ).replace( ']', '' )][jQuery( this ).val()] = 1;
      }
      else {
        fields[jQuery( this ).attr( 'name' )] = 1;
      }
    }
  });
  fields['term_id'] = jQuery('#term_id').val();

  // send request.
  jQuery.ajax({
    url: efmlJsSyncVars.ajax_url,
    type: 'post',
    data: {
      action: 'efml_sync_save_config',
      fields: fields,
      nonce: efmlJsSyncVars.sync_save_config_nonce,
    },
    error: function( jqXHR, textStatus, errorThrown ) {
      efml_ajax_error_dialog( errorThrown )
    },
    success: function (response) {
      efml_create_dialog( response );
    }
  });
}
