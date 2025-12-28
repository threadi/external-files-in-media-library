/**
 * This file handle the complete intro for this plugin with dialogs thanks to IntroJS.
 *
 * @source https://introjs.com
 */
jQuery(document).ready(function($) {
  /**
   * Part 1: start intro by click on an element with the class "efml-intro-start".
   */
  $('.efml-intro-start').on('click', function(e) {
    e.preventDefault();

    // create the first intro dialog.
    let intro = efml_intro_object( [
      {
        title: efmlIntroJsVars.step_1_title,
        intro: efmlIntroJsVars.step_1_intro,
      },
      {
        element: document.querySelector('#menu-media .wp-submenu .wp-first-item + li > a'),
        title: efmlIntroJsVars.step_2_title,
        intro: efmlIntroJsVars.step_2_intro,
      },
    ] );

    // open the media library submenu in admin menu left.
    intro.onbeforechange(function () {
      if (intro._currentStepSignal.rawVal === 0) {
        jQuery.ajax( {
          type: "POST",
          url: efmlIntroJsVars.ajax_url,
          data: {
            'action': 'efml_intro_started',
            'nonce': efmlIntroJsVars.intro_started_nonce
          },
          error: function( jqXHR, textStatus, errorThrown ) {
            efml_ajax_error_dialog( errorThrown )
          },
          success: function() {
            $('.etfw-transient, #etfw-transients-grouped').remove();
          }
        } )
      }
      if (intro._currentStepSignal.rawVal === 1) {
        $('#menu-media').addClass( 'opensub' );
      }
    });

    // forward user to the form to add new files in media library with intro marker.
    intro.oncomplete(function() {
      // prevent normal exist handler.
      intro.onexit( () => { return true } );
      // forward user.
      window.location.href = efmlIntroJsVars.url_1;
    });

    // start the intro.
    intro.start();
  });

  /**
   * Following parts are only run with marker in URL.
   */
  if (RegExp('efml-intro', 'gi').test(window.location.search)) {
    /**
     * Part 2: Show intro on page 2.
     */
    window.setTimeout( function () {

      // create the second intro dialog.
      let intro = efml_intro_object( [
          {
            element: document.querySelector( '.efml-import-dialog' ),
            title: efmlIntroJsVars.step_3_title,
            intro: efmlIntroJsVars.step_3_intro,
            position: 'top'
          },
        ]
      );

      // trigger click on the button to open the import dialog.
      intro.oncomplete(function(e) {
        // prevent normal exist handler.
        intro.onexit( () => { return true } );
        // trigger the click.
        $( '#plupload-upload-ui .efml-import-dialog' ).trigger( 'click' );
      });

      // start the intro.
      intro.start();
    }, 200 )

    /**
     * Part 3: Show how to add URL if import dialog is loaded.
     */
    document.addEventListener("efml-import-dialog-loaded", function() {
      window.setTimeout(function() {

        // create the third intro dialog.
        let intro = efml_intro_object( [
            {
              element: document.querySelector( '.efml-import-dialog .easy-dialog-for-wordpress-text:first-of-type' ),
              title: efmlIntroJsVars.step_4_title,
              intro: efmlIntroJsVars.step_4_intro,
              position: 'bottom'
            },
          ]
        )

        // add example URL in textarea with animation and forward then to part 4.
        intro.oncomplete(function() {
          // prevent normal exist handler.
          intro.onexit( () => { return true } );

          // get the field.
          let field = $(".eml_add_external_files");

          // get the example URL:
          let value = efmlIntroJsVars.url_2;

          // animate its insertion in the field.
          value.split("").forEach(function(elem, index){
            setTimeout(function(){
              field.val( field.val() + elem );

              // on last entry, start part 4 of the intro.
              if (index === value.split("").length - 1){
                efml_intro_part_4();
              }
            }, index*efmlIntroJsVars.delay);
            });
        });

        // start the intro.
        intro.start();
      }, 500)
    });

    /**
     * Part 5: Describe the resulting dialog and show additional hints.
     */
    document.addEventListener("efml-import-finished", function() {
      window.setTimeout(function() {
        let intro = efml_intro_object( [
            {
              element: document.querySelector( '.easy-dialog.efml' ),
              title: efmlIntroJsVars.step_6_title,
              intro: efmlIntroJsVars.step_6_intro,
            },
            {
              title: efmlIntroJsVars.step_7_title,
              intro: efmlIntroJsVars.step_7_intro,
            },
          ]
        );

        // set the done label.
        intro.setOption( 'doneLabel', efmlIntroJsVars.button_title_done );

        // forward user to the add same page without intro marker.
        intro.oncomplete(function() {
          // prevent normal exist handler.
          intro.onexit( () => { return true } );

          // forward user.
          window.location.href = efmlIntroJsVars.url_3;
        })

        // start the intro.
        intro.start();
      }, 500)
    });
  }
});

