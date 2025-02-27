<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Trait for handling UUID operations across the plugin.
 */
trait UUID_Handler {
    /**
     * Initialize UUID handling
     */
    public static function init_uuid_handler() {
        // Hook into post save to ensure UUID exists
        add_action('save_post_slot', array(__CLASS__, 'ensure_slot_has_uuid'), 10, 3);
    }

    /**
     * Ensure a slot post has a UUID, creating one if it doesn't exist
     * 
     * @param int $post_id The post ID
     * @param WP_Post $post The post object
     * @param bool $update Whether this is an update
     */
    public static function ensure_slot_has_uuid($post_id, $post, $update) {
        // If this is just a revision, don't proceed
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // Check if post already has a UUID
        $existing_uuid = get_post_meta($post_id, 'uuid', true);
        
        if (empty($existing_uuid)) {
            // Only generate and save a new UUID if one doesn't exist
            $new_uuid = self::generate_uuid();
            error_log("Generating new UUID for post {$post_id}: {$new_uuid}");
            update_post_meta($post_id, 'uuid', $new_uuid);
        } else {
            error_log("Post {$post_id} already has UUID: {$existing_uuid}");
        }
    }

    /**
     * Find a slot by its UUID stored in post meta.
     * 
     * @param string $uuid The UUID to search for.
     * @return WP_Post|false The found post or false if not found.
     */
    protected static function find_slot_by_uuid($uuid) {
        if (empty($uuid) || !self::is_valid_uuid($uuid)) {
            return false;
        }
        
        $args = array(
            'post_type'   => 'slot',
            'meta_key'    => 'uuid',
            'meta_value'  => $uuid,
            'post_status' => 'any',
            'numberposts' => 1,
        );
        $found = get_posts($args);
        return !empty($found) ? $found[0] : false;
    }

    /**
     * Validate UUID format.
     * 
     * @param string $uuid The UUID to validate.
     * @return bool Whether the UUID is valid.
     */
    protected static function is_valid_uuid($uuid) {
        if (!is_string($uuid)) {
            return false;
        }
        
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', strtolower($uuid)) === 1;
    }

    /**
     * Generate a new UUID v4.
     * 
     * @return string A new UUID v4.
     */
    protected static function generate_uuid() {
        // Generate 16 bytes of random data
        if (function_exists('random_bytes')) {
            $data = random_bytes(16);
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $data = openssl_random_pseudo_bytes(16);
        } else {
            // Fallback to mt_rand (less secure but always available)
            $data = '';
            for ($i = 0; $i < 16; $i++) {
                $data .= chr(mt_rand(0, 255));
            }
        }

        // Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        // Output the 36 character UUID
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
