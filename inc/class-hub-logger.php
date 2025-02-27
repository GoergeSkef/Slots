<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Hub_Logger {

    // Table name will be set in create_logs_table.
    private static $table_name;

    public static function init() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'hubplugin_logs';
    }

    /**
     * Create the logs table on plugin activation
     */
    public static function create_logs_table() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'hubplugin_logs';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS " . self::$table_name . " (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            site_id VARCHAR(255) DEFAULT '' NOT NULL,
            event_type VARCHAR(100) DEFAULT '' NOT NULL,
            message TEXT NOT NULL,
            payload LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Log an event to the database
     */
    public static function log( $site_id, $event_type, $message, $payload = array() ) {
        global $wpdb;
        if ( empty( self::$table_name ) ) {
            self::init();
        }

        $wpdb->insert(
            self::$table_name,
            array(
                'site_id'    => $site_id,
                'event_type' => $event_type,
                'message'    => $message,
                'payload'    => maybe_serialize( $payload ), // or maybe_json_encode
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );
    }

    /**
     * Log an API request to the database
     */
    public static function log_api_request($domain, $endpoint, $status) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix.'hub_api_logs',
            [
                'timestamp' => current_time('mysql'),
                'domain' => $domain,
                'endpoint' => $endpoint,
                'status_code' => $status
            ]
        );
    }
}
