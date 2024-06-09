<?php

function bp_revitek_add_button() {
    if (is_user_logged_in() && bp_get_activity_id()) {
        $activity_id = absint(bp_get_activity_id()); // Використовуємо absint() для перевірки activity_id
        $count = bp_revitek_get_count($activity_id);
        $button = sprintf(
            '<button class="button revitek-button" data-activity-id="%d" data-nonce="%s"><span class="revitek-class-button">%s</span><span class="revitek-class-count">(%d)</span></button>',
            esc_attr($activity_id), // Екрануємо activity_id
            wp_create_nonce('bp_revitek_nonce'), // Не потрібно екранувати, оскільки це nonce
            esc_html__('Revitek', 'bp-revitek'), // Екрануємо текст кнопки
            esc_html($count) // Екрануємо лічильник
        );
        echo wp_kses_post($button); // Екрануємо змінну $button перед виведенням
    }
}
add_action('bp_activity_entry_meta', 'bp_revitek_add_button', 10, 0);

function bp_revitek_handle_action() {
    check_ajax_referer('bp_revitek_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(__('User not logged in', 'bp-revitek'));
    }

    $activity_id = isset($_POST['activity_id']) ? absint($_POST['activity_id']) : 0; // Додали перевірку на наявність activity_id

    if (!$activity_id) {
        wp_send_json_error(__('Invalid activity ID', 'bp-revitek'));
    }

    if (bp_revitek_exists(get_current_user_id(), $activity_id)) {
        wp_send_json_error(__('Revitek already exists', 'bp-revitek'));
    }

    $result = bp_revitek_create_activity(get_current_user_id(), $activity_id);

    if ($result === true) {
        $count = bp_revitek_get_count($activity_id);
        wp_send_json_success(array('count' => $count));
    } else {
        wp_send_json_error(__('Failed to create revitek activity', 'bp-revitek'));
    }
}
add_action('wp_ajax_bp_revitek', 'bp_revitek_handle_action');

function bp_revitek_exists($user_id, $activity_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bp_reviteks';
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM $table_name WHERE user_id = %d AND activity_id = %d",
        $user_id, $activity_id
    ));
    return $exists > 0;
}

function bp_revitek_create_activity($user_id, $activity_id) {
    // Отримуємо оригінальну активність
    $original_activity = new BP_Activity_Activity($activity_id);

    if (!$original_activity->id) {
        error_log(__('Original activity not found', 'bp-revitek'));
        return false;
    }

    // Перевіряємо, чи вже міститься текст "Reviteked:" у змісті оригінальної активності
    if (strpos($original_activity->content, __('Reviteked:', 'bp-revitek')) === 0) {
        // Якщо так, просто повертаємо true, оскільки ревітек вже створений
        return true;
    }

    // Створюємо новий запис активності
    $new_activity = new BP_Activity_Activity();
    $new_activity->user_id = $user_id;
    $new_activity->component = $original_activity->component;
    $new_activity->type = 'activity_update'; // Встановіть відповідний тип активності
    $new_activity->content = $original_activity->content;
    $new_activity->primary_link = $original_activity->primary_link;
    $new_activity->item_id = $original_activity->item_id;
    $new_activity->secondary_item_id = $original_activity->id;
    $new_activity->date_recorded = bp_core_current_time();
    $new_activity_id = $new_activity->save();

    if ( !$new_activity_id ) {
    error_log('Failed to save new activity');
    return false;
}


    // Додаємо запис у таблицю ревітеків
    $result = bp_revitek_add($user_id, $activity_id);

    if (!$result) {
        error_log(__('Failed to add revitek to the database', 'bp-revitek'));
        return false;
    }

    bp_revitek_send_notification($activity_id, $user_id);

    return true;
}

function bp_revitek_add($user_id, $activity_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bp_reviteks';
    return $wpdb->insert(
        $table_name,
        array(
            'user_id' => $user_id,
            'activity_id' => $activity_id
        ),
        array(
            '%d',
            '%d'
        )
    );
}

function bp_revitek_get_count($activity_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bp_reviteks';
    return $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(id) FROM $table_name WHERE activity_id = %d",
        $activity_id
    ));
}

function bp_revitek_display_banner($content, $activity) {
    if ($activity->type == 'activity_update' && $activity->secondary_item_id) {
        $original_activity = bp_revitek_get_original_activity($activity->secondary_item_id);
        if ($original_activity) {
            $user = get_userdata($original_activity->user_id);
            // Отримуємо URL оригінальної активності
            $original_activity_url = bp_activity_get_permalink($original_activity->id);
            $banner = sprintf(
                '<div id="revitek-banner" class="revitek-banner"><img class="avatar" src="%s" alt="%s" /><a href="%s">%s<i class="fas fa-retweet"></i></a></div>',
                esc_url(bp_core_fetch_avatar(array('item_id' => $original_activity->user_id, 'html' => false))),
                esc_attr($user->display_name),
                esc_url($original_activity_url),
                esc_html($user->display_name)
            );
            $content = $banner . $content;
        }
    }
    return $content;
}
add_filter('bp_get_activity_content_body', 'bp_revitek_display_banner', 10, 2);


function bp_revitek_get_original_activity($activity_id) {
    return new BP_Activity_Activity($activity_id);
}
?>
