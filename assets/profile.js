/**
 * Created by kate on 21/05/16.
 */
(function($){
    var container = $('#auth_user');

    container.html( '<tr><th><label>' + window.Profile.th_text + '</label></th><td><a class="button thickbox" href="' + window.Profile.ajax + '&KeepThis=true&TB_iframe=true&height=250&width=450">' + window.Profile.button_text + '</a></td></tr>' );

    $( '.button', container ).on( 'click', function( ev ) {
        ev.preventDefault();
    } );
})(jQuery);