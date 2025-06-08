/**
 * Save the access token for DropBox.
 */
function efml_dropbox_connect() {
  // get the access token from form.
  let access_token = jQuery("#efml_dropbox_access_token").val();

  // send request
  jQuery.ajax({
    url: efmlJsVarsDropBox.ajax_url,
    type: 'post',
    data: {
      access_token: access_token,
      action: 'efml_add_access_token',
      nonce: efmlJsVarsDropBox.access_token_connect_nonce
    },
    error: function( jqXHR, textStatus, errorThrown ) {
      efml_ajax_error_dialog( errorThrown )
    },
    success: function( response ) {
      efml_create_dialog( response.data );
    }
  });
}

/**
 * Remove the access token for DropBox.
 */
function efml_dropbox_disconnect() {
  // send request
  jQuery.ajax({
    url: efmlJsVarsDropBox.ajax_url,
    type: 'post',
    data: {
      action: 'efml_remove_access_token',
      nonce: efmlJsVarsDropBox.access_token_disconnect_nonce
    },
    error: function( jqXHR, textStatus, errorThrown ) {
      efml_ajax_error_dialog( errorThrown )
    },
    success: function( response ) {
      efml_create_dialog( response.data );
    }
  });
}
