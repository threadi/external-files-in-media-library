jQuery(document).ready(function($) {
    /**
     * Add rating hint.
     */
    $('body.settings_page_eml_settings h1').each(function() {
      let button = document.createElement('a');
      button.className = 'review-hint-button page-title-action';
      button.href = 'https://wordpress.org/plugins/external-files-in-media-library/#reviews';
      button.innerHTML = emlJsVars.title_rate_us;
      button.target = '_blank';
      this.after(button);
    })

    /**
     * Add AJAX-functionality to upload new urls on Media > Add New
     */
    $('button.eml_add_external_upload').on('click', function(e) {
        e.preventDefault();

        // get button as object
        let button_obj = $(this);

        // get field value.
        let urls = $(this).parent().find('.eml_add_external_files').val();

        // do nothing if list is empty.
        if( urls.length === 0 ) {
          return;
        }

        // send request.
        jQuery.ajax({
            url: emlJsVars.ajax_url,
            type: 'post',
            data: {
                urls: urls,
                action: 'eml_add_external_urls',
                nonce: emlJsVars.urls_nonce
            },
            success: function (response) {
                if( response.length > 0 ) {
                    let response_json = JSON.parse(response);
                    // remove all other responses
                    $('.eml-response').remove();
                    // add the response
                    $('<div class="eml-response ' + ( response_json.state === 'error' ? 'error' : 'updated' ) + '">' + response_json.message +'</div>').insertAfter(button_obj);
                }
            }
        });
    });

    /**
     * Add AJAX-functionality to recheck the availability of a single file.
     */
    $("#eml_recheck_availability").on('click', function(e) {
        e.preventDefault();

        // get the ID of the file
        let id = $("#post_ID").val();

        // send request
        jQuery.ajax({
            url: emlJsVars.ajax_url,
            type: 'post',
            data: {
                id: id,
                action: 'eml_check_availability',
                nonce: emlJsVars.availability_nonce
            },
            success: function (response) {
              let p = $("#eml_url_file_state");
              let dialog_config = {
                detail: {
                  className: 'eml',
                  title: emlJsVars.title_availability_refreshed,
                  texts: [
                    '<p>' + emlJsVars.text_not_available + '</p>'
                  ],
                  buttons: [
                    {
                      'action': 'closeDialog();',
                      'variant': 'primary',
                      'text': emlJsVars.lbl_ok
                    },
                  ]
                }
              }
              if( response.state === 'error' ) {
                  p.html('<span class="dashicons dashicons-no-alt"></span> ' + response.message);
              }
              else {
                  p.html('<span class="dashicons dashicons-yes-alt"></span> ' + response.message);
                  dialog_config.detail.texts[0] = '<p>' + emlJsVars.text_is_available + '</p>';
              }
              eml_create_dialog( dialog_config );
            }
        });
    });

    /**
     * Switch the hosting of an external file.
     */
    $('.button.eml-change-host').on( 'click', function(e) {
        e.preventDefault();

        // secure object where the text should be changed.
        let obj = $(this).parent().find('span.eml-hosting-state');

        // get the ID of the file
        let id = $("#post_ID").val();

        // send request
        jQuery.ajax({
          url: emlJsVars.ajax_url,
          type: 'post',
          data: {
            id: id,
            action: 'eml_switch_hosting',
            nonce: emlJsVars.switch_hosting_nonce
          },
          success: function (response) {
            let dialog_config = {
              detail: {
                className: 'eml',
                title: emlJsVars.title_hosting_changed,
                texts: [
                  '<p>' + emlJsVars.text_hosting_has_been_changed + '</p>'
                ],
                buttons: [
                  {
                    'action': 'location.reload();',
                    'variant': 'primary',
                    'text': emlJsVars.lbl_ok
                  },
                ]
              }
            }
            obj.html(response.message);
            eml_create_dialog( dialog_config );
          }
        });
    })

    // save to hide transient-messages via ajax-request
    $('div[data-dismissible] button.notice-dismiss').on('click',
        function (event) {
            event.preventDefault();
            let $this = $(this);
            let attr_value, option_name, dismissible_length, data;
            attr_value = $this.closest('div[data-dismissible]').attr('data-dismissible').split('-');

            // Remove the dismissible length from the attribute value and rejoin the array.
            dismissible_length = attr_value.pop();
            option_name = attr_value.join('-');
            data = {
                'action': 'dismiss_admin_notice',
                'option_name': option_name,
                'dismissible_length': dismissible_length,
                'nonce': emlJsVars.dismiss_nonce
            };

            // run ajax request to save this setting
            $.post(emlJsVars.ajax_url, data);
            $this.closest('div[data-dismissible]').hide('slow');
        }
    );
});

