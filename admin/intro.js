/**
 * This file handles the complete intro for this plugin with dialogs thanks to Driver.js.
 *
 * @source https://driverjs.com
 */
jQuery(document).ready(function($) {
  /**
   * Part 1: start intro by click on an element with the class "efml-intro-start".
   *
   * Bound to the document, as the button could be added later (e.g., by the DataView of the settings).
   */
  $(document).on('click', '.efml-intro-start', function(e) {
    e.preventDefault();

    // mark the intro as started: this removes the welcome hint.
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
    } );

    // create and start the first intro dialog.
    efml_intro_object( [
      {
        title: efmlIntroJsVars.step_1_title,
        intro: efmlIntroJsVars.step_1_intro,
      },
      {
        // a function, as this element is only visible after the submenu has been opened.
        element: () => document.querySelector('#menu-media .wp-submenu .wp-first-item + li > a'),
        title: efmlIntroJsVars.step_2_title,
        intro: efmlIntroJsVars.step_2_intro,
      },
    ], {
      // open the media library submenu in admin menu left before its entry is shown.
      onNext: function( index ) {
        if ( index === 0 ) {
          $('#menu-media').addClass( 'opensub' );
        }
      },
      // forward user to the form to add new files in media library with intro marker.
      onComplete: function() {
        window.location.href = efmlIntroJsVars.url_1;
      }
    } );
  });

  /**
   * Following parts are only run with marker in URL.
   */
  if (RegExp('efml-intro', 'gi').test(window.location.search)) {
    /**
     * Part 2: Show intro on page 2.
     */
    window.setTimeout( function () {
      // create and start the second intro dialog.
      efml_intro_object( [
          {
            element: document.querySelector( '.efml-import-dialog' ),
            title: efmlIntroJsVars.step_3_title,
            intro: efmlIntroJsVars.step_3_intro,
            position: 'top'
          },
        ], {
          // trigger click on the button to open the import dialog.
          onComplete: function() {
            $( '#plupload-upload-ui .efml-import-dialog' ).trigger( 'click' );
          }
        }
      );
    }, 200 )

    /**
     * Part 3: Show how to add URL if import dialog is loaded.
     */
    document.addEventListener("efml-import-dialog-loaded", function() {
      window.setTimeout(function() {
        // create and start the third intro dialog.
        efml_intro_object( [
            {
              element: document.querySelector( '.efml-import-dialog .easy-dialog-for-wordpress-text:first-of-type' ),
              title: efmlIntroJsVars.step_4_title,
              intro: efmlIntroJsVars.step_4_intro,
              position: 'bottom'
            },
          ], {
            // add example URL in textarea with animation and forward then to part 4.
            onComplete: function() {
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
            }
          }
        );
      }, 500)
    });

    /**
     * Part 5: Describe the resulting dialog and show additional hints.
     */
    document.addEventListener("efml-import-finished", function() {
      window.setTimeout(function() {
        efml_intro_object( [
            {
              element: document.querySelector( '.easy-dialog.efml' ),
              title: efmlIntroJsVars.step_6_title,
              intro: efmlIntroJsVars.step_6_intro,
            },
            {
              title: efmlIntroJsVars.step_7_title,
              intro: efmlIntroJsVars.step_7_intro,
            },
          ], {
            // use the done label on the last step.
            doneLabel: efmlIntroJsVars.button_title_done,
            // forward user to the same page without intro marker.
            onComplete: function() {
              window.location.href = efmlIntroJsVars.url_3;
            }
          }
        );
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
  let has_privacy_hint = true;
  if( document.querySelector( '.efml-import-dialog .easy-dialog-for-wordpress-text:has(#privacy_hint)' ) === null ) {
    has_privacy_hint = false;
    settings = [
      {
        element: document.querySelector( '.efml-import-dialog button:first-of-type' ),
        title: efmlIntroJsVars.step_5b_title,
        intro: efmlIntroJsVars.step_5b_intro,
        position: 'top'
      },
    ];
  }

  // create and start the fourth intro dialog.
  efml_intro_object( settings, {
    // set the privacy checkbox before the submit button is shown.
    onNext: function( index ) {
      if ( has_privacy_hint && index === 0 ) {
        jQuery('#privacy_hint').prop('checked', true);
      }
    },
    // trigger click on submit button in the import dialog.
    onComplete: function() {
      jQuery( '.efml-import-dialog button:first-of-type' ).trigger( 'click' );
    }
  } );
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
 * Create the intro object for the given steps and start it.
 *
 * Each step uses the keys "element" (optional), "title", "intro" and "position" (optional).
 *
 * Supported options:
 * - onNext: function( index ), called before the next step is shown.
 * - onComplete: function(), called after the button on the last step has been clicked.
 * - doneLabel: the label of the button on the last step.
 *
 * If the user closes the intro, it is marked as closed.
 *
 * @param steps List of steps.
 * @param options The options.
 * @returns {*}
 */
function efml_intro_object( steps, options ) {
  options = options || {};

  // convert the steps to the format of Driver.js.
  let driver_steps = steps.map( function( step ) {
    let driver_step = {
      popover: {
        title: step.title,
        description: step.intro
      }
    };
    if( step.element ) {
      driver_step.element = step.element;
    }
    if( step.position ) {
      driver_step.popover.side = step.position;
    }
    return driver_step;
  } );

  // create the object.
  let intro = window.driver.js.driver( {
    steps: driver_steps,
    nextBtnText: efmlIntroJsVars.button_title_next,
    prevBtnText: efmlIntroJsVars.button_title_back,
    doneBtnText: options.doneLabel || efmlIntroJsVars.button_title_next,
    showButtons: [ 'next', 'previous', 'close' ],
    showProgress: false,
    // do not close the intro by ESC or a click on the overlay, only via the close button.
    allowKeyboardControl: false,
    overlayClickBehavior: 'none',
    disableActiveInteraction: true,
    popoverClass: 'efml-intro',
    // handle the click on the next and the done button.
    onNextClick: function() {
      let index = intro.getActiveIndex();
      if( options.onNext ) {
        options.onNext( index );
      }

      // move to the next step, if this is not the last one.
      if( ! intro.isLastStep() ) {
        intro.moveNext();
        return;
      }

      // end the intro and run the following task.
      intro.destroy();
      if( options.onComplete ) {
        options.onComplete();
      }
    },
    // the user closes the intro: mark it as closed.
    onCloseClick: function() {
      intro.destroy();
      efml_intro_exit_handler( false );
    }
  } );

  // start the intro.
  intro.drive();

  return intro;
}
