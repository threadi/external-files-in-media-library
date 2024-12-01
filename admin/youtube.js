function efml_add_youtube_videos() {
  // send request.
  jQuery.ajax({
    url: efmlYoutubeJsVars.ajax_url,
    type: 'post',
    data: {
      channel_id: jQuery("#youtube_channel_id").val(),
      action: 'eml_youtube_add_channel',
      nonce: efmlYoutubeJsVars.add_channel_nonce
    },
    error: function( jqXHR, textStatus, errorThrown ) {
      efml_ajax_error_dialog( errorThrown )
    },
    success: function (dialog_config) {
        efml_create_dialog( dialog_config );
    }
  });
}
