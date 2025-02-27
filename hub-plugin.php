<?php
/**
 * Plugin Name: Hub Plugin (Central)
 * Plugin URI:  https://example.com
 * Description: The central hub plugin that manages global "Slot" data and handles synchronization with client sites.
 * Version:     1.0.0
 * Author:      Your Name
 * Author URI:  https://example.com
 * Text Domain: hub-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define plugin constants
define( 'HUB_PLUGIN_VERSION', '1.0.0' );
define( 'HUB_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'HUB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoload or manually require files
require_once plugin_dir_path( __FILE__ ) . 'inc/class-hub-cpt.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/class-hub-meta.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/class-hub-api.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/class-hub-logger.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/class-hub-admin-menu.php';
require_once plugin_dir_path( __FILE__ ) . 'inc/class-hub-shortcodes.php';


// Activation/Deactivation hooks
register_activation_hook( __FILE__, 'hub_plugin_activate' );
register_deactivation_hook( __FILE__, 'hub_plugin_deactivate' );

/**
 * Plugin activation callback
 */
function hub_plugin_activate() {
    // Create or update the custom logs table in the database
    Hub_Logger::create_logs_table();

    // Flush rewrite rules for the new custom post type
    Hub_CPT::register_slot_cpt();
    flush_rewrite_rules();

    // Generate main hub API key if not exists
    if (!get_option('hub_main_api_key')) {
        update_option('hub_main_api_key', wp_generate_password(64, false));
    }
}

/**
 * Plugin deactivation callback
 */
function hub_plugin_deactivate() {
    // Optional: Clean up or flush rewrites
    flush_rewrite_rules();
}

// Instantiate classes
add_action( 'plugins_loaded', 'hub_plugin_init_classes' );
function hub_plugin_init_classes() {

    // Initialize logger (not strictly necessary if static)
    Hub_Logger::init();

    // Setup admin menu
    Hub_Admin_Menu::init();

    // Register REST API endpoints
    add_action( 'rest_api_init', array( 'Hub_API', 'register_routes' ) );
}


// Register the CPT and taxonomies on init
add_action( 'init', array( 'Hub_CPT', 'register_slot_cpt' ) );
// Register meta boxes
add_action( 'add_meta_boxes', array( 'Hub_Meta', 'register_slot_metabox' ) );
// Save meta on slot save
add_action( 'save_post_slot', array( 'Hub_Meta', 'save_slot_metabox' ) );



function hub_override_single_slot_template( $single ) {
    if ( is_singular('slot') ) {
        $file = plugin_dir_path( __FILE__ ) . 'templates/frontend/single-slot.php';
        if ( file_exists( $file ) ) {
            return $file;
        }
    }
    return $single;
}
add_filter( 'single_template', 'hub_override_single_slot_template' );




// Enqueue admin scripts/styles
add_action( 'admin_enqueue_scripts', 'hub_plugin_enqueue_admin_assets' );
function hub_plugin_enqueue_admin_assets() {
    // Only load on plugin-specific pages or load globally in admin
    wp_enqueue_style( 'hub-admin-styles', HUB_PLUGIN_URL . 'assets/css/admin-styles.css', array(), HUB_PLUGIN_VERSION );
    wp_enqueue_script( 'hub-admin-scripts', HUB_PLUGIN_URL . 'assets/js/admin-scripts.js', array( 'jquery' ), HUB_PLUGIN_VERSION, true );
}

// Add to your main plugin file
add_action('init', function() {
    new Hub_Admin_Menu();
});

// Update the existing rest_api_init action to include all routes
add_action('rest_api_init', function() {
    // Original verify endpoint
    register_rest_route('hub/v1', '/verify', array(
        'methods' => 'POST',
        'callback' => 'hub_verify_api_key',
        'permission_callback' => '__return_true'
    ));
    
    // Categories endpoint
    register_rest_route('hub/v1', '/categories', array(
        'methods' => 'GET',
        'callback' => 'hub_get_categories_endpoint',
        'permission_callback' => '__return_true'
    ));
    
    // Posts endpoint
    register_rest_route('hub/v1', '/posts', array(
        'methods' => 'GET',
        'callback' => 'hub_get_posts_endpoint',
        'permission_callback' => '__return_true'
    ));
    
    // Content endpoints
    register_rest_route('hub/v1', '/get-content', array(
        'methods' => 'GET',
        'callback' => 'hub_get_content_endpoint',
        'permission_callback' => 'hub_verify_api_key_request'
    ));
    
    register_rest_route('hub/v1', '/receive-content', array(
        'methods' => 'POST',
        'callback' => 'hub_receive_content_endpoint',
        'permission_callback' => 'hub_verify_api_key_request'
    ));
});

