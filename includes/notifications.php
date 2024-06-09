<?php

// Реєструємо новий компонент для сповіщень
function bp_revitek_register_component( $component_names = array() ) {
    if ( ! is_array( $component_names ) ) {
        $component_names = array();
    }

    // Додаємо 'revitek' до списку зареєстрованих компонентів
    array_push( $component_names, 'revitek' );
    return $component_names;
}
add_filter( 'bp_notifications_get_registered_components', 'bp_revitek_register_component' );

// Форматуємо сповіщення для ретвітів
function bp_revitek_format_notifications( $content, $item_id, $secondary_item_id, $total_items, $format = 'string', $action, $component_name ) {
    if ( 'revitek_action' === $action ) {
        $reviteker = get_userdata( $secondary_item_id );
        if ( !$reviteker ) {
            error_log( 'Reviteker not found for ID: ' . $secondary_item_id );
            return false;
        }

        $activity = new BP_Activity_Activity( $item_id );
        if ( !$activity ) {
            error_log( 'Activity not found for ID: ' . $item_id );
            return false;
        }

        $post_link = bp_activity_get_permalink( $item_id, $activity );

        /* translators: %s is the display name of the user who reviteked the post */
        $title = sprintf( __( 'Your post was reviteked by %s', 'bp-revitek' ), esc_html( $reviteker->display_name ) );
        
        /* translators: %s is the display name of the user who reviteked the post */
        $text = sprintf( __( 'Your post was reviteked by %s', 'bp-revitek' ), esc_html( $reviteker->display_name ) );

        
        if ( 'string' === $format ) {
            $content = apply_filters( 'bp_revitek_format', '<a href="' . esc_url( $post_link ) . '" title="' . esc_attr( $title ) . '">' . esc_html( $text ) . '</a>', $text, $post_link );
        } else {
            $content = apply_filters( 'bp_revitek_format', array(
                'text' => $text,
                'link' => $post_link
            ), $post_link, $total_items, $text, $title );
        }

        return $content;
    }

    return $content;
}
add_filter( 'bp_notifications_get_notifications_for_user', 'bp_revitek_format_notifications', 10, 7 );

// Відправка сповіщення про ретвіт
function bp_revitek_send_notification( $activity_id, $user_id ) {
    $activity = new BP_Activity_Activity( $activity_id );

    if ( $activity->user_id == $user_id ) {
        error_log( 'Revitek notification not sent: user is the author' );
        return; // Не надсилаємо сповіщення автору
    }

    $result = bp_notifications_add_notification( array(
        'user_id'           => $activity->user_id,
        'item_id'           => $activity_id,
        'secondary_item_id' => $user_id,
        'component_name'    => 'revitek',
        'component_action'  => 'revitek_action'
    ));

    if ( !$result ) {
        error_log( 'Failed to add notification for revitek' );
    }
}
?>
