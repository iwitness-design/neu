(function($) {

  var joinleave_group_change_handler = function() {
    // if the join/leave group button was clicked and ajax call is over (no spinner),
    // refresh the page so that we see the success message & email settings
    if (
      $(this).children().length > 0 &&
      $(this).find('a[class$=-group]').length > 0 &&
      $(this).find('.fa-spin').length === 0
    ) {
      // we really want to see #message, but the top bar covers it so aim a little higher
      window.location.replace(window.location.pathname + window.location.search + '#main');
      // unless we reload, browser simply scrolls up to the anchor.
      // we want to see the email options so refresh everything.
      window.location.reload();
    }
  };

  var getQueryVariable = function(variable) {
    var query = window.location.search.substring(1);
    var vars = query.split("&");
    for (var i=0;i<vars.length;i++) {
      var pair = vars[i].split("=");
      if(pair[0] == variable){return pair[1];}
    }
    return(false);
  }

  $(document).ready(function(){
    var searchQuery = getQueryVariable('s');
    var searchInput = $('#members_search');
     
      $("#topic-form-toggle").on('click', '#add', function() {
    $(".topic-form").slideToggle("slow");
    $("#add").hide();
    $('html,body').animate({
            scrollTop: $(".topic-form").offset().top},
            'slow');
  });  


    // preserve url searches by copying them to the search box if necessary
    if (searchQuery.length > 0 && searchInput.val() === '') {
      searchInput.val(searchQuery.replace(/\+/g," "));
    }

    if ( $( '#send-to-input').get( 0 ) ) {
      $('#send-to-input').bp_mentions( bp.mentions.users );
    }

    // we need live() to affect pages of groups loaded via ajax.
    $('#groups-dir-list .group-button').live('DOMSubtreeModified', joinleave_group_change_handler);

    // groups directory does not run a new query if "back" button was clicked due to browser cache, so force refresh
    // (without this, results on page can be from the wrong tab despite which is "selected")
    if ($('#members-dir-list, #groups-dir-list').length > 0) {
      $('.item-list-tabs .selected a').trigger('click');
    }

    // disable this since it breaks in safari and isn't really useful anyway
    $.fn.jRMenuMore = function () {}

    $('form#hc-terms-acceptance-form input[type=submit][name=hc_accept_terms_continue]').on('click', function(){
            if ( $('form#hc-terms-acceptance-form input[type=checkbox][name=hc_accept_terms]').is(':checked') ) {
                    $('#hc-terms-acceptance-form').submit();
            } else {
                    alert('Please agree to the terms by checking the box next to "I agree".');
            }
    });

    //this handles the ajax for settings-general.php in single member view
    $('.settings_general_submit input').on('click', function( event ) {

      $.ajax({
        method: 'POST',
        url: ajaxurl,
        data: {
          action: 'hcommons_settings_general',
          nonce: settings_general_req.nonce,
          primary_email: $('.email_selection input[type="radio"]:checked').val()
        },
        cache: false
      }).done(function(data) {

        //store all radio buttons in this var to loop through later
        var radio = $('.email_selection input[type="radio"]');

        //loop through each radio button and whichever one was saved is the one that will be checked.
        radio.each(function( i, v ) {

        //in the context of the current loop
        if( $(this).val() == data.primary_email ) {

          $(this).prop( 'checked', true );
        }

        });

        $('html, body').animate({ scrollTop: 0 }, 'fast');

        //ajax message to assert user that the data has been infact, updated
          $('#item-header-cover').prepend(
            $('<div />', { id: "message", class: "bp-template-notice updated" }).append(
                $('<p />').text('Changed saved.')
              )
            );

      });

      event.preventDefault();

    });
    
   
    // admins can add these classes in the widget options, but only to
    // the content of widgets which still leaves an empty box with a
    // border unless we also add the class to the container.
    $( '.hide-if-logged-in.panel-widget-style' ).parent().addClass( 'hide-if-logged-in' );
    $( '.hide-if-logged-out.panel-widget-style' ).parent().addClass( 'hide-if-logged-out' );

    // handle usernames with and without @ in message compose form
    $( '#send_message_form' ).on( 'submit', function( e ) {
      $( '#send-to-input' ).val( $( '#send-to-input' ).val().replace( '@', '' ) );
    } );
  });

})(jQuery);
