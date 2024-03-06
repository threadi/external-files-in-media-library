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
                if( response.length > 0 ) {
                    let response_json = JSON.parse(response);
                    let p = $("#eml_url_file_state");
                    if( response_json.state === 'error' ) {
                        p.html('<span class="dashicons dashicons-no-alt"></span> ' + response_json.message);
                    }
                    else {
                        p.html('<span class="dashicons dashicons-yes-alt"></span> ' + response_json.message);
                    }
                }
            }
        });
    });

    // save to hide transient-messages via ajax-request
    $('div[data-dismissible] button.notice-dismiss').on('click',
        function (event) {
            event.preventDefault();
            var $this = $(this);
            var attr_value, option_name, dismissible_length, data;
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
