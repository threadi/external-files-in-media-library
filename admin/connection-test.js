/**
 * Run the complete connection test of a service and show its results.
 *
 * @param {string} type    The name of the service.
 * @param {object} fields  The fields of the connection.
 * @param {number} term_id The ID of the used external source.
 * @param {string} directory The actual directory.
 */
function efml_connection_test( type, fields, term_id, directory ) {
  // show a dialog while the test is running.
  efml_create_dialog( {
    detail: {
      className: 'efml',
      title: efmlConnectionTestVars.title,
      texts: [ '<p>' + efmlConnectionTestVars.text + '</p>' ],
      buttons: []
    }
  } );

  // run the test.
  jQuery.ajax( {
    url: efmlConnectionTestVars.ajax_url,
    type: 'POST',
    data: {
      action: 'efml_connection_test',
      type: type,
      fields: fields,
      term_id: term_id,
      directory: directory,
      nonce: efmlConnectionTestVars.nonce,
    },
    error: function( jqXHR, textStatus, errorThrown ) {
      efml_ajax_error_dialog( errorThrown );
    },
    success: function( dialog_config ) {
      efml_create_dialog( dialog_config );
    }
  } );
}
