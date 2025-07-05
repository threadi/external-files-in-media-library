jQuery(document).ready(function($) {
  /**
   * Add AJAX-functionality to recheck the availability of a single file.
   */
  $("#eml_recheck_availability").on('click', function(e) {
    e.preventDefault();

    // get the ID of the file
    let id = $("#post_ID").val();

    // send request
    $.ajax({
      url: efmlJsAvailabilityVars.ajax_url,
      type: 'post',
      data: {
        id: id,
        action: 'eml_check_availability',
        nonce: efmlJsAvailabilityVars.availability_nonce
      },
      error: function( jqXHR, textStatus, errorThrown ) {
        efml_ajax_error_dialog( errorThrown )
      },
      success: function (response) {
        let p = $("#eml_url_file_state");
        let dialog_config = {
          detail: {
            className: 'eml',
            title: efmlJsAvailabilityVars.title_availability_refreshed,
            texts: [
              '<p>' + efmlJsAvailabilityVars.text_not_available + '</p>'
            ],
            buttons: [
              {
                'action': 'closeDialog();',
                'variant': 'primary',
                'text': efmlJsAvailabilityVars.lbl_ok
              },
            ]
          }
        }
        if( response.state === 'error' ) {
          p.html('<span class="dashicons dashicons-no-alt"></span> ' + response.message);
        }
        else {
          p.html('<span class="dashicons dashicons-yes-alt"></span> ' + response.message);
          dialog_config.detail.texts[0] = '<p>' + efmlJsAvailabilityVars.text_is_available + '</p>';
        }
        efml_create_dialog( dialog_config );
      }
    });
  });
});
