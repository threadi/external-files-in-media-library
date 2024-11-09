jQuery(document).ready(function($) {
    /**
     * Add rating hint.
     */
    $('body.settings_page_eml_settings h1.wp-heading-inline').each(function() {
      let button = document.createElement('a');
      button.className = 'review-hint-button page-title-action';
      button.href = emlJsVars.review_url;
      button.innerHTML = emlJsVars.title_rate_us;
      button.target = '_blank';
      this.after(button);
    })

    /**
     * Add AJAX-functionality to upload new URLs on Media > Add New
     */
    $('button.eml_add_external_upload').on('click', function(e) {
        e.preventDefault();

        // get field value.
        let urls = $(this).parent().find('.eml_add_external_files').val();

        // do nothing if list is empty.
        if( urls.length === 0 ) {
          return;
        }

        // get queue setting.
        let add_to_queue = $(this).parent().find('#add_to_queue').is(':checked') ? 1 : 0;

        // get the credentials (optional).
        let login = $(this).parent().find('#eml_login').val();
        let password = $(this).parent().find('#eml_password').val();

        // send request.
        jQuery.ajax({
            url: emlJsVars.ajax_url,
            type: 'post',
            data: {
                urls: urls,
                login: login,
                password: password,
                add_to_queue: add_to_queue,
                action: 'eml_add_external_urls',
                nonce: emlJsVars.urls_nonce
            },
            error: function( jqXHR, textStatus, errorThrown ) {
              eml_ajax_error_dialog( errorThrown )
            },
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
            error: function( jqXHR, textStatus, errorThrown ) {
              eml_ajax_error_dialog( errorThrown )
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
          error: function( jqXHR, textStatus, errorThrown ) {
            eml_ajax_error_dialog( errorThrown )
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

  /**
   * Copy strings in clipboard.
   */
  $(".settings_page_eml_settings .copy-text-attr").on("click", function( e ) {
    e.preventDefault();
    $(this).removeClass("copied");
    if( efml_copy_to_clipboard($(this).data( 'text' ).trim()) ) {
      $(this).addClass("copied");
    }
  });
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
            'action': 'edfw_open_dialog("add_eml_files");',
            'variant': 'primary',
            'text': emlJsVars.lbl_ok
          },
        ]
      }
    }
    eml_create_dialog( dialog_config );
    return;
  }

  // get the credentials (optional).
  let login = jQuery('#eml_login').val();
  let password = jQuery('#eml_password').val();

  // get queue setting.
  let add_to_queue = jQuery('#add_to_queue').is(':checked') ? 1 : 0;

  // send request.
  jQuery.ajax({
    url: emlJsVars.ajax_url,
    type: 'post',
    data: {
      urls: urls,
      login: login,
      password: password,
      add_to_queue: add_to_queue,
      action: 'eml_add_external_urls',
      nonce: emlJsVars.urls_nonce
    },
    error: function( jqXHR, textStatus, errorThrown ) {
      eml_ajax_error_dialog( errorThrown )
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
      setTimeout(function() { eml_upload_files_get_info() }, emlJsVars.info_timeout);
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
    error: function( jqXHR, textStatus, errorThrown ) {
      eml_ajax_error_dialog( errorThrown )
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
          eml_upload_files_get_info()
        }, emlJsVars.info_timeout );
      }
      else {
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
  document.body.dispatchEvent(new CustomEvent("easy-dialog-for-wordpress", config));
}

/**
 * Copy given text to clipboard.
 *
 * @param text The text to copy.
 */
function efml_copy_to_clipboard( text ) {
  let helper = document.createElement("textarea");
  document.body.appendChild(helper);
  helper.value = text.replace(/(<([^>]+)>)/gi, "");
  helper.select();
  if( document.execCommand("copy") ) {
    document.body.removeChild(helper);
    return true;
  }
  document.body.removeChild(helper);
  return false;
}

/**
 * Define dialog for AJAX-errors.
 */
function eml_ajax_error_dialog( errortext, texts ) {
  if( errortext === undefined || errortext.length === 0 ) {
    errortext = 'Request Timeout';
  }
  let message = '<p>' + emlJsVars.txt_error + '</p>';
  message = message + '<ul>';
  if( texts && texts[errortext] ) {
    message = message + '<li>' + texts[errortext] + '</li>';
  }
  else {
    message = message + '<li>' + errortext + '</li>';
  }
  message = message + '</ul>';

  // show dialog.
  let dialog_config = {
    detail: {
      title: emlJsVars.title_error,
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
