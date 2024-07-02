<?php
/*
Plugin Name: Custom Chat Plugin
Description: A custom chat system for WordPress.
Version: 1.0
Author: kazuki Yamasaki
Email: ksirf2@gmail.com
*/

// Enqueue necessary scripts and styles
function custom_chat_enqueue_scripts() {
    wp_enqueue_style('custom-chat-style', plugin_dir_url(__FILE__) . 'css/style.css');
    wp_enqueue_script('custom-chat-script', plugin_dir_url(__FILE__) . 'js/script.js', array('jquery'), null, true);
    wp_localize_script('custom-chat-script', 'customChat', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('custom-chat-nonce'),
        'userId'  => get_current_user_id()
    ));
}
add_action('wp_enqueue_scripts', 'custom_chat_enqueue_scripts');

function custom_chat_enqueue_admin_styles() {
    wp_enqueue_style('custom-chat-admin-styles', plugin_dir_url(__FILE__) . 'css/admin.css');
    wp_enqueue_script('custom-chat-admin-script', plugin_dir_url(__FILE__) . 'js/admin.js', array('jquery'), null, true);
    wp_localize_script('custom-chat-admin-script', 'customChat', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('custom-chat-nonce')
    ));
}
add_action('admin_enqueue_scripts', 'custom_chat_enqueue_admin_styles');

// Add chat box to the footer
function custom_chat_box() {
    if (is_user_logged_in()) {

        $unread_count = custom_chat_get_user_unread_count(get_current_user_id());
        echo<<<HTML
>>><div id="custom-chat-box">
        <div class="custom-chat-box-header">
            <h3>お問い合わせ</h3>
            
            
</div>
            <div class="custom-chat-box-content">
                <div id="chat-messages"></div>
                <textarea name="" id="chat-input" cols="30" rows="3"></textarea>
                <button id="chat-send-btn">Send</button>
</div>
                                          <span class="update-plugins"><span class="plugin-count"> {$unread_count}</span></span>

              </div>
HTML;
    } else {
       echo "";
    }
}
add_action('wp_footer', 'custom_chat_box');

// Plugin activation hook to create database table
register_activation_hook(__FILE__, 'custom_chat_create_table');


function custom_chat_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_chat_messages';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        user_id bigint(20) NOT NULL,
        message text NOT NULL,
        is_admin tinyint(1) NOT NULL DEFAULT 0,
        admin_viewed tinyint(1) NOT NULL DEFAULT 0,
        user_viewed tinyint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Ajax handler for saving chat messages
add_action('wp_ajax_save_chat_message', 'custom_chat_save_message');

function custom_chat_save_message() {
    check_ajax_referer('custom-chat-nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in to send a message');
    }

    if (!isset($_POST['message']) || empty(trim($_POST['message']))) {
        wp_send_json_error('No message provided');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_chat_messages';

    $message = sanitize_text_field($_POST['message']);
    $user_id = get_current_user_id();
    $time = current_time('mysql');

    $wpdb->insert(
        $table_name,
        array(
            'time' => $time,
            'user_id' => $user_id,
            'message' => $message,
            'user_viewed' => 1
        )
    );

    wp_send_json_success(array(
        'time' => $time,
        'user_id' => $user_id,
        'message' => $message
    ));
}

// Function to retrieve chat messages
function custom_chat_get_messages() {
    check_ajax_referer('custom-chat-nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in to view messages');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_chat_messages';

    if (isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
    } else {
        $user_id = get_current_user_id();
    }
    if(!is_admin()){
        custom_chat_mark_as_user_viewed($user_id);

    }
    $messages = $wpdb->get_results($wpdb->prepare(
        "SELECT id, time, user_id, message, is_admin FROM $table_name WHERE user_id = %d ORDER BY time ASC",
        $user_id
    ));

    wp_send_json_success($messages);
}
add_action('wp_ajax_get_chat_messages', 'custom_chat_get_messages');
add_action('wp_ajax_nopriv_get_chat_messages', 'custom_chat_get_messages');

// Function to mark messages as viewed by user
function custom_chat_mark_as_user_viewed($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_chat_messages';
    $wpdb->update(
        $table_name,
        array('user_viewed' => 1),
        array('user_id' => $user_id, 'user_viewed' => 0)
    );
}

// Function to get user unread count
function custom_chat_get_user_unread_count($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_chat_messages';
    return $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND user_viewed = 0 AND is_admin = 1",
        $user_id
    ));
}

// Add admin menu for chat management
add_action('admin_menu', 'custom_chat_admin_menu');
add_action('admin_menu', 'custom_chat_add_menu_bubble');

function custom_chat_admin_menu() {
    add_menu_page(
        'User Chats',
        'User Chats',
        'manage_options',
        'custom-chat-admin',
        'custom_chat_admin_page',
        'dashicons-admin-comments',
        6
    );
}

function custom_chat_add_menu_bubble() {
    global $menu;
    $pending_count = custom_chat_get_pending_count();
    if ($pending_count > 0) {
        foreach ($menu as $key => $value) {
            if ($menu[$key][2] == 'custom-chat-admin') {
                $menu[$key][0] .= ' <span class="update-plugins count-' . $pending_count . '"><span class="plugin-count">' . $pending_count . '</span></span>';
                return;
            }
        }
    }
}

