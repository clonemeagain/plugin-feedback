/* 
 * Feedback plugin 
 * 
 * Manipulates the browser history/url so refreshes don't resubmit
 * Also, injects the status of the feedback into the page, and shows
 * a "More details" box for extra details. 
 */
(function ($) {
    var newhref = document.location.href.replace(/(.*)&feedback=.+/, '$1'),
            data = '#CONFIG#';
    console.log(data);
    $(document).on('ready',
            function () {
                $('#ticketInfo').append(
                        '<div id="dialog">' + data.dialog_heading +
                        '<label for="text">' + data.details_label + '</label>' +
                        '<textarea name="text" value="" placeholder="' + data.suggestion + '" rows="15" cols="80"></textarea>' +
                        '</div>');
                $('#dialog').dialog({
                    modal: true,
                    height: 400,
                    minWidth: 582,
                    show: "blind",
                    hide: "blind",
                    position: {my: "center", at: "center", of: '#ticketInfo'},
                    buttons: [{
                            text: data.send_button_text,
                            icon: "ui-icon-heart",
                            click: function () {
                                $.ajax({
                                    type: 'post',
                                    url: data.url,
                                    data: {
                                        ticket_id: data.ticket_id,
                                        vote: data.vote,
                                        text: $('#dialog textarea').val(),
                                        token: data.token,
                                    },
                                    success: function (msg) {
                                        $('#ticketInfo').after('<h2 style="background-color: lightgreen; border: 1px solid green; padding:10px;">' + data.good + '</h2>');
                                    },
                                    error: function (xhr) {
                                        $('#ticketInfo').after('<h2 style="background-color: pink; border: 1px solid red; padding:10px;">' + data.bad + '</h2>');
                                        console.log(xhr);
                                    },
                                    complete: function () {
                                        $(".ui-dialog-titlebar-close").click();
                                    }
                                })
                            }}]});
                console.log("Plugin: Feedback has run.");
                return; // debug!

                // Clear the url part from the back-button.. 
                if (window.history.replaceState) {
                    //prevents browser from storing history with each change:
                    // not 100% that this actually works.. 
                    window.history.replaceState({}, document.title, newhref); // we were never here..
                    window.history.pushState({}, document.title, newhref);
                } else {
                    // Cry?.. fucking IE..
                    alert(data.good);
                    document.location.href = newhref;
                }
            });
})(jQuery);