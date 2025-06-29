jQuery(document).ready(function($) {
    /**
     * Add rating hint and add file action.
     */
    $('body.settings_page_eml_settings h1.wp-heading-inline, body.taxonomy-edlfw_archive h1.wp-heading-inline').each(function() {
      let review_button = document.createElement('a');
      review_button.className = 'review-hint-button page-title-action';
      review_button.href = efmlJsVars.review_url;
      review_button.innerHTML = efmlJsVars.title_rate_us;
      review_button.target = '_blank';
      this.after(review_button);

      let add_file_button = document.createElement('a');
      add_file_button.className = 'page-title-action';
      add_file_button.href = efmlJsVars.add_file_url;
      add_file_button.innerHTML = efmlJsVars.title_add_file;
      this.after(add_file_button);

      let add_directory_listing_button = document.createElement( 'a' );
      add_directory_listing_button.className = 'page-title-action';
      add_directory_listing_button.href = efmlJsVars.directory_listing_url;
      add_directory_listing_button.innerHTML = efmlJsVars.title_add_source;
      this.after( add_directory_listing_button );
    });

    /**
     * Add rating hint.
     */
    $('body.media_page_efml_local_directories h1.wp-heading-inline').each(function() {
      let review_button = document.createElement('a');
      review_button.className = 'review-hint-button page-title-action';
      review_button.href = efmlJsVars.review_url;
      review_button.innerHTML = efmlJsVars.title_rate_us;
      review_button.target = '_blank';
      this.after(review_button);
    });

    /**
     * Get and show the import dialog.
     */
    $('.efml-import-dialog').on( 'click', function(e) {
      e.preventDefault();
      efml_get_import_dialog();
    });

    /**
     * Add AJAX-functionality to recheck the availability of a single file.
     */
    $("#eml_recheck_availability").on('click', function(e) {
        e.preventDefault();

        // get the ID of the file
        let id = $("#post_ID").val();

        // send request
        $.ajax({
            url: efmlJsVars.ajax_url,
            type: 'post',
            data: {
                id: id,
                action: 'eml_check_availability',
                nonce: efmlJsVars.availability_nonce
            },
            error: function( jqXHR, textStatus, errorThrown ) {
              efml_ajax_error_dialog( errorThrown )
            },
            success: function (response) {
              let p = $("#eml_url_file_state");
              let dialog_config = {
                detail: {
                  className: 'eml',
                  title: efmlJsVars.title_availability_refreshed,
                  texts: [
                    '<p>' + efmlJsVars.text_not_available + '</p>'
                  ],
                  buttons: [
                    {
                      'action': 'closeDialog();',
                      'variant': 'primary',
                      'text': efmlJsVars.lbl_ok
                    },
                  ]
                }
              }
              if( response.state === 'error' ) {
                  p.html('<span class="dashicons dashicons-no-alt"></span> ' + response.message);
              }
              else {
                  p.html('<span class="dashicons dashicons-yes-alt"></span> ' + response.message);
                  dialog_config.detail.texts[0] = '<p>' + efmlJsVars.text_is_available + '</p>';
              }
              efml_create_dialog( dialog_config );
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
        $.ajax({
          url: efmlJsVars.ajax_url,
          type: 'post',
          data: {
            id: id,
            action: 'eml_switch_hosting',
            nonce: efmlJsVars.switch_hosting_nonce
          },
          error: function( jqXHR, textStatus, errorThrown ) {
            efml_ajax_error_dialog( errorThrown )
          },
          beforeSend: function() {
            // show progress.
            let dialog_config = {
              detail: {
                className: 'eml',
                title: efmlJsVars.title_hosting_change_wait,
                texts: [
                  '<p>' + efmlJsVars.text_hosting_change_wait + '</p>'
                ],
              }
            }
            efml_create_dialog( dialog_config );
          },
          success: function (response) {
            let dialog_config = {
              detail: {
                className: 'eml',
                title: efmlJsVars.title_hosting_changed,
                texts: [
                  '<p>' + efmlJsVars.text_hosting_has_been_changed + '</p>'
                ],
                buttons: [
                  {
                    'action': 'location.reload();',
                    'variant': 'primary',
                    'text': efmlJsVars.lbl_ok
                  },
                ]
              }
            }
            obj.html(response.message);
            efml_create_dialog( dialog_config );
          }
        });
    })

    /**
     * Save to hide transient-messages via AJAX-request.
     */
    $('div.eml-transient[data-dismissible] button.notice-dismiss').on('click',
        function (event) {
            event.preventDefault();
            let $this = $(this);
            let attr_value, option_name, dismissible_length, data;
            attr_value = $this.closest('div.eml-transient[data-dismissible]').attr('data-dismissible').split('-');

            // Remove the dismissible length from the attribute value and rejoin the array.
            dismissible_length = attr_value.pop();
            option_name = attr_value.join('-');
            data = {
                'action': 'dismiss_admin_notice',
                'option_name': option_name,
                'dismissible_length': dismissible_length,
                'nonce': efmlJsVars.dismiss_nonce
            };

            // run ajax request to save this setting
            $.post(efmlJsVars.ajax_url, data);
            $this.closest('div.eml-transient[data-dismissible]').hide('slow');
        }
    );

    /**
     * Add observer to check if easy dialog has been opened.
     *
     * If it is opened:
     * - Add event to enable/disable credential fields in import-dialog.
     * - Check if automatic processing has been set and run it.
     *
     * @type {MutationObserver}
     */
    let observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
          if (mutation.attributeName === "class") {
            if ($(mutation.target).hasClass('easy-dialog-for-wordpress')){
              /**
               * Add click event for credentials
               */
              $('.efml-import-dialog #use_credentials').off().on('click', function() {
                // get fields.
                let login = $('#login');
                let password = $('#password');
                if( $(this).is(':checked') ) {
                  login.removeAttr('readonly');
                  password.removeAttr('readonly');
                }
                else {
                  login.attr('readonly', 'readonly');
                  password.attr('readonly', 'readonly');
                }
              });

              /**
               * Trigger automatic submit if class "efml-import-dialog-process-now" is set.
               */
              if( $('.efml-import-dialog.efml-import-dialog-process-now').length === 1 ) {
                efml_process_import_dialog();
              }
            }
          }
        });
      });

    observer.observe(document.body, {
      attributes: true
    });

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

    /**
     * Save sync changes via toggle.
     */
    $('.eml-switch-toggle input').on("change", function() {
      // send request.
      jQuery.ajax( {
        url: efmlJsVars.ajax_url,
        type: 'post',
        data: {
          term_id: $( this ).data( 'term-id' ),
          state: $( this ).val(),
          action: 'efml_change_sync_state',
          nonce: efmlJsVars.sync_state_nonce
        },
        error: function (jqXHR, textStatus, errorThrown) {
          efml_ajax_error_dialog( errorThrown )
        },
      });
    });

    /**
     * Prevent editing of our own archive terms.
     */
    $('body.taxonomy-edlfw_archive #edittag input').each( function() {
        $(this).attr('readonly', true);
    });
});

