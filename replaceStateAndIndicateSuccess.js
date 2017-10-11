/* 
 * Feedback plugin 
 * 
 * Manipulates the browser history/url so refreshes don't resubmit
 * Also, injects the status of the feedback into the page, and shows
 * a "More details" box for extra details. 
 */
(function ($) {
    var newhref = document.location.href.replace(/(.*)&feedback=.+/, '$1'),
            text = '#CONFIG#';

    $('body').append('<div id="dialog">' + text.dialog_heading +
            '<form action="tickets.php?id=' + text.ticket_id + '&feedbackcomments" method="POST">' +
            '<label for="feedbackbox">' + text.details_label + '</label>' +
            '<textarea name="feedbacktext" id="feedbacktext" value="" placeholder="" rows="15" cols="80"></textarea>' +
            '</form></div>');


    console.log("Plugin: Feedback has run.");
    $(document).on('ready',
            function () {
                if (!text.status) {
                    $('#ticketInfo').after('<h2 style="background-color: pink; border: 1px solid red; padding:10px;">' + text.bad + '</h2>');
                } else {
                    $('#ticketInfo').after('<h2 style="background-color: lightgreen; border: 1px solid green; padding:10px;">' + text.good + '</h2>');
                }
                $('#dialog').dialog({
                    modal: true,
                    height: 400,
                    minWidth: 582,
                    show: "blind",
                    hide: "blind",
                    position: {my: "center", at: "center", of: '#ticketInfo'},
                    buttons: [{
                            text: text.send_button_text,
                            icon: "ui-icon-heart",
                            click: function () {
                                $('#dialog form').submit();
                            }}]});

                return; // debug!

                // Clear the url part from the back-button.. 
                if (window.history.replaceState) {
                    //prevents browser from storing history with each change:
                    // not 100% that this actually works.. 
                    window.history.replaceState({}, document.title, newhref); // we were never here..
                    window.history.pushState({}, document.title, newhref);
                } else {
                    // Cry?.. fucking IE..
                    alert(text.good);
                    document.location.href = newhref;
                }
            });
})(jQuery);