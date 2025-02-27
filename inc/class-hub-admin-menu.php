<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Hub_Admin_Menu {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu_pages' ) );
        add_action( 'hub_daily_cron', array( __CLASS__, 'rotate_expired_keys' ) );
        add_action('wp_ajax_hub_verify_connection', array(__CLASS__, 'ajax_verify_connection'));
        add_action('wp_ajax_hub_get_categories', array(__CLASS__, 'ajax_get_categories'));
        add_action('wp_ajax_hub_get_posts', array(__CLASS__, 'ajax_get_posts'));
        add_action('wp_ajax_hub_process_sync', array(__CLASS__, 'ajax_process_sync'));
        add_action('wp_ajax_hub_get_remote_categories', array(__CLASS__, 'ajax_get_remote_categories'));
        add_action('wp_ajax_hub_get_remote_posts', array(__CLASS__, 'ajax_get_remote_posts'));
        add_action('wp_ajax_hub_delete_api_key', array(__CLASS__, 'ajax_delete_api_key'));
        add_action('wp_ajax_hub_force_push', array(__CLASS__, 'ajax_force_push'));
    }

    public static function add_menu_pages() {
        // Top-level menu
        add_menu_page(
            __( 'Hub Plugin', 'hub-plugin' ),
            __( 'Hub Plugin', 'hub-plugin' ),
            'manage_options',
            'hub-plugin',
            array( __CLASS__, 'render_main_page' ),
            'dashicons-networking',
            26
        );

        // Sub-menu: Logs
        add_submenu_page(
            'hub-plugin',
            __( 'Logs', 'hub-plugin' ),
            __( 'Logs', 'hub-plugin' ),
            'manage_options',
            'hub-plugin-logs',
            array( __CLASS__, 'render_logs_page' )
        );

        // Add new submenu for API connections
        add_submenu_page(
            'hub-plugin',
            __('API Connections', 'hub-client'),
            __('API Connections', 'hub-client'),
            'manage_options',
            'hub-connections',
            array(__CLASS__, 'render_connections_page')
        );

        // Add new submenu for sync operations
        add_submenu_page(
            'hub-plugin',
            __('Sync Operations', 'hub-client'),
            __('Sync Operations', 'hub-client'),
            'manage_options',
            'hub-sync',
            array(__CLASS__, 'render_sync_page')
        );
    }

    public static function render_main_page() {
        // Display success/error messages
        if (isset($_GET['status'])) {
            $message_type = $_GET['status'] === 'success' ? 'success' : 'error';
            $message = isset($_GET['message']) ? urldecode($_GET['message']) : '';
            if (!empty($message)) {
                printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', 
                    esc_attr($message_type), 
                    esc_html($message)
                );
            }
        }
        
        $api_keys = get_option('hub_api_keys', array());
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Hub Plugin Dashboard', 'hub-plugin'); ?></h1>
            
            <!-- API Key Management Section -->
            <div class="hub-section">
                <h2><?php esc_html_e('API Key Management', 'hub-plugin'); ?></h2>
                <!-- API Key Generation Form -->
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('generate_api_key_nonce'); ?>
                    <input type="hidden" name="action" value="generate_hub_api_key" />
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="website_domain"><?php esc_html_e('Website Domain', 'hub-plugin'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="website_domain"
                                       name="website_domain" 
                                       class="regular-text"
                                       placeholder="example.com"
                                       required />
                                <p class="description">
                                    <?php esc_html_e('Enter the domain name without http:// or www', 'hub-plugin'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Generate API Key', 'hub-plugin')); ?>
            </form>
            </div>

            <!-- Client Sites Management -->
            <div class="hub-section">
                <h2><?php esc_html_e('Client Sites Management', 'hub-plugin'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Domain', 'hub-plugin'); ?></th>
                            <th><?php esc_html_e('API Key', 'hub-plugin'); ?></th>
                            <th><?php esc_html_e('Status', 'hub-plugin'); ?></th>
                            <th><?php esc_html_e('Actions', 'hub-plugin'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach ($api_keys as $domain => $key): 
                        ?>
                        <tr data-domain="<?php echo esc_attr($domain); ?>">
                            <td><?php echo esc_html($domain); ?></td>
                            <td>
                                <code class="api-key"><?php echo esc_html($key); ?></code>
                                <button class="button verify-connection" data-domain="<?php echo esc_attr($domain); ?>">
                                    <?php esc_html_e('Verify', 'hub-plugin'); ?>
                                </button>
                                <button class="button button-primary push-updates" data-domain="<?php echo esc_attr($domain); ?>">
                                    <?php esc_html_e('Push', 'hub-plugin'); ?>
                                </button>
                                <button class="button pull-updates" data-domain="<?php echo esc_attr($domain); ?>">
                                    <?php esc_html_e('Pull', 'hub-plugin'); ?>
                                </button>
                                <button class="button copy-key" data-key="<?php echo esc_attr($key); ?>">
                                    <?php esc_html_e('Copy', 'hub-plugin'); ?>
                                </button>
                                <button class="button delete-key" data-domain="<?php echo esc_attr($domain); ?>">
                                    <?php esc_html_e('Delete', 'hub-plugin'); ?>
                                </button>
                            </td>
                            <td class="connection-status">
                                <span class="status-unknown"><?php esc_html_e('Unknown', 'hub-plugin'); ?></span>
                            </td>
                        </tr>
                <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Push/Pull Modal -->
            <div id="hub-modal" class="hub-modal" style="display: none;">
                <div class="hub-modal-content">
                    <span class="hub-modal-close">&times;</span>
                    <h2 id="hub-modal-title"></h2>
                    <div id="hub-modal-body">
                        <!-- Content will be loaded dynamically -->
                    </div>
                </div>
            </div>

            <div class="hub-section">
                <h2><?php esc_html_e('Hub Master Key', 'hub-plugin'); ?></h2>
                <div class="hub-master-key">
                    <code><?php echo esc_html(get_option('hub_main_api_key')); ?></code>
                    <button class="button copy-key" data-key="<?php echo esc_attr(get_option('hub_main_api_key')); ?>">
                        <?php esc_html_e('Copy', 'hub-plugin'); ?>
                    </button>
                </div>
                <p class="description"><?php esc_html_e('This key allows pushing content to any client site without individual connections', 'hub-plugin'); ?></p>
            </div>
        </div>

        <script>
        function copyApiKey(key) {
            navigator.clipboard.writeText(key).then(function() {
                alert('API key copied to clipboard!');
            }).catch(function(err) {
                console.error('Failed to copy API key:', err);
            });
        }
        </script>
        <?php
    }

    public static function render_logs_page() {
        require_once HUB_PLUGIN_DIR_PATH . 'templates/admin/logs-page.php';
    }


    public static function render_update_slots_page() {
        ?>
        <div class="wrap">
          <h1><?php esc_html_e( 'Update Slots', 'hub-plugin' ); ?></h1>
          <form method="post">
            <?php wp_nonce_field( 'hub_update_slots_action', 'hub_update_slots_nonce' ); ?>
    
            <p>
              <label><?php esc_html_e( 'Single Slot (Post ID or UUID):', 'hub-plugin' ); ?></label>
              <input type="text" name="single_slot_identifier" />
            </p>
            <p>
              <label><?php esc_html_e( 'Slot Category (slug):', 'hub-plugin' ); ?></label>
              <input type="text" name="slot_category" />
            </p>
            <p>
              <label><?php esc_html_e( 'Slot Owner (slug):', 'hub-plugin' ); ?></label>
              <input type="text" name="slot_owner" />
            </p>
            <p>
              <input type="checkbox" name="update_all" value="1" /> <?php esc_html_e( 'Update All Slots', 'hub-plugin' ); ?>
            </p>
    
            <p>
              <input type="submit" class="button button-primary" name="hub_update_slots_submit" value="<?php esc_attr_e( 'Perform Update', 'hub-plugin' ); ?>" />
            </p>
          </form>
        </div>
        <?php
    }

    public static function maybe_handle_update_slots_form() {
        if ( isset( $_POST['hub_update_slots_submit'] ) ) {
            check_admin_referer( 'hub_update_slots_action', 'hub_update_slots_nonce' );
    
            $single_id_or_uuid = sanitize_text_field( $_POST['single_slot_identifier'] ?? '' );
            $slot_category = sanitize_text_field( $_POST['slot_category'] ?? '' );
            $slot_owner    = sanitize_text_field( $_POST['slot_owner'] ?? '' );
            $update_all    = ! empty( $_POST['update_all'] );
    
            if ( $update_all ) {
                // Call your logic to update ALL slots
                // e.g. loop over all existing slots, push them to the hub or pull from the hub
            } elseif ( $single_id_or_uuid ) {
                // Attempt to interpret it as a post ID or a UUID
                // Then update/push/pull that single slot.
            } elseif ( $slot_category || $slot_owner ) {
                // Update/pull/push all slots in that category or owner
            }
    
            // Possibly add an admin notice with the results
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-success"><p>Slots updated successfully!</p></div>';
            });
        }
    }

    public static function handle_generate_api_key() {
        check_admin_referer('generate_api_key_nonce');
        
        $domain = isset($_POST['website_domain']) ? sanitize_text_field($_POST['website_domain']) : '';
        
        // Clean up domain/URL input
        $domain = self::clean_domain_input($domain);
        
        if (empty($domain)) {
            wp_redirect(add_query_arg(
                array(
                    'status' => 'error',
                    'message' => urlencode('Invalid domain')
                ),
                admin_url('admin.php?page=hub-plugin')
            ));
            exit;
        }

        // Generate new API key
        $api_key = wp_generate_password(64, false);
        
        // Store the key
        $keys = get_option('hub_api_keys', array());
        $keys[$domain] = $api_key;
        update_option('hub_api_keys', $keys);

        error_log("Hub Plugin: Generated new API key for domain: $domain");
        
        wp_redirect(add_query_arg(
            array(
                'status' => 'success',
                'message' => urlencode('API key generated successfully')
            ),
            admin_url('admin.php?page=hub-plugin')
        ));
        exit;
    }

    private static function clean_domain_input($input) {
        // Remove protocols
        $domain = preg_replace('#^https?://#', '', $input);
        
        // Remove www.
        $domain = preg_replace('/^www\./', '', $domain);
        
        // Remove trailing slashes and paths
        $domain = strtok(rtrim($domain, '/'), '/');
        
        // Remove any query strings
        $domain = strtok($domain, '?');
        
        // Validate domain format
        if (!preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)*\.[a-z]{2,}$/i', $domain)) {
            error_log("Hub Plugin: Invalid domain format: $domain");
            return '';
        }
        
        return trim($domain);
    }

    public static function rotate_expired_keys() {
        // Key rotation logic
    }

    public static function ajax_verify_connection() {
        check_ajax_referer('hub_admin_nonce', 'nonce');
        
        $domain = sanitize_text_field($_POST['domain']);
        $original_domain = $domain;
        
        // Clean up domain
        $domain = self::clean_domain_input($domain);
        
        error_log("Hub Plugin: Verifying connection. Original domain: $original_domain, Cleaned domain: $domain");
        
        $keys = get_option('hub_api_keys', array());
        
        if (!isset($keys[$domain])) {
            error_log("Hub Plugin: Domain not found in stored keys. Available keys: " . print_r($keys, true));
            wp_send_json_error("Domain not found in registered sites");
        }

        $api_key = $keys[$domain];
        
        // Try both HTTP and HTTPS
        $verify_url = "https://$domain/wp-json/client-plugin/v1/verify";
        $response = wp_remote_post($verify_url, array(
            'timeout' => 30,
            'headers' => array(
                'X-API-Key' => $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'domain' => parse_url(get_site_url(), PHP_URL_HOST)
            )),
            'sslverify' => false
        ));

        // If HTTPS fails, try HTTP
        if (is_wp_error($response)) {
            error_log("Hub Plugin: HTTPS verification failed, trying HTTP");
            $verify_url = "http://$domain/wp-json/client-plugin/v1/verify";
            $response = wp_remote_post($verify_url, array(
                'timeout' => 30,
                'headers' => array(
                    'X-API-Key' => $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array(
                    'domain' => parse_url(get_site_url(), PHP_URL_HOST)
                )),
                'sslverify' => false
            ));
        }

        if (is_wp_error($response)) {
            error_log("Hub Plugin: Verification request failed - " . $response->get_error_message());
            wp_send_json_error($response->get_error_message());
            return;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log("Hub Plugin: Verification response code: $response_code");
        error_log("Hub Plugin: Verification response body: $body");
        error_log("Hub Plugin: Verification URL used: $verify_url");

        $data = json_decode($body, true);

        if (isset($data['valid']) && $data['valid']) {
            wp_send_json_success('Connection verified');
        } else {
            $error_message = isset($data['message']) ? $data['message'] : 'Connection failed';
            wp_send_json_error($error_message);
        }
    }

    public static function ajax_get_categories() {
        check_ajax_referer('hub_admin_nonce', 'nonce');
        
        $domain = sanitize_text_field($_POST['domain']);
        $api_key = self::get_api_key_for_domain($domain);
        
        if (!$api_key) {
            wp_send_json_error('Invalid domain');
        }

        $response = wp_remote_get("https://$domain/wp-json/client-plugin/v1/categories", array(
            'headers' => array('X-API-Key' => $api_key)
        ));

        if (is_wp_error($response)) {
            error_log('Hub Plugin: Failed to get categories - ' . $response->get_error_message());
            wp_send_json_error($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        wp_send_json_success($data);
    }

    public static function ajax_get_posts() {
        check_ajax_referer('hub_admin_nonce', 'nonce');
        
        $domain = sanitize_text_field($_POST['domain']);
        $api_key = self::get_api_key_for_domain($domain);
        
        if (!$api_key) {
            wp_send_json_error('Invalid domain');
        }

        $response = wp_remote_get("https://$domain/wp-json/client-plugin/v1/posts", array(
            'headers' => array('X-API-Key' => $api_key)
        ));

        if (is_wp_error($response)) {
            error_log('Hub Plugin: Failed to get posts - ' . $response->get_error_message());
            wp_send_json_error($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        wp_send_json_success($data);
    }

    public static function ajax_process_sync() {
        check_ajax_referer('hub_sync_nonce', 'nonce');
        
        $domain = sanitize_text_field($_POST['domain']);
        $action_type = sanitize_text_field($_POST['action_type']);
        $sync_scope = sanitize_text_field($_POST['sync_scope']);
        $categories = isset($_POST['categories']) ? array_map('sanitize_text_field', $_POST['categories']) : array();
        $posts = isset($_POST['posts']) ? array_map('intval', $_POST['posts']) : array();
        
        try {
            $api_key = self::get_api_key_for_domain($domain);
            if (!$api_key) {
                throw new Exception('Invalid domain or API key');
            }

            $payload = array(
                'scope' => $sync_scope,
                'categories' => $categories,
                'post_ids' => $posts,
                'timestamp' => current_time('timestamp')
            );

            if ($action_type === 'push') {
                $result = self::push_content($domain, $api_key, $payload);
            } else {
                $result = self::pull_content($domain, $api_key, $payload);
            }

            Hub_Logger::log_sync_operation(
                $domain,
                $action_type,
                $sync_scope,
                count($result['processed']),
                $result['success'] ? 'success' : 'error'
            );

            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    private static function push_content($domain, $api_key, $payload) {
        $endpoint = "https://$domain/wp-json/hub/v1/receive-content";
        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'X-Hub-API-Key' => $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($payload),
            'timeout' => 30
        ));

        return self::handle_sync_response($response);
    }

    private static function pull_content($domain, $api_key, $payload) {
        $endpoint = "https://$domain/wp-json/hub/v1/get-content";
        $response = wp_remote_get(add_query_arg($payload, $endpoint), array(
            'headers' => array(
                'X-Hub-API-Key' => $api_key
            ),
            'timeout' => 30
        ));

        return self::handle_sync_response($response);
    }

    private static function handle_sync_response($response) {
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data) || !isset($data['success'])) {
            throw new Exception('Invalid response from remote server');
        }

        if (!$data['success']) {
            throw new Exception($data['message'] ?? 'Sync operation failed');
        }

        return $data;
    }

    private static function get_api_key_for_domain($domain) {
        $keys = get_option('hub_api_keys', array());
        return isset($keys[$domain]) ? $keys[$domain] : false;
    }

    public function __construct() {
        add_action('admin_post_generate_hub_api_key', array(__CLASS__, 'handle_generate_api_key'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_scripts'));
    }

    public static function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'hub-plugin') === false) return;

        wp_enqueue_style('hub-sync-styles', HUB_PLUGIN_URL . 'assets/css/sync-styles.css');
        wp_enqueue_script('hub-admin', HUB_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'select2'), '1.0.0', true);
        
        wp_localize_script('hub-admin', 'hubAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hub_admin_nonce')
        ));

        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0-rc.0');
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
    }

    /**
     * Render the API connections page
     */
    public static function render_connections_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Save connection settings if form was submitted
        if (isset($_POST['hub_connection_submit']) && check_admin_referer('hub_connection_nonce')) {
            $remote_url = esc_url_raw($_POST['hub_remote_url']);
            $api_key = sanitize_text_field($_POST['hub_api_key']);
            
            // Save the connection details
            update_option('hub_remote_connection', array(
                'url' => $remote_url,
                'api_key' => $api_key,
                'verified' => false
            ));

            // Attempt to verify the connection
            $verify_response = self::verify_remote_connection($remote_url, $api_key);
            
            if (!is_wp_error($verify_response)) {
                update_option('hub_remote_connection', array(
                    'url' => $remote_url,
                    'api_key' => $api_key,
                    'verified' => true
                ));
                $message = __('Connection verified successfully!', 'hub-client');
                $type = 'success';
            } else {
                $message = $verify_response->get_error_message();
                $type = 'error';
            }
        }

        // Get current connection settings
        $connection = get_option('hub_remote_connection', array(
            'url' => '',
            'api_key' => '',
            'verified' => false
        ));

        // Render the page
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('API Connections', 'hub-client'); ?></h1>
            
            <?php if (isset($message)): ?>
                <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field('hub_connection_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="hub_remote_url"><?php echo esc_html__('Remote Site URL', 'hub-client'); ?></label>
                        </th>
                        <td>
                            <input type="url" name="hub_remote_url" id="hub_remote_url" 
                                value="<?php echo esc_attr($connection['url']); ?>" 
                                class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="hub_api_key"><?php echo esc_html__('API Key', 'hub-client'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="hub_api_key" id="hub_api_key" 
                                value="<?php echo esc_attr($connection['api_key']); ?>" 
                                class="regular-text" required>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="hub_connection_submit" class="button button-primary" 
                        value="<?php echo esc_attr__('Save and Verify Connection', 'hub-client'); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Verify the connection with the remote site
     */
    private static function verify_remote_connection($remote_url, $api_key) {
        $verify_endpoint = trailingslashit($remote_url) . 'wp-json/hub/v1/verify';
        
        error_log("Hub Plugin: Attempting verification with endpoint: " . $verify_endpoint);
        
        $response = wp_remote_post($verify_endpoint, array(
            'headers' => array(
                'X-Hub-API-Key' => $api_key
            ),
            'sslverify' => false,
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            error_log("Hub Plugin: Verification request failed - " . $response->get_error_message());
            return new WP_Error('connection_failed', __('Could not connect to remote site', 'hub-client'));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log("Hub Plugin: Verification response code: $response_code");
        error_log("Hub Plugin: Verification response body: $body");

        $data = json_decode($body);

        if (empty($data) || !isset($data->success)) {
            return new WP_Error('invalid_response', __('Invalid response from remote site', 'hub-client'));
        }

        if (!$data->success) {
            return new WP_Error('verification_failed', $data->message ?? __('API key verification failed', 'hub-client'));
        }

        return true;
    }

    public static function render_sync_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $connections = get_option('hub_remote_connection', array());
        $api_keys = get_option('hub_api_keys', array());
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Sync Operations', 'hub-client'); ?></h1>
            
            <div class="hub-sync-container">
                <form id="hub-sync-form">
                    <div class="hub-form-section">
                        <label for="sync-domain"><?php esc_html_e('Select Domain:', 'hub-client'); ?></label>
                        <select name="domain" id="sync-domain" required>
                            <option value=""><?php esc_html_e('Select a domain', 'hub-client'); ?></option>
                            <?php foreach ($api_keys as $domain => $key): ?>
                                <option value="<?php echo esc_attr($domain); ?>"><?php echo esc_html($domain); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="hub-form-section">
                        <label><?php esc_html_e('Action Type:', 'hub-client'); ?></label>
                        <label><input type="radio" name="action_type" value="push" required> <?php esc_html_e('Push', 'hub-client'); ?></label>
                        <label><input type="radio" name="action_type" value="pull"> <?php esc_html_e('Pull', 'hub-client'); ?></label>
                    </div>

                    <div class="hub-form-section">
                        <label><?php esc_html_e('Sync Scope:', 'hub-client'); ?></label>
                        <select name="sync_scope" id="sync-scope" required>
                            <option value="all"><?php esc_html_e('All Content', 'hub-client'); ?></option>
                            <option value="category"><?php esc_html_e('By Category', 'hub-client'); ?></option>
                            <option value="specific"><?php esc_html_e('Specific Posts', 'hub-client'); ?></option>
                        </select>
                    </div>

                    <div class="hub-form-section" id="category-select" style="display:none;">
                        <label><?php esc_html_e('Select Categories:', 'hub-client'); ?></label>
                        <div class="category-checkboxes">
                            <?php foreach (get_categories(array('hide_empty' => false)) as $category): ?>
                                <label>
                                    <input type="checkbox" name="categories[]" value="<?php echo esc_attr($category->slug); ?>">
                                    <?php echo esc_html($category->name); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="hub-form-section" id="post-select" style="display:none;">
                        <label><?php esc_html_e('Select Posts:', 'hub-client'); ?></label>
                        <select name="posts[]" multiple class="post-select">
                            <?php foreach (get_posts(array('numberposts' => -1)) as $post): ?>
                                <option value="<?php echo esc_attr($post->ID); ?>"><?php echo esc_html($post->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="button button-primary"><?php esc_html_e('Start Sync', 'hub-client'); ?></button>
                </form>
                
                <div id="sync-progress" style="display:none;">
                    <h3><?php esc_html_e('Sync Progress', 'hub-client'); ?></h3>
                    <div class="progress-bar">
                        <div class="progress"></div>
                    </div>
                    <div class="sync-log"></div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function ajax_get_remote_categories() {
        check_ajax_referer('hub_sync_nonce', 'nonce');
        
        $domain = sanitize_text_field($_POST['domain']);
        $api_key = self::get_api_key_for_domain($domain);
        
        if (!$api_key) {
            wp_send_json_error('Invalid domain');
        }

        $response = wp_remote_get("https://$domain/wp-json/hub/v1/categories", array(
            'headers' => array('X-Hub-API-Key' => $api_key),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!empty($data)) {
            wp_send_json_success($data);
        } else {
            wp_send_json_error('No categories found');
        }
    }

    public static function ajax_get_remote_posts() {
        check_ajax_referer('hub_sync_nonce', 'nonce');
        
        $domain = sanitize_text_field($_POST['domain']);
        $api_key = self::get_api_key_for_domain($domain);
        
        if (!$api_key) {
            wp_send_json_error('Invalid domain');
        }

        $response = wp_remote_get("https://$domain/wp-json/hub/v1/posts", array(
            'headers' => array('X-Hub-API-Key' => $api_key),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!empty($data)) {
            wp_send_json_success($data);
        } else {
            wp_send_json_error('No posts found');
        }
    }

    public static function ajax_delete_api_key() {
        check_ajax_referer('hub_admin_nonce', 'nonce');
        
        $domain = sanitize_text_field($_POST['domain']);
        $api_keys = get_option('hub_api_keys', array());
        
        if (isset($api_keys[$domain])) {
            unset($api_keys[$domain]);
            update_option('hub_api_keys', $api_keys);
            wp_send_json_success('API key deleted');
        } else {
            wp_send_json_error('Domain not found');
        }
    }

    public static function ajax_force_push() {
        check_ajax_referer('hub_admin_nonce', 'nonce');
        
        $domain = sanitize_text_field($_POST['domain']);
        $api_key = self::get_api_key_for_domain($domain);
        
        try {
            $posts = get_posts(array('numberposts' => -1));
            $payload = array(
                'posts' => array_map(function($post) {
                    return array(
                        'ID' => $post->ID,
                        'post_title' => $post->post_title,
                        'post_content' => $post->post_content,
                        'meta' => get_post_meta($post->ID)
                    );
                }, $posts)
            );

            $response = wp_remote_post("https://$domain/wp-json/hub/v1/receive-content", array(
                'headers' => array(
                    'X-Hub-API-Key' => $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($payload)
            ));

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            wp_send_json_success('Force push completed');
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}
