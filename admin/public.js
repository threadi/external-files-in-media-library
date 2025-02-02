jQuery(document).ready(function($) {
// save to hide transient-messages via ajax-request
  $( 'div.eml-transient[data-dismissible] button.notice-dismiss' ).on( 'click',
    function (event) {
      event.preventDefault();
      let $this = $( this );
      let attr_value, option_name, dismissible_length, data;
      attr_value = $this.closest( 'div.eml-transient[data-dismissible]' ).attr( 'data-dismissible' ).split( '-' );

      // Remove the dismissible length from the attribute value and rejoin the array.
      dismissible_length = attr_value.pop();
      option_name = attr_value.join( '-' );
      data = {
        'action': 'dismiss_admin_notice',
        'option_name': option_name,
        'dismissible_length': dismissible_length,
        'nonce': efmlJsVars.dismiss_nonce
      };

      // run ajax request to save this setting
      $.post( efmlJsVars.ajax_url, data );
      $this.closest( 'div.eml-transient[data-dismissible]' ).hide( 'slow' );
    }
  );
});