/**
 * Handling for upload of URLs from textarea in dialog.
 */
function eml_upload_files() {
  let urls = jQuery( '#external_files' ).val();

  // do nothing if list is empty.
  if( urls.length === 0 ) {
    let dialog_config = {
      detail: {
        className: 'eml',
        title: emlJsVars.title_no_urls,
        texts: [
          '<p>' + emlJsVars.text_no_urls + '</p>'
        ],
        buttons: [
          {
            'action': 'location.reload();',
            'variant': 'primary',
            'text': emlJsVars.lbl_ok
          },
        ]
      }
    }
    eml_create_dialog( dialog_config );
    return;
  }

  // send request.
  jQuery.ajax({
    url: emlJsVars.ajax_url,
    type: 'post',
    data: {
      urls: urls,
      action: 'eml_add_external_urls',
      nonce: emlJsVars.urls_nonce
    },
    beforeSend: function() {
      // show progress.
      let dialog_config = {
        detail: {
          className: 'eml',
          title: emlJsVars.title_import_progress,
          progressbar: {
            active: true,
            progress: 0,
            id: 'progress',
            label_id: 'progress_status'
          },
        }
      }
      eml_create_dialog( dialog_config );

      // get info about progress.
      setTimeout(function() { eml_upload_files_get_info() }, 200);
    }
  });
}

/**
 * Get info about running import of URLs.
 */
function eml_upload_files_get_info() {
  jQuery.ajax( {
    type: "POST",
    url: emlJsVars.ajax_url,
    data: {
      'action': 'eml_get_external_urls_import_info',
      'nonce': emlJsVars.get_import_info_nonce
    },
    success: function (data) {
      let count = parseInt( data[0] );
      let max = parseInt( data[1] );
      let running = parseInt( data[2] );
      let status = data[3];
      let files = data[4];
      let errors = data[5];

      // show progress.
      jQuery( '#progress' ).attr( 'value', (count / max) * 100 );
      jQuery( '#progress_status' ).html( status );

      /**
       * If import is still running, get next info in 200ms.
       * If import is not running and error occurred, show the error.
       * If import is not running and no error occurred, show ok-message.
       */
      if ( running >= 1 ) {
        setTimeout( function () {
          eml_upload_files_get_info()
        }, 200 );
      }
      else {
        let message = '<p>' + emlJsVars.text_import_ended + '</p>';
        if( files.length > 0 ) {
          message = message + '<p><strong>' + emlJsVars.text_urls_imported + ':</strong></p><ul class="ok-list">';
          for (file of files) {
            message = message + '<li><a href="' + file.url + '" target="_blank">' + file.url + '</a> <a href="' + file.edit_link + '" target="_blank" class="dashicons dashicons-edit"></a></li>';
          }
          message = message + '</ul>';
        }
        if( errors.length > 0 ) {
          message = message + '<p><strong>' + emlJsVars.text_urls_errors + ':</strong></p><ul class="error-list">';
          for (error of errors) {
            message = message + '<li><a href="' + error.url + '" target="_blank">' + error.url + '</a><br>' + error.log + '</li>';
          }
          message = message + '</ul>';
        }
        let dialog_config = {
          detail: {
            className: 'eml',
            title: emlJsVars.title_import_ended,
            texts: [
              message
            ],
            buttons: [
              {
                'action': 'location.reload();',
                'variant': 'primary',
                'text': emlJsVars.lbl_ok
              }
            ]
          }
        }
        eml_create_dialog( dialog_config );
      }
    }
  } )
}

/**
 * Helper to create a new dialog with given config.
 *
 * @param config
 */
function eml_create_dialog( config ) {
  document.body.dispatchEvent(new CustomEvent("wp-easy-dialog", config));
}
