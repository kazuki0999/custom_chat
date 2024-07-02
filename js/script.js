jQuery(document).ready(function($) {

    jQuery(".custom-chat-box-header").click(function(){
        var display = $(".custom-chat-box-content").css("display");

        if(display === "flex"){
            jQuery(".custom-chat-box-content").css(`display`,"none") ;

        }else{
            jQuery(".custom-chat-box-content").css(`display`,"flex") ;

        }
    });

    function fetchMessages() {
        $.post(customChat.ajaxurl, {
            action: 'get_chat_messages',
            nonce: customChat.nonce
        }, function(response) {
            if (response.success) {
                $('#chat-messages').empty();
                $.each(response.data, function(index, message) {
                    var date = new Date(message.time);
                    var formattedDate = date.toLocaleString();
                    var sender = message.is_admin ? '管理者' : message.user_id;
                    var displayMessage = message.is_admin ===1 ? '<div class="chat-message admin-message"><span class="chat-message-meta">管理者 <small>(' + formattedDate + ')</small></span>  ' + message.message + '</div>' : '<div class="chat-message user-message"><span class="chat-message-meta"><small>(' + formattedDate + ')</small></span>' + message.message + '</div>';
                    $('#chat-messages').append(displayMessage);
                });
            }
        }).fail(function(xhr, status, error) {
            console.log('Error:', error);
        });
    }

    function sendMessage() {
        var message = $('#chat-input').val();
        if (message.trim() !== '') {
            $.post(customChat.ajaxurl, {
                action: 'save_chat_message',
                message: message,
                nonce: customChat.nonce
            }, function(response) {
                if (response.success) {
                    fetchMessages(); // Refresh messages after sending
                    $('#chat-input').val('');
                } else {
                    alert('Message could not be saved: ' + response.data);
                }
            }).fail(function(xhr, status, error) {
                console.log('Error:', error);
            });
        }
    }

    $('#chat-send-btn').click(sendMessage);

    // $('#chat-input').keypress(function(e) {
    //     if (e.which == 13) { // 13 is the key code for "Enter"
    //         sendMessage();
    //         return false; // Prevent the default action of the enter key
    //     }
    // });

    setInterval(fetchMessages, 50000); // Fetch new messages every 5 seconds
    fetchMessages(); // Initial fetch
});
