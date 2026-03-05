/**
 * Save the access token for DropBox.
 */
function efml_dropbox_connect() {
  // get the credentials from the form.
  let api_key = jQuery("#efml_dropbox_api_key").val();
  let api_secret = jQuery("#efml_dropbox_api_secret").val();

  // send request
  jQuery.ajax({
    url: efmlJsVarsDropBox.ajax_url,
    type: 'post',
    data: {
      api_key: api_key,
      api_secret: api_secret,
      action: 'efml_dropbox_setup_connection',
      nonce: efmlJsVarsDropBox.dropbox_connect_nonce
    },
    error: function( jqXHR, textStatus, errorThrown ) {
      efml_ajax_error_dialog( errorThrown )
    },
    success: function( response ) {
      if( response.data.url ) {
        location.href = response.data.url;
        return;
      }
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
