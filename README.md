=== Central Slots Hub Plugin ===
Contributors: George Iskef
Tags: slots, sync, hub, api
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
License: GPLv3 or later

A centralized hub for managing and distributing slot game information across multiple client websites.

== Description ==

The Central Slots Hub Plugin serves as a central repository for slot game information that can be distributed to multiple client websites. It manages API keys, handles synchronization requests, and provides a robust admin interface for managing client connections.

== Core Components ==

1. Hub API (class-hub-api.php)
   - Handles all REST API endpoints
   - Manages API authentication
   - Key Functions:
     * register_routes(): Registers REST API endpoints
     * permission_check(): Validates API requests
     * verify_connection(): Verifies client connections
     * handle_push_from_client(): Processes incoming slot data
     * list_slots_for_client(): Provides slot data to clients
     * push_updates_to_client(): Pushes updates to client sites

2. Hub Admin Menu (class-hub-admin-menu.php)
   - Manages the admin interface
   - Handles API key generation and management
   - Key Functions:
     * render_main_page(): Displays the main admin interface
     * handle_generate_api_key(): Generates new API keys
     * ajax_verify_connection(): Handles connection verification
     * ajax_get_categories(): Retrieves category data
     * ajax_get_posts(): Retrieves post data
     * ajax_perform_sync(): Manages synchronization operations

3. Hub Logger (class-hub-logger.php)
   - Tracks all sync operations and errors
   - Maintains audit trail of client interactions
   - Key Functions:
     * log(): Records events and operations
     * get_logs(): Retrieves logged information
     * create_logs_table(): Sets up logging database

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/central-slots-hub`
2. Activate the plugin
3. Configure the plugin settings:
   - Go to Hub Plugin in the admin menu
   - Generate API keys for client sites
   - Configure sync settings

== Usage ==

1. API Key Management:
   - Generate unique API keys for each client site
   - View and manage existing client connections
   - Monitor connection status

2. Slot Management:
   - Add and edit slot information
   - Organize slots by categories
   - Manage slot metadata (RTP, provider, etc.)

3. Synchronization:
   - Push updates to client sites
   - Accept incoming updates from clients
   - Preview changes before applying

4. Monitoring:
   - View sync history
   - Monitor client connections
   - Track API usage

== API Endpoints ==

1. /wp-json/hub/v1/verify-connection
   - Method: POST
   - Purpose: Verify client API connections
   - Required Headers: X-API-Key

2. /wp-json/hub/v1/slots
   - Method: GET
   - Purpose: List available slots
   - Required Headers: X-API-Key

3. /wp-json/hub-plugin/v1/slots/push
   - Method: POST
   - Purpose: Accept slot updates from clients
   - Required Headers: X-API-Key

== Security ==

The plugin implements several security measures:
- API key authentication
- Domain validation
- Rate limiting
- SSL verification (configurable)
- Input sanitization and validation

== Troubleshooting ==

Common issues and solutions:
1. API Connection Failures
   - Verify API key configuration
   - Check domain settings
   - Confirm SSL settings

2. Sync Issues
   - Check error logs
   - Verify permissions
   - Confirm data format

== Developer Notes ==

1. Hooks and Filters:
   - hub_api_verify_connection
   - hub_sync_before_push
   - hub_sync_after_push
   - hub_log_event

2. Custom Functions:
   - Hub_API::verify_connection()
   - Hub_Admin_Menu::handle_generate_api_key()
   - Hub_Logger::log()
