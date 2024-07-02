jQuery(document).ready(function($) {
    $('.user-name').on('click', function(e) {
        e.preventDefault();
        var userId = $(this).data('user-id');

        $('#chat-' + userId).slideToggle();
        fetchMessages(userId); // Initial fetch
        $.post(ajaxurl, {
            action: 'mark_as_viewed',
            user_id: userId,
            nonce: customChat.nonce
        });
    });

    function fetchMessages(userId) {
        $.post(ajaxurl, {
            action: 'get_chat_messages',
            user_id: userId,
            nonce: customChat.nonce
        }, function(response) {
            if (response.success) {
                var chatMessagesDiv = $('#chat-' + userId + ' .chat-messages');
                chatMessagesDiv.empty();
                $.each(response.data, function(index, message) {
                    var date = new Date(message.time);
                    var formattedDate = date.toLocaleString();
                    var displayMessage = message.is_admin == 1
                        ? '<div class="chat-message admin-message"><strong>管理者</strong> (' + formattedDate + '): ' + message.message + '</div>'
                        : '<div class="chat-message user-message">(' + formattedDate + '): ' + message.message + '</div>';
                    chatMessagesDiv.append(displayMessage);
                });
            }
        }).fail(function(xhr, status, error) {
            console.log('Error:', error);
        });
    }

    function sendMessage(userId, message) {
        $.post(ajaxurl, {
            action: 'save_admin_chat_message',
            message: message,
            user_id: userId,
            nonce: customChat.nonce
        }, function(response) {
            if (response.success) {
                fetchMessages(userId); // Refresh messages after sending
            } else {
                alert('Message could not be saved: ' + response.data);
            }
        }).fail(function(xhr, status, error) {
            console.log('Error:', error);
        });
    }

    $('.send-message-btn').on('click', function() {
        var form = $(this).closest('form');
        var userId = form.data('user-id');
        var message = form.find('textarea[name="message"]').val();
        if (message.trim() !== '') {
            sendMessage(userId, message);
            form.find('textarea[name="message"]').val(''); // Clear the textarea
        }
    });

    setInterval(function() {
        $('.user-chat').each(function() {
            var userId = $(this).find('.user-name').data('user-id');
            fetchMessages(userId);
        });
    }, 50000); // Fetch new messages every 5 seconds
});
