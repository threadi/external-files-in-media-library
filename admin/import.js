/**
 * Import given settings file via AJAX.
 */
function efml_import_settings_file() {
  let file = jQuery('#import_settings_file')[0].files[0];
  if( undefined === file ) {
    let dialog_config = {
      detail: {
        title: efmlImportJsVars.title_settings_import_file_missing,
        texts: [
          '<p>' + efmlImportJsVars.text_settings_import_file_missing + '</p>'
        ],
        buttons: [
          {
            'action': 'closeDialog();',
            'variant': 'primary',
            'text': efmlImportJsVars.lbl_ok
          }
        ]
      }
    }
    eml_create_dialog( dialog_config );
    return;
  }

  let request = new FormData();
  request.append( 'file', file);
  request.append( 'action', 'eml_settings_import_file' );
  request.append( 'nonce', efmlImportJsVars.settings_import_file_nonce );

  jQuery.ajax({
    url: efmlImportJsVars.ajax_url,
    type: "POST",
    data: request,
    contentType: false,
    processData: false,
    success: function( dialog_config ){
      eml_create_dialog( dialog_config );
    },
  });
}