/**
 * Verify the API key
 */
function hub_verify_api_key($request) {
    $api_key = $request->get_header('X-Hub-API-Key');
    
    error_log("Hub Plugin: Verification attempt with key: " . $api_key);

    if (!$api_key) {
        error_log("Hub Plugin: No API key provided in verification request");
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'No API key provided'
        ), 401);
    }

    // Get the stored API keys
    $api_keys = get_option('hub_api_keys', array());
    error_log("Hub Plugin: Stored API keys: " . print_r($api_keys, true));
    
    // Check if the API key exists
    if (in_array($api_key, array_values($api_keys))) {
        error_log("Hub Plugin: API key verification successful");
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'API key verified successfully'
        ), 200);
    }

    error_log("Hub Plugin: API key not found in stored keys");
    return new WP_REST_Response(array(
        'success' => false,
        'message' => 'Invalid API key'
    ), 401);
}

// Add new endpoint functions
function hub_get_categories_endpoint($request) {
    $categories = get_categories(array('hide_empty' => false));
    $data = array();
    
    foreach ($categories as $category) {
        $data[] = array(
            'id' => $category->term_id,
            'name' => $category->name,
            'slug' => $category->slug
        );
    }
    
    return new WP_REST_Response($data, 200);
}

function hub_get_posts_endpoint($request) {
    $posts = get_posts(array(
        'numberposts' => -1,
        'post_type' => 'post',
        'post_status' => 'publish'
    ));
    
    $data = array();
    
    foreach ($posts as $post) {
        $data[] = array(
            'id' => $post->ID,
            'title' => $post->post_title,
            'slug' => $post->post_name
        );
    }
    
    return new WP_REST_Response($data, 200);
}

// Update the permission callback
function hub_verify_api_key_request() {
    $api_key = $_SERVER['HTTP_X_HUB_API_KEY'] ?? '';
    $api_keys = get_option('hub_api_keys', array());
    
    // Allow hub-to-client communication without explicit connection
    $is_hub = ($api_key === get_option('hub_main_api_key'));
    
    return $is_hub || in_array($api_key, array_values($api_keys));
}

// Add content endpoints
function hub_get_content_endpoint($request) {
    $params = $request->get_params();
    $data = array();
    
    try {
        if ($params['scope'] === 'category' && !empty($params['categories'])) {
            $data['posts'] = hub_get_posts_by_taxonomy($params['categories']);
        } elseif ($params['scope'] === 'specific' && !empty($params['post_ids'])) {
            $data['posts'] = hub_get_specific_posts($params['post_ids']);
        } else {
            $data['posts'] = hub_get_all_posts();
        }
        
        $data['success'] = true;
    } catch (Exception $e) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => $e->getMessage()
        ), 500);
    }
    
    return new WP_REST_Response($data, 200);
}

function hub_receive_content_endpoint($request) {
    $data = $request->get_json_params();
    $results = array();
    
    try {
        if (empty($data['posts'])) {
            throw new Exception('No content received');
        }
        
        foreach ($data['posts'] as $post_data) {
            $results[] = hub_process_incoming_post($post_data);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'processed' => count($results),
            'results' => $results
        ), 200);
    } catch (Exception $e) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => $e->getMessage()
        ), 500);
    }
}

// Add helper functions
function hub_get_posts_by_taxonomy($categories) {
    return get_posts(array(
        'category' => $categories,
        'numberposts' => -1,
        'post_status' => 'publish'
    ));
}

function hub_process_incoming_post($post_data) {
    $existing = get_post($post_data['ID']);
    $post_arr = array(
        'post_title' => $post_data['post_title'],
        'post_content' => $post_data['post_content'],
        'post_status' => 'publish',
        'post_type' => 'post',
        'meta_input' => $post_data['meta'] ?? array()
    );
    
    if ($existing) {
        $post_arr['ID'] = $existing->ID;
        $post_id = wp_update_post($post_arr);
    } else {
        $post_id = wp_insert_post($post_arr);
    }
    
    if (is_wp_error($post_id)) {
        throw new Exception('Failed to process post: ' . $post_data['post_title']);
    }
    
    return $post_id;
}
