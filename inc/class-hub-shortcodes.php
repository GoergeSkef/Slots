<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if (!file_exists(__DIR__ . '/trait-uuid-handler.php')) {
    error_log('UUID Handler trait file not found');
}
require_once( __DIR__ . '/trait-uuid-handler.php' );

/**
 * Provides shortcodes for displaying Slots in a grid or detail view.
 */
class Hub_Shortcodes {
    use UUID_Handler;

    public static function init() {
        add_shortcode( 'slots_grid', array( __CLASS__, 'slots_grid_shortcode' ) );
        add_shortcode( 'slot_detail', array( __CLASS__, 'slot_detail_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
    }

    /**
     * Enqueue required styles and scripts
     */
    public static function enqueue_assets() {
        wp_enqueue_style(
            'hub-admin-styles',
            plugins_url( '/assets/css/admin-styles.css', dirname( __FILE__ ) ),
            array(),
            filemtime( plugin_dir_path( dirname( __FILE__ ) ) . 'assets/css/admin-styles.css' )
        );

        wp_enqueue_script(
            'slots-filter',
            plugins_url( '/assets/js/slots-filter.js', dirname( __FILE__ ) ),
            array(),
            filemtime( plugin_dir_path( dirname( __FILE__ ) ) . 'assets/js/slots-filter.js' ),
            true
        );
    }

    /**
     * [slots_grid limit="5" sort="recent"]
     * Output a grid of slot posts with filtering options.
     */
    public static function slots_grid_shortcode( $atts ) {
        // Get current filter values from URL parameters
        $current_limit = isset( $_GET['slots_limit'] ) ? absint( $_GET['slots_limit'] ) : 6;
        $current_sort = isset( $_GET['slots_sort'] ) ? sanitize_text_field( $_GET['slots_sort'] ) : 'recent';
        $current_search = isset( $_GET['slots_search'] ) ? sanitize_text_field( $_GET['slots_search'] ) : '';

        // Merge with shortcode attributes
        $atts = shortcode_atts( array(
            'limit' => $current_limit,
            'sort'  => $current_sort,
        ), $atts, 'slots_grid' );

        // Build query
        $args = array(
            'post_type'      => 'slot',
            'posts_per_page' => (int) $atts['limit'],
        );

        // Add search if provided
        if ( ! empty( $current_search ) ) {
            $args['s'] = $current_search;
        }

        // Sorting logic
        switch ( $atts['sort'] ) {
            case 'random':
                $args['orderby'] = 'rand';
                break;
            case 'recent':
                $args['orderby'] = 'modified';
                $args['order'] = 'DESC';
                break;
            case 'min_wager':
                $args['meta_key'] = 'min_wager';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'ASC';
                break;
            case 'max_wager':
                $args['meta_key'] = 'max_wager';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'DESC';
                break;
            case 'rating':
                $args['meta_key'] = 'star_rating';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'DESC';
                break;
            case 'rtp':
                $args['meta_key'] = 'rtp';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'DESC';
                break;
        }

        $query = new WP_Query( $args );
        ob_start();

        // Filter controls
        ?>
        <div class="slots-filter-controls">
            <form method="get" action="">
                <div class="filter-group">
                    <label for="slots_search">Search Slots</label>
                    <input type="text" 
                           id="slots_search" 
                           name="slots_search" 
                           value="<?php echo esc_attr( $current_search ); ?>" 
                           placeholder="Search slots...">
                </div>

                <div class="filter-group">
                    <label for="slots_limit">Display Limit</label>
                    <select name="slots_limit" id="slots_limit">
                        <?php
                        $limit_options = array( 6, 12, 24, 48 );
                        foreach ( $limit_options as $limit ) {
                            printf(
                                '<option value="%1$d" %2$s>%1$d Slots</option>',
                                $limit,
                                selected( $current_limit, $limit, false )
                            );
                        }
                        ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="slots_sort">Sort By</label>
                    <select name="slots_sort" id="slots_sort">
                        <?php
                        $sort_options = array(
                            'recent'    => 'Last Updated',
                            'random'    => 'Random',
                            'min_wager' => 'Minimum Wager (Low to High)',
                            'max_wager' => 'Maximum Wager (High to Low)',
                            'rating'    => 'Highest Rating',
                            'rtp'       => 'Highest RTP',
                        );
                        foreach ( $sort_options as $value => $label ) {
                            printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr( $value ),
                                selected( $current_sort, $value, false ),
                                esc_html( $label )
                            );
                        }
                        ?>
                    </select>
                </div>

                <?php
                // Preserve any other existing query parameters
                foreach ( $_GET as $key => $value ) {
                    if ( ! in_array( $key, array( 'slots_limit', 'slots_sort', 'slots_search' ) ) ) {
                        printf(
                            '<input type="hidden" name="%s" value="%s">',
                            esc_attr( $key ),
                            esc_attr( $value )
                        );
                    }
                }
                ?>
            </form>
        </div>

        <?php if ( $query->have_posts() ): ?>
            <table class="slots-grid-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Name</th>
                        <th>Provider</th>
                        <th>Rating</th>
                        <th>RTP</th>
                        <th>Min Wager</th>
                        <th>Max Wager</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                while ( $query->have_posts() ) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    $star_rating   = get_post_meta( $post_id, 'star_rating', true );
                    $provider_name = get_post_meta( $post_id, 'provider_name', true );
                    $rtp          = get_post_meta( $post_id, 'rtp', true );
                    $min_wager    = get_post_meta( $post_id, 'min_wager', true );
                    $max_wager    = get_post_meta( $post_id, 'max_wager', true );
                    ?>
                    <tr>
                        <td>
                            <?php 
                            if ( has_post_thumbnail() ) {
                                echo get_the_post_thumbnail( $post_id, array( 50, 50 ), array( 'class' => 'slot-thumbnail' ) );
                            }
                            ?>
                        </td>
                        <td><?php the_title(); ?></td>
                        <td><?php echo esc_html( $provider_name ); ?></td>
                        <td><?php echo number_format( (float) $star_rating, 1 ); ?></td>
                        <td><?php echo esc_html( $rtp ); ?>%</td>
                        <td><?php echo esc_html( $min_wager ); ?></td>
                        <td><?php echo esc_html( $max_wager ); ?></td>
                        <td class="slot-actions">
                            <a href="<?php the_permalink(); ?>" class="slot-button read-more">Read More</a>
                            <a href="#" class="slot-button get-started" data-slot-id="<?php echo esc_attr( $post_id ); ?>">Get Started</a>
                        </td>
                    </tr>
                    <?php
                }
                ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No slots found matching your criteria.</p>
        <?php endif;

        wp_reset_postdata();
        return ob_get_clean();
    }

