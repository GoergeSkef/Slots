<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if (!file_exists(__DIR__ . '/trait-uuid-handler.php')) {
    error_log('UUID Handler trait file not found');
}
require_once( __DIR__ . '/trait-uuid-handler.php' );

class Hub_API {
    use UUID_Handler;

    /**
     * Register custom REST routes for this plugin.
     */
    public static function register_routes() {
        // Initialize UUID handling
        self::init_uuid_handler();

        // Register routes under 'hub/v1' namespace
        register_rest_route('hub/v1', '/verify-connection', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'verify_connection'),
            'permission_callback' => array(__CLASS__, 'permission_check'),
        ));

        // e.g. POST /wp-json/hub-plugin/v1/slots/push
        register_rest_route( 'hub-plugin/v1', '/slots/push', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'handle_push_from_client' ),
            'permission_callback' => array( __CLASS__, 'permission_check' ),
        ) );

        // e.g. GET /wp-json/hub-plugin/v1/slots
        register_rest_route( 'hub-plugin/v1', '/slots', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'list_slots_for_client' ),
            'permission_callback' => array( __CLASS__, 'permission_check' ),
        ) );

        // Optional: route to push updates from hub to a client (placeholder)
        // e.g. POST /wp-json/hub-plugin/v1/slots/push-to-client
        register_rest_route( 'hub-plugin/v1', '/slots/push-to-client', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'push_updates_to_client' ),
            'permission_callback' => array( __CLASS__, 'permission_check' ),
        ) );
    }

    /**
     * Basic permission check placeholder.
     * In real life, you would check for an API key, user capabilities, or other security measures.
     */
    public static function permission_check($request) {
        $api_key = $request->get_header('X-API-Key');
        if (empty($api_key)) {
            error_log('Hub Plugin: No API key provided in request header');
            return new WP_Error('rest_forbidden', 'Invalid API key', array('status' => 403));
        }

        $keys = get_option('hub_api_keys', array());
        error_log('Hub Plugin: Checking API key against stored keys: ' . print_r($keys, true));

        // Check if the key exists in any domain
        foreach ($keys as $domain => $stored_key) {
            if ($api_key === $stored_key) {
                return true;
            }
        }

        error_log('Hub Plugin: API key not found in stored keys');
        return new WP_Error('rest_forbidden', 'Invalid API key', array('status' => 403));
    }

    /**
     * Handle a push request from a client site to update or create a slot on the hub.
     */
    public static function handle_push_from_client( $request ) {
        // Rate limiting implementation
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $rate_limit_key = 'hub_api_rate_limit_' . $ip_address;
        $rate_limit = get_transient( $rate_limit_key );
        if ( $rate_limit && $rate_limit > 5 ) {
            return new WP_Error( 'rest_rate_limit_exceeded', 'Rate limit exceeded', array( 'status' => 429 ) );
        }
        set_transient( $rate_limit_key, ( $rate_limit ? $rate_limit + 1 : 1 ), 60 );

        $params = $request->get_params();
    
        $uuid         = isset( $params['uuid'] ) ? sanitize_text_field( $params['uuid'] ) : '';
        $title        = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : 'Untitled Slot';
        $content      = isset( $params['content'] ) ? wp_kses_post( $params['content'] ) : '';
        $site_id      = isset( $params['site_id'] ) ? sanitize_text_field( $params['site_id'] ) : 'unknown';
    
        $star_rating  = isset( $params['star_rating'] ) ? floatval( $params['star_rating'] ) : '';
        $provider     = isset( $params['provider_name'] ) ? sanitize_text_field( $params['provider_name'] ) : '';
        $rtp          = isset( $params['rtp'] ) ? floatval( $params['rtp'] ) : '';
        $min_wager    = isset( $params['min_wager'] ) ? floatval( $params['min_wager'] ) : '';
        $max_wager    = isset( $params['max_wager'] ) ? floatval( $params['max_wager'] ) : '';
    
        // Taxonomies (optional)
        // For example, $params['slot_category'] might be an array of category names or IDs
        $slot_category = isset( $params['slot_category'] ) ? (array) $params['slot_category'] : array();
        $slot_owner    = isset( $params['slot_owner'] ) ? (array) $params['slot_owner'] : array();
    
        // Try to find existing slot by UUID if provided
        $existing = !empty( $uuid ) ? self::find_slot_by_uuid( $uuid ) : false;
        $slot_id  = 0;
    
        if ( $existing ) {
            // Update existing post
            $slot_id = wp_update_post( array(
                'ID'           => $existing->ID,
                'post_title'   => $title,
                'post_content' => $content,
            ) );
            error_log( "Updating existing slot {$slot_id} with UUID: {$uuid}" );
        } else {
            // Create new post
            $slot_id = wp_insert_post( array(
                'post_title'   => $title,
                'post_content' => $content,
                'post_type'    => 'slot',
                'post_status'  => 'publish',
            ) );

            if ( is_wp_error( $slot_id ) ) {
                error_log( "Failed to create slot: " . $slot_id->get_error_message() );
                return $slot_id;
            }

            // If UUID was provided, use it
            if ( !empty( $uuid ) && self::is_valid_uuid( $uuid ) ) {
                update_post_meta( $slot_id, 'uuid', $uuid );
                error_log( "Created new slot {$slot_id} with provided UUID: {$uuid}" );
            }
            // If no UUID was provided, one will be automatically generated by the save_post hook
        }

        if ( $slot_id && ! is_wp_error( $slot_id ) ) {
            // Update meta fields
            update_post_meta( $slot_id, 'star_rating', $star_rating );
            update_post_meta( $slot_id, 'provider_name', $provider );
            update_post_meta( $slot_id, 'rtp', $rtp );
            update_post_meta( $slot_id, 'min_wager', $min_wager );
            update_post_meta( $slot_id, 'max_wager', $max_wager );
    
            // Update taxonomies, if provided
            if ( ! empty( $slot_category ) ) {
                wp_set_post_terms( $slot_id, $slot_category, 'slot_category', false );
            }
            if ( ! empty( $slot_owner ) ) {
                wp_set_post_terms( $slot_id, $slot_owner, 'slot_owner', false );
            }
        }
    
        $message = $existing ? "Updated slot with UUID: {$uuid}" : "Created/Updated slot with ID: {$slot_id}";
        Hub_Logger::log( $site_id, 'push_from_client', $message, $params );
    
        return array(
            'success' => true,
            'message' => $message,
            'slot_id' => $slot_id,
            'uuid'    => get_post_meta( $slot_id, 'uuid', true ),
        );
    }

    /**
     * Find a slot by its UUID stored in post meta.
     */
    private static function find_slot_by_uuid( $uuid ) {
        if ( empty( $uuid ) ) {
            return false;
        }
        $args = array(
            'post_type'  => 'slot',
            'meta_key'   => 'uuid',
            'meta_value' => $uuid,
            'post_status'=> 'any',
            'numberposts'=> 1,
        );
        $found = get_posts( $args );
        return ! empty( $found ) ? $found[0] : false;
    }

    /**
     * List or retrieve slots for a client to pull.
     * In real usage, you might accept parameters like 'since' or 'uuid'.
     */
    public static function list_slots_for_client( $request ) {
        // Rate limiting implementation
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $rate_limit_key = 'hub_api_rate_limit_' . $ip_address;
        $rate_limit = get_transient( $rate_limit_key );
        if ( $rate_limit && $rate_limit > 5 ) {
            return new WP_Error( 'rest_rate_limit_exceeded', 'Rate limit exceeded', array( 'status' => 429 ) );
        }
        set_transient( $rate_limit_key, ( $rate_limit ? $rate_limit + 1 : 1 ), 60 );

        $params = $request->get_params();
    
        // Possible query params:
        // ?uuid=some-uuid
        // ?slot_category=category-slug
        // ?slot_owner=owner-slug
        // ?all=1  (means fetch all)
    
        $args = array(
            'post_type'      => 'slot',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        );
    
        // Filter by single UUID?
        if ( ! empty( $params['uuid'] ) ) {
            // find slot by meta
            $slot = self::find_slot_by_uuid( sanitize_text_field( $params['uuid'] ) );
            if ( ! $slot ) {
                return array(
                    'success' => true,
                    'slots'   => array() // no slot found
                );
            }
            // We'll just return that one slot's data
            return array(
                'success' => true,
                'slots'   => array( self::prepare_slot_data( $slot->ID ) ),
            );
        }
    
        // Filter by slot_category?
        if ( ! empty( $params['slot_category'] ) ) {
            $args['tax_query'][] = array(
                'taxonomy' => 'slot_category',
                'field'    => 'slug',
                'terms'    => sanitize_text_field( $params['slot_category'] ),
            );
        }
    
        // Filter by slot_owner?
        if ( ! empty( $params['slot_owner'] ) ) {
            $args['tax_query'][] = array(
                'taxonomy' => 'slot_owner',
                'field'    => 'slug',
                'terms'    => sanitize_text_field( $params['slot_owner'] ),
            );
        }
    
        // If ?all=1 is given, we just ignore filters and pull everything
        if ( isset( $params['all'] ) && $params['all'] == 1 ) {
            // Do nothing, we already set up $args to get all publish
        }
    
        // Query the posts
        $slots_query = get_posts( $args );
        $data = array();
    
        foreach ( $slots_query as $slot ) {
            $data[] = self::prepare_slot_data( $slot->ID );
        }
    
        return array(
            'success' => true,
            'slots'   => $data,
        );
    }
    
    /**
     * Utility to return a slot's data in an array, including meta & taxonomies.
     */
    private static function prepare_slot_data( $slot_id ) {
        $post       = get_post( $slot_id );
        if ( ! $post ) {
            return array();
        }
        $uuid       = get_post_meta( $slot_id, 'uuid', true );
        $star       = get_post_meta( $slot_id, 'star_rating', true );
        $provider   = get_post_meta( $slot_id, 'provider_name', true );
        $rtp        = get_post_meta( $slot_id, 'rtp', true );
        $min_wager  = get_post_meta( $slot_id, 'min_wager', true );
        $max_wager  = get_post_meta( $slot_id, 'max_wager', true );
    
        // Taxonomies
        $slot_cats  = wp_get_post_terms( $slot_id, 'slot_category', array( 'fields' => 'slugs' ) );
        $slot_owners= wp_get_post_terms( $slot_id, 'slot_owner', array( 'fields' => 'slugs' ) );
    
        return array(
            'uuid'         => $uuid,
            'title'        => $post->post_title,
            'content'      => $post->post_content,
            'star_rating'  => $star,
            'provider_name'=> $provider,
            'rtp'          => $rtp,
            'min_wager'    => $min_wager,
            'max_wager'    => $max_wager,
            'slot_category'=> $slot_cats,
            'slot_owner'   => $slot_owners,
            'featured_image_url' => get_the_post_thumbnail_url( $slot_id, 'full' ),
            'last_modified'=> $post->post_modified,
        );
    }
    

    /**
     * Push updates from hub to client (placeholder).
     * In real usage, you need the client site URL and authentication from your plugin settings.
     */
    public static function push_updates_to_client( $request ) {
        // Rate limiting implementation
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $rate_limit_key = 'hub_api_rate_limit_' . $ip_address;
        $rate_limit = get_transient( $rate_limit_key );
        if ( $rate_limit && $rate_limit > 5 ) {
            return new WP_Error( 'rest_rate_limit_exceeded', 'Rate limit exceeded', array( 'status' => 429 ) );
        }
        set_transient( $rate_limit_key, ( $rate_limit ? $rate_limit + 1 : 1 ), 60 );

        $params   = $request->get_params();
        $client_url = isset( $params['client_url'] ) ? esc_url_raw( $params['client_url'] ) : '';

        // This is a placeholder. You would use wp_remote_post or similar 
        // to send data to the client's REST endpoint, e.g. /wp-json/client-plugin/v1/slots/push
        // Then log the response.

        $message = "Attempted to push updates to client: {$client_url} (not actually implemented).";
        Hub_Logger::log( 'HUB', 'push_to_client', $message, $params );

        return array(
            'success' => true,
            'message' => $message,
        );
    }

    // Add this as a new method
    public static function verify_connection($request) {
        $api_key = $request->get_header('X-API-Key');
        $domain = $request->get_param('domain');
        
        // Clean up domain
        $domain = self::clean_domain($domain);
        
        if (empty($api_key) || empty($domain)) {
            error_log("Hub Plugin: Verification failed - Missing API key or domain. Domain: $domain");
            return new WP_Error('invalid_request', 'Missing API key or domain', array('status' => 400));
        }

        $keys = get_option('hub_api_keys', array());
        $is_valid = isset($keys[$domain]) && $keys[$domain] === $api_key;

        error_log("Hub Plugin: Verifying connection for domain: $domain - " . ($is_valid ? 'Valid' : 'Invalid'));
        error_log("Hub Plugin: Available keys: " . print_r($keys, true));

        return new WP_REST_Response(array(
            'valid' => $is_valid,
            'message' => $is_valid ? 'Connection verified' : 'Invalid API key for domain',
            'debug' => [
                'provided_key' => $api_key,
                'domain' => $domain,
                'stored_key' => isset($keys[$domain]) ? $keys[$domain] : 'no key found'
            ]
        ), $is_valid ? 200 : 403);
    }

    // Add this helper method
    private static function clean_domain($domain) {
        // Remove protocol
        $domain = preg_replace('#^https?://#', '', $domain);
        
        // Remove www.
        $domain = preg_replace('/^www\./', '', $domain);
        
        // Remove trailing slashes
        $domain = rtrim($domain, '/');
        
        // Remove any paths
        $domain = strtok($domain, '/');
        
        return trim($domain);
    }
}

function verify_api_key(WP_REST_Request $request) {
    $api_key = $request->get_param('api_key');
    $keys = get_option('hub_api_keys', array());

    $valid = in_array($api_key, $keys);

    return new WP_REST_Response(array('valid' => $valid), 200);
}