/**
 * Get info about running import of URLs.
 */
function efml_upload_files_get_info() {
  jQuery.ajax( {
    type: "POST",
    url: efmlJsVars.ajax_url,
    data: {
      'action': 'eml_get_external_urls_import_info',
      'nonce': efmlJsVars.get_import_info_nonce
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
          efml_upload_files_get_info()
        }, efmlJsVars.info_timeout );
      }
      else {
        efml_create_dialog( dialog_config );
      }
    }
  } )
}

/**
 * Helper to create a new dialog with given config.
 *
 * @param config
 */
function efml_create_dialog( config ) {
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
function efml_ajax_error_dialog( errortext, texts ) {
  if( errortext === undefined || errortext.length === 0 ) {
    errortext = 'Request Timeout';
  }
  let message = '<p>' + efmlJsVars.txt_error + '</p>';
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
      title: efmlJsVars.title_error,
      texts: [
        message
      ],
      buttons: [
        {
          'action': 'location.reload();',
          'variant': 'primary',
          'text': efmlJsVars.lbl_ok
        }
      ]
    }
  }
  efml_create_dialog( dialog_config );
}

/**
 * Reset proxy via request.
 */
function efml_reset_proxy() {
  jQuery.ajax( {
    type: "POST",
    url: efmlJsVars.ajax_url,
    data: {
      'action': 'eml_reset_proxy',
      'nonce': efmlJsVars.reset_proxy_nonce
    },
    error: function( jqXHR, textStatus, errorThrown ) {
      efml_ajax_error_dialog( errorThrown )
    },
    success: function (dialog_config) {
      efml_create_dialog( dialog_config );
    }
  } )
}

/**
 * Start sync of entries from given directory.
 *
 * @param method
 * @param term_id
 */
