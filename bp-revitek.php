<?php
/**
 * Plugin Name: Revitek post for BuddyPress 
 * Description: Adds a revitek functionality to BuddyPress activity streams.
 * Version: 1.1.0
 * Author: Koka Boka
 * Text Domain: bp-revitek
 */

// Визначаємо константи плагіна
define( 'BP_REVITEK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Підключаємо необхідні файли
require_once BP_REVITEK_PLUGIN_DIR . 'includes/functions.php';
require_once BP_REVITEK_PLUGIN_DIR . 'includes/notifications.php';

// Підключаємо стилі та скрипти
function bp_revitek_enqueue_scripts() {
    wp_enqueue_style( 'bp-revitek-css', plugins_url( 'assets/css/bp-revitek.css', __FILE__ ), array(), '1.0' );
    wp_enqueue_script( 'bp-revitek-js', plugins_url( 'assets/js/bp-revitek.js', __FILE__ ), array( 'jquery' ), '1.0', true );

    wp_localize_script(
        'bp-revitek-js',
        'bpRevitek',
        array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'bp_revitek_nonce' )
        )
    );
}
add_action( 'wp_enqueue_scripts', 'bp_revitek_enqueue_scripts' );

// Завантаження текстового домену для перекладів
function bp_revitek_load_textdomain() {
    load_plugin_textdomain( 'bp-revitek', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'bp_revitek_load_textdomain' );

// Створення таблиці при активації плагіна
function bp_revitek_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bp_reviteks';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        activity_id bigint(20) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'bp_revitek_create_table' );

// Перевірка наявності потрібних таблиць
register_activation_hook(__FILE__, 'bp_revitek_check_db');

function bp_revitek_check_db() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'bp_reviteks';
    $cached_result = wp_cache_get('bp_revitek_check_db', 'bp_revitek');

    if ($cached_result === false) {
        $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        wp_cache_set('bp_revitek_check_db', $result, 'bp_revitek', 3600); // Кешуємо результат на 1 годину
    } else {
        $result = $cached_result;
    }

    if ($result != $table_name) {
        bp_revitek_create_table();
    }
}

// Отримання даних з кешу або бази даних
function bp_revitek_get_data() {
    $cached_data = wp_cache_get('bp_reviteks_data', 'bp_reviteks');

    if ($cached_data === false) {
        $query_args = array(
            'post_type'      => 'bp_reviteks', // Замініть на ваш тип запису
            'posts_per_page' => -1,
            'fields'         => 'ids', // Використовуємо тільки ідентифікатори для економії ресурсів
        );

        $query = new WP_Query($query_args);

        if ($query->have_posts()) {
            $cached_data = $query->posts;
            wp_cache_set('bp_reviteks_data', $cached_data, 'bp_reviteks', 3600); // Кешуємо дані на годину
        } else {
            $cached_data = array(); // Порожній масив, якщо немає даних
        }
    }

    return $cached_data;
}

// Видалення кешу під час деактивації плагіна
register_deactivation_hook(__FILE__, 'bp_revitek_clear_cache');

function bp_revitek_clear_cache() {
    wp_cache_delete('bp_reviteks_data', 'bp_reviteks');
    wp_cache_delete('bp_revitek_check_db', 'bp_revitek');
}
?>
