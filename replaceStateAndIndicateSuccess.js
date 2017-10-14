/* 
 * Feedback plugin 
 * 
 * Manipulates the browser history/url so refreshes don't resubmit
 * Also, injects the status of the feedback into the page, and shows
 * a "More details" box for extra details. 
 */
(function ($) {
    var newhref = document.location.href.replace(/(.*)&feedback=.+/, '$1');
    var data = '#CONFIG#';
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
                console.log(data);
                $('#content').append('<div id="dialog" style="display:none;">' +
                        '<div class="feedback-heading" style="padding:10px;">' + data.dialog_heading + '</div>' +
                        '<div class="votegroup green-border"><input type="radio" name="vote" value="up" id="upvote"/>' +
                        '<label for="upvote">' + data.options.up + '</label></div>' +
                        '<div class="votegroup"><input type="radio" name="vote" value="meh" id="mehvote"/>' +
                        '<label for="mehvote">' + data.options.meh + '</label></div>' +
                        '<div class="votegroup red-border"><input type="radio" name="vote" value="down" id="downvote"/>' +
                        '<label for="downvote">' + data.options.down + '</label></div>' +
                        '<div class="feedback-input"><textarea name="text" value="" placeholder="' + data.options.placeholder + '"></textarea></div>' +
                        '</div>');
                // The page has loaded, we're showing the modal, let's save the feedback now, and if they update the input and send, we'll update it.
                $.ajax({type: 'post', url: data.url, data: getData(),
                    success: function () {
                        // all good man
                        // Clear the url part from the back-button.. 
                        // ergo: View Source won't show this script
                        if (window.history.replaceState && false) {
                            //prevents browser from storing history with each change:
                            window.history.replaceState({}, document.title, newhref); // we were never here..
                            window.history.pushState({}, document.title, newhref);
                        }
                    },
                    error: function (xhr) {
                        console.log(xhr);
                        $('button.feedback-sendButton').attr('disabled', true);
                        $('div.feedback-input').append('<span ')
                                .append('You need to <a target="_blank" style="text-decoration:underline;" href="' + newhref + '"> login</a> to post this, just refresh this page when you\'re logged in.');
                    }
                });

                // Select the selected one, but listen if it changes
                $('.votegroup label').on('click', function (e) {
                    var elem = $(e.target);
                    data.vote = elem.parent().find('input').val();
                    $('div.feedback-heading').html(data.dialog_headings[data.vote]);
                    $('.votegroup span.check').remove();
                    elem.parent().prepend('<span class="check">&#x2714;</span>');
                });
                $('label[for=' + data.vote + 'vote]').click();
                // Show the modal dialog to the user asking for additional comments:
                $('#dialog').dialog({
                    modal: true,
                    height: 410,
                    minWidth: 592,
                    show: "blind",
                    hide: "blind",
                    closeText: '', //data.close_button_text,
                    position: {my: "center", at: "center", of: '#content'},
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
                                        $('#ticketInfo').after('<div style="background-color: pink; border: 1px solid red; padding:10px;">' + data.bad + xhr.responseText + '</div>');
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
            });
})(jQuery);