function efml_sync_from_directory( method, term_id ) {
  // send request.
  jQuery.ajax({
    url: efmlJsVars.ajax_url,
    type: 'post',
    data: {
      action: 'efml_sync_from_directory',
      method: method,
      term_id: term_id,
      nonce: efmlJsVars.sync_nonce,
    },
    error: function( jqXHR, textStatus, errorThrown ) {
      efml_ajax_error_dialog( errorThrown )
    },
    beforeSend: function() {
      // show progress.
      let dialog_config = {
        detail: {
          className: 'eml',
          title: efmlJsVars.title_sync_progress,
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
      setTimeout(function() { efml_sync_get_info() }, efmlJsVars.info_timeout);
    }
  });
}

/**
 * Get info about running sync of files.
 */
function efml_sync_get_info() {
  jQuery.ajax( {
    type: "POST",
    url: efmlJsVars.ajax_url,
    data: {
      'action': 'efml_get_sync_info',
      'nonce': efmlJsVars.get_info_sync_nonce
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
        }, efmlJsVars.info_timeout );
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
  // get fields from the form.
  let fields = {};
  jQuery('.eml-sync-config select').each(function(){
    fields[jQuery(this).attr('id')] = jQuery(this).val();
  });
  jQuery('.eml-sync-config input[type="checkbox"]').each(function(){
    if( jQuery(this).is(':checked') ) {
      if( ! fields[jQuery( this ).attr( 'name' )] ) {
        fields[jQuery( this ).attr( 'name' )] = {};
      }
      fields[jQuery( this ).attr( 'name' )][jQuery( this ).val()] = 1;
    }
  });
  fields['term_id'] = jQuery('#term_id').val();

  // send request.
  jQuery.ajax({
    url: efmlJsVars.ajax_url,
    type: 'post',
    data: {
      action: 'efml_sync_save_config',
      fields: fields,
      nonce: efmlJsVars.sync_save_config_nonce,
    },
    error: function( jqXHR, textStatus, errorThrown ) {
      efml_ajax_error_dialog( errorThrown )
    },
    success: function (response) {
      let dialog_config = {
        detail: {
          className: 'eml',
          title: efmlJsVars.title_sync_config_saved,
          texts: [
            '<p>' + efmlJsVars.text_sync_config_saved + '</p>'
          ],
          buttons: [
            {
              'action': 'location.reload();',
              'variant': 'primary',
              'text': efmlJsVars.lbl_ok
            },
          ]
        }
      }
      efml_create_dialog( dialog_config );
    }
  });
}

/**
 * Save a directory as directory archive.
 *
 * @param type The used type.
 * @param url The URL of the directory.
 * @param login The login (optional).
 * @param password The password (optional).
 * @param api_key The API Key (optional).
 * @param term_id The used term (optional).
 */
function efml_save_as_directory( type, url, login, password, api_key, term_id ) {
  jQuery.ajax({
    url: efmlJsVars.ajax_url,
    type: 'POST',
    data: {
      action: 'efml_add_archive',
      type: type,
      url: url,
      login: login,
      password: password,
      api_key: api_key,
      term_id: term_id,
      nonce: efmlJsVars.add_archive_nonce,
    },
    error: function( jqXHR, textStatus, errorThrown ) {
      efml_ajax_error_dialog( errorThrown )
    },
    success: function ( dialog_config ) {
      efml_create_dialog( dialog_config );
    }
  });
}

/**
 * Get the import dialog via AJAX and show it.
 */
function efml_get_import_dialog( settings ) {
  if( typeof settings === "undefined" ) {
    settings = {};
    /*settings.no_textarea = true;
    settings.urls = 'https://pdfobject.com/pdf/sample.pdf';
    settings.no_services = true;
    settings.no_credentials = true;
    settings.no_dialog = true;*/
  }

  // send request to get the actual dialog.
  jQuery.ajax({
    url: efmlJsVars.ajax_url,
    type: 'POST',
    data: {
      action: 'efml_get_import_dialog',
      nonce: efmlJsVars.import_dialog_nonce,
      settings: settings
    },
    beforeSend: function() {
      // show loading dialog.
      let dialog_config = {
        detail: {
          className: 'is-loading',
          title: efmlJsVars.title_loading,
          texts: [
            '<p>' + efmlJsVars.text_loading + '</p>'
          ],
        }
      }
      efml_create_dialog( dialog_config );
    },
    error: function( jqXHR, textStatus, errorThrown ) {
      efml_ajax_error_dialog( errorThrown )
    },
    success: function (response) {
      if( response.detail ) {
        efml_create_dialog( response );
      }
    }
  });
}

/**
 * Start the process of a URL import from dialog.
 *
 * Send the complete form from the dialog via AJAX to process it.
 */
function efml_process_import_dialog() {
  // get all form data.
  let formData = jQuery('.efml-import-dialog :input').serializeArray();

  // add data to process this request.
  formData.push({ 'name': 'action', 'value': 'eml_add_external_urls'});
  formData.push({ 'name': 'nonce', 'value': efmlJsVars.urls_nonce});

  // send request.
  jQuery.ajax({
    url: efmlJsVars.ajax_url,
    type: 'POST',
    data: formData,
    error: function( jqXHR, textStatus, errorThrown ) {
      efml_ajax_error_dialog( errorThrown )
    },
    beforeSend: function() {
      // show progress.
      let dialog_config = {
        detail: {
          className: 'eml',
          title: efmlJsVars.title_import_progress,
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
      setTimeout(function() { efml_upload_files_get_info() }, efmlJsVars.info_timeout);
    }
  });
}