    /**
     * [slot_detail id="123"] or [slot_detail uuid="abcd-xyz"]
     * Output a single slot's details.
     */
    public static function slot_detail_shortcode( $atts ) {
        error_log( 'slot_detail_shortcode called with attributes: ' . print_r( $atts, true ) );
        
        $atts = shortcode_atts( array(
            'id'   => '',
            'uuid' => '',
        ), $atts, 'slot_detail' );

        // We either find the slot by "id" or "uuid".
        $post_id = 0;
        $found = null;

        if ( ! empty( $atts['id'] ) ) {
            $post_id = (int) $atts['id'];
            error_log( "Looking for post by ID: {$post_id}" );
            
            // Verify the post exists and is of the correct type
            $post = get_post( $post_id );
            if ( ! $post || $post->post_type !== 'slot' ) {
                error_log( "Post not found or not a slot: {$post_id}" );
                $post_id = 0;
            }
        } elseif ( ! empty( $atts['uuid'] ) ) {
            error_log( "Looking for UUID: {$atts['uuid']}" );
            
            // Debug the database query
            global $wpdb;
            $uuid = sanitize_text_field( $atts['uuid'] );
            $query = $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'uuid' AND meta_value = %s",
                $uuid
            );
            error_log( "Database query: {$query}" );
            
            $found = self::find_slot_by_uuid( $uuid );
            if ( $found ) {
                $post_id = $found->ID;
                error_log( "Found post ID: {$post_id}" );
            } else {
                error_log( "No post found for UUID: {$uuid}" );
                
                // Additional debugging
                $meta_rows = $wpdb->get_results( $query );
                error_log( "Direct database query results: " . print_r( $meta_rows, true ) );
            }
        }

        if ( ! $post_id ) {
            error_log( "No valid post_id found" );
            return '<p>Slot not found. Please check the provided ID or UUID.</p>';
        }

        // Now display the slot info
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'slot' ) {
            error_log( "Post {$post_id} not found or not a slot post type" );
            return '<p>Slot with ID ' . esc_html( $post_id ) . ' not found.</p>';
        }

        // Gather meta
        $star_rating   = get_post_meta( $post_id, 'star_rating', true );
        $provider_name = get_post_meta( $post_id, 'provider_name', true );
        $rtp          = get_post_meta( $post_id, 'rtp', true );
        $min_wager    = get_post_meta( $post_id, 'min_wager', true );
        $max_wager    = get_post_meta( $post_id, 'max_wager', true );
        $uuid         = get_post_meta( $post_id, 'uuid', true );
        $hero_url     = get_the_post_thumbnail_url( $post_id, 'full' );

        ob_start();
        ?>
        <div class="slot-detail">
            <?php if ( $hero_url ): ?>
            <div class="slot-hero" style="background-image: url('<?php echo esc_url( $hero_url ); ?>'); height: 300px; background-size: cover;">
            </div>
            <?php endif; ?>
            <h2><?php echo esc_html( $post->post_title ); ?></h2>
            <?php if ( $uuid ): ?>
            <p><small>UUID: <?php echo esc_html( $uuid ); ?></small></p>
            <?php endif; ?>
            <p class="slot-star-rating">
                <span class="dashicons dashicons-star-filled"></span>
                <?php echo number_format( (float) $star_rating, 1 ); ?>
            </p>
            <p><strong>Provider:</strong> <?php echo esc_html( $provider_name ); ?></p>
            <p><strong>RTP:</strong> <?php echo esc_html( $rtp ); ?>%</p>
            <p>
                You can wager as little as <?php echo esc_html( $min_wager ); ?> credits 
                and as much as <?php echo esc_html( $max_wager ); ?> credits.
            </p>
            <div class="slot-description">
                <?php echo wp_kses_post( apply_filters( 'the_content', $post->post_content ) ); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Helper to find a slot by UUID (same as in Hub_API, but repeated here for convenience).
     */
    private static function find_slot_by_uuid( $uuid ) {
        if ( empty( $uuid ) ) return false;
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
}


add_action( 'init', array( 'Hub_Shortcodes', 'init' ) );