/**
 * Part 4: set privacy checkbox and click on submit.
 */
function efml_intro_part_4() {
  let settings = [
    {
      element: document.querySelector( '.efml-import-dialog .easy-dialog-for-wordpress-text:has(#privacy_hint)' ),
      title: efmlIntroJsVars.step_5a_title,
      intro: efmlIntroJsVars.step_5a_intro,
    },
    {
      element: document.querySelector( '.efml-import-dialog button:first-of-type' ),
      title: efmlIntroJsVars.step_5b_title,
      intro: efmlIntroJsVars.step_5b_intro,
      position: 'top'
    },
  ];
  if( document.querySelector( '.efml-import-dialog .easy-dialog-for-wordpress-text:has(#privacy_hint)' ) === null ) {
    settings = [
      {
        element: document.querySelector( '.efml-import-dialog button:first-of-type' ),
        title: efmlIntroJsVars.step_5b_title,
        intro: efmlIntroJsVars.step_5b_intro,
        position: 'top'
      },
    ];
  }

  // create the fourth intro dialog.
  let intro = efml_intro_object( settings );

  // open the media library submenu in admin menu left.
  intro.onbeforechange(function () {
    if (intro._currentStepSignal.rawVal === 1) {
      jQuery('#privacy_hint').prop('checked', true);
    }
  });

  // trigger click on submit button in the import dialog.
  intro.oncomplete(function() {
    // prevent normal exist handler.
    intro.onexit( () => { return true } );

    // trigger the click.
    jQuery( '.efml-import-dialog button:first-of-type' ).trigger( 'click' );
  });

  // start the intro.
  intro.start();
}

/**
 * The exit handler: sends AJAX-request to server to mark intro as "has been run".
 */
function efml_intro_exit_handler( forward ) {
  jQuery.ajax( {
    type: "POST",
    url: efmlIntroJsVars.ajax_url,
    data: {
      'action': 'efml_intro_closed',
      'nonce': efmlIntroJsVars.intro_closed_nonce
    },
    error: function( jqXHR, textStatus, errorThrown ) {
      efml_ajax_error_dialog( errorThrown )
    },
    success: function() {
      // forward user, if requested.
      if( forward ) {
        window.location.href = efmlIntroJsVars.url_3;
      }
    }
  } )
}

/**
 * Create the basic intro object.
 *
 * @param steps
 * @returns {*}
 */
function efml_intro_object( steps ) {
  let intro = introJs.tour().setOptions( {
    nextLabel: efmlIntroJsVars.button_title_next,
    prevLabel: efmlIntroJsVars.button_title_back,
    doneLabel: efmlIntroJsVars.button_title_next,
    exitOnEsc: false,
    exitOnOverlayClick: false,
    showProgress: false,
    showBullets: false,
    disableInteraction: true,
    steps: steps
  });

  // set skip handler.
  intro.onSkip( () => {
    efml_intro_exit_handler( false );
    intro.onexit( () => { return true } );
  } );

  // set exit handler.
  intro.onexit( () => efml_intro_exit_handler( true ) );

  return intro;
}
