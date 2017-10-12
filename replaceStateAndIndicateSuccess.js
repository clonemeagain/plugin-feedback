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
    $(document).on('ready',
            function () {
                var getData = function () {
                    return {
                        ticket_id: data.ticket_id,
                        vote: data.vote,
                        text: $('#dialog textarea').val(),
                        token: data.token
                    };
                };
                $('#ticketInfo').append('<div id="dialog" style="display:none;">' +
                        '<div class="feedback-heading" style="padding:10px;">' + data.dialog_heading + '</div>' +
                        '<div class="feedback-input" style="margin:10px;"><textarea name="text" value="" placeholder="' + data.suggestion + '"></textarea></div>' +
                        '</div>');
                // The page has loaded, we're showing the modal, let's save the feedback now, and if they update the input and send, we'll update it.
                $.ajax({type: 'post', url: data.url, data: getData()});
                // Show the modal dialog to the user asking for additional comments:
                var dialog = $('#dialog')
                        .dialog({
                            modal: true,
                            height: 410,
                            minWidth: 592,
                            show: "blind",
                            hide: "blind",
                            closeText: '', //data.close_button_text,
                            position: {my: "center", at: "center", of: '#ticketInfo'},
                            buttons: [{
                                    text: data.send_button_text,
                                    icon: "ui-icon-heart",
                                    "class": "feedback-sendButton",
                                    click: function () {
                                        $.ajax({
                                            type: 'post',
                                            url: data.url,
                                            data: getData(),
                                            success: function (msg) {
                                                $('#ticketInfo').after('<div style="background-color: lightgreen; border: 1px solid green; padding:10px;">' + data.good + '</div>');
                                            },
                                            error: function (xhr) {
                                                $('#ticketInfo').after('<div style="background-color: pink; border: 1px solid red; padding:10px;">' + data.bad + '</div>');
                                                console.log(xhr);
                                            },
                                            complete: function () {
                                                $(".ui-dialog-titlebar-close").click();
                                            }
                                        });
                                    }}],
                            close: function (event, ui) {
                                $(this).dialog('destroy');
                            }
                        });
                console.log("Plugin: Feedback has run.");
                // for debugging, just return here, otherwise you have to keep adding the key
                //return true;
                // Clear the url part from the back-button.. 
                // ergo: View Source won't show this
                if (window.history.replaceState) {
                    //prevents browser from storing history with each change:
                    // not 100% that this actually works.. 
                    window.history.replaceState({}, document.title, newhref); // we were never here..
                    window.history.pushState({}, document.title, newhref);
                } else {
                    // Cry?.. fucking IE..
                    //document.location.href = newhref;
                }
            });
})(jQuery);