jQuery(document).ready(function($) {
  /**
   * Add AJAX-functionality to show config settings for export.
   */
  $( ".efml-export" ).on( 'click', function (e) {
    e.preventDefault();

    // send request
    $.ajax({
      url: efmlJsExportVars.ajax_url,
      type: 'post',
      data: {
        term_id: $(this).data('term-id'),
        action: 'efml_get_export_config_dialog',
        nonce: efmlJsExportVars.export_config_nonce
      },
      error: function( jqXHR, textStatus, errorThrown ) {
        efml_ajax_error_dialog( errorThrown )
      },
      success: function (response) {
        efml_create_dialog( response );
      }
    });
  });

  /**
   * Save export changes via toggle.
   */
  $('.export .eml-switch-toggle input:not([readonly])').on("change", function() {
    // send request.
    jQuery.ajax( {
      url: efmlJsExportVars.ajax_url,
      type: 'post',
      data: {
        term_id: $( this ).data( 'term-id' ),
        state: $( this ).val(),
        action: 'efml_change_export_state',
        nonce: efmlJsExportVars.export_state_nonce
      },
      error: function (jqXHR, textStatus, errorThrown) {
        efml_ajax_error_dialog( errorThrown )
      },
    });
  });
});

/**
 * Save the configuration for a single export target.
 */
function efml_export_save_config() {
  // get all form data.
  let formData = jQuery('.efml-export-config :input').serializeArray();

  // add data to process this request.
  formData.push({ 'name': 'action', 'value': 'efml_save_export_config'});
  formData.push({ 'name': 'nonce', 'value': efmlJsExportVars.save_export_config_nonce});

  // send request.
  jQuery.ajax({
    url: efmlJsVars.ajax_url,
    type: 'POST',
    data: formData,
    error: function( jqXHR, textStatus, errorThrown ) {
      efml_ajax_error_dialog( errorThrown )
    },
    success: function (response) {
      efml_create_dialog( response );
    }
  });
}