function custom_chat_get_pending_count() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_chat_messages';
    return $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE admin_viewed = 0");
}

// Mark messages as viewed for specific user by admin
function custom_chat_mark_as_viewed_for_admin($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_chat_messages';
    $wpdb->update(
        $table_name,
        array('admin_viewed' => 1),
        array('user_id' => $user_id, 'admin_viewed' => 0)
    );
}
function custom_chat_mark_as_viewed_for_user($user_id){
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_chat_messages';
    $wpdb->update(
        $table_name,
        array('user_viewed' => 1),
        array('user_id' => $user_id, 'user_viewed' => 0)
    );
}

function custom_chat_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_chat_messages';

    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_GET['user_id'])) {
        $selected_user_id = intval($_GET['user_id']);
        custom_chat_mark_as_viewed_for_admin($selected_user_id);
    }

    $users = get_users();
    $user_messages = [];
    foreach ($users as $user) {
        $user_messages[$user->ID] = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d ORDER BY time ASC",
            $user->ID
        ));
    }

    // Sort users by unread messages
    usort($users, function($a, $b) use ($user_messages) {
        $a_count = count(array_filter($user_messages[$a->ID], function($message) {
            return !$message->admin_viewed;
        }));
        $b_count = count(array_filter($user_messages[$b->ID], function($message) {
            return !$message->admin_viewed;
        }));
        return $b_count - $a_count;
    });

    ?>
    <div class="wrap">
        <h1>User Chats</h1>
        <ul>
            <?php foreach ($users as $user):
                $unread_count_admin = count(array_filter($user_messages[$user->ID], function($message) {
                    return !$message->admin_viewed;
                }));
                $unread_count_user = count(array_filter($user_messages[$user->ID], function($message) {
                    return !$message->user_viewed && $message->is_admin == 1;
                }));
                $full_name = $user->last_name . ' ' . $user->first_name;
                ?>
                <li class="user-chat">
                    <a href="#" class="user-name" data-user-id="<?php echo esc_attr($user->ID); ?>">
                        <?php echo esc_html($full_name); ?>
                        <?php if (is_admin() && $unread_count_admin > 0): ?>
                            <span class="update-plugins count-<?php echo $unread_count_admin; ?>"><span class="plugin-count"><?php echo $unread_count_admin; ?></span></span>
                        <?php endif; ?>
                        <?php if (!is_admin() &&$unread_count_user > 0): ?>
                            <span class="update-plugins count-<?php echo $unread_count_admin; ?>"><span class="plugin-count"><?php echo $unread_count_user; ?></span></span>
                        <?php endif; ?>
                    </a>
                    <div class="chat-content" id="chat-<?php echo esc_attr($user->ID); ?>" style="display:none;">
                        <div class="chat-messages">
                            <?php foreach ($user_messages[$user->ID] as $message):
                                $class = $message->is_admin ? 'admin-message' : 'user-message';
                                $sender = $message->is_admin ? '管理者' : esc_html($message->user_id);
                                ?>
                                <div class="chat-message <?php echo $class; ?>">
                                    <strong><?php echo $sender; ?>:</strong> <?php echo esc_html($message->message); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <form method="post" action="" class="admin-chat-form" data-user-id="<?php echo esc_attr($user->ID); ?>">
                            <?php wp_nonce_field('custom_chat_admin_nonce'); ?>
                            <textarea name="message" rows="4" cols="50" placeholder="Type your message..."></textarea>
                            <button type="button" class="button button-primary send-message-btn">Send Message</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php
}

// Ajax handler to save chat message from admin
add_action('wp_ajax_save_admin_chat_message', 'custom_chat_save_message_from_admin');

function custom_chat_save_message_from_admin() {
    check_ajax_referer('custom-chat-nonce', 'nonce');

    if (!current_user_can('manage_options') || !isset($_POST['message']) || !isset($_POST['user_id'])) {
        wp_send_json_error('Invalid request');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_chat_messages';

    $message = sanitize_text_field($_POST['message']);
    $user_id = intval($_POST['user_id']);
    $time = current_time('mysql');

    $wpdb->insert(
        $table_name,
        array(
            'time' => $time,
            'user_id' => $user_id,
            'message' => $message,
            'is_admin' => 1,
            'admin_viewed' => 1,
            'user_viewed' => 0
        )
    );

    wp_send_json_success();
}

add_action('wp_ajax_mark_as_viewed', 'custom_chat_mark_as_viewed');
add_action('wp_ajax_mark_as_viewed_user', 'custom_chat_mark_as_viewed_user');

function custom_chat_mark_as_viewed() {
    check_ajax_referer('custom-chat-nonce', 'nonce');

    if (!current_user_can('manage_options') || !isset($_POST['user_id'])) {
        wp_send_json_error('Invalid request');
    }

    custom_chat_mark_as_viewed_for_admin(intval($_POST['user_id']));
    wp_send_json_success();
}
function custom_chat_mark_as_viewed_user(){
    check_ajax_referer('custom-chat-nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error('Invalid request');
        exit();
    }
    $user_id=get_current_user_id();

    custom_chat_mark_as_viewed_for_user(intval($user_id));
    wp_send_json_success();
}