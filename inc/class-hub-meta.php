<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Adds a custom meta box for the Slot CPT fields,
 * and saves them on post save.
 */
class Hub_Meta {

    public static function register_slot_metabox() {
        add_meta_box(
            'hub_slot_details',
            __( 'Slot Details', 'hub-plugin' ),
            array( __CLASS__, 'render_slot_metabox' ),
            'slot',
            'normal',
            'default'
        );
    }

    /**
     * Renders the meta box form fields.
     */
    public static function render_slot_metabox( $post ) {
        wp_nonce_field( 'hub_slot_save_data', 'hub_slot_nonce' );

        $star_rating  = get_post_meta( $post->ID, 'star_rating', true );
        $provider     = get_post_meta( $post->ID, 'provider_name', true );
        $rtp          = get_post_meta( $post->ID, 'rtp', true );
        $min_wager    = get_post_meta( $post->ID, 'min_wager', true );
        $max_wager    = get_post_meta( $post->ID, 'max_wager', true );
        ?>
        <table class="form-table">
            <tr>
                <th><label for="hub_star_rating"><?php esc_html_e( 'Star Rating (1-5)', 'hub-plugin' ); ?></label></th>
                <td>
                <input type="number" id="hub_star_rating" name="hub_star_rating" value="<?php echo esc_attr( $star_rating ); ?>" step="0.1" min="0" max="5">
                </td>
            </tr>
            <tr>
                <th><label for="hub_provider_name"><?php esc_html_e( 'Provider Name', 'hub-plugin' ); ?></label></th>
                <td>
                    <input type="text" id="hub_provider_name" name="hub_provider_name" value="<?php echo esc_attr( $provider ); ?>" style="width: 300px;">
                </td>
            </tr>
            <tr>
                <th><label for="hub_rtp"><?php esc_html_e( 'RTP (%)', 'hub-plugin' ); ?></label></th>
                <td>
                    <input type="number" id="hub_rtp" name="hub_rtp" value="<?php echo esc_attr( $rtp ); ?>" step="0.01" min="0" max="100">
                </td>
            </tr>
            <tr>
                <th><label for="hub_min_wager"><?php esc_html_e( 'Minimum Wager', 'hub-plugin' ); ?></label></th>
                <td>
                    <input type="number" id="hub_min_wager" name="hub_min_wager" value="<?php echo esc_attr( $min_wager ); ?>" step="0.01" min="0">
                </td>
            </tr>
            <tr>
                <th><label for="hub_max_wager"><?php esc_html_e( 'Maximum Wager', 'hub-plugin' ); ?></label></th>
                <td>
                    <input type="number" id="hub_max_wager" name="hub_max_wager" value="<?php echo esc_attr( $max_wager ); ?>" step="0.01" min="0">
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save the meta box data when the Slot is saved.
     */
    public static function save_slot_metabox( $post_id ) {
        // Security check
        if ( ! isset( $_POST['hub_slot_nonce'] ) || ! wp_verify_nonce( $_POST['hub_slot_nonce'], 'hub_slot_save_data' ) ) {
            return;
        }
        // Check autosave
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
            return;
        }
        // Check user permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Sanitize and save
        if ( isset( $_POST['hub_star_rating'] ) ) {
            update_post_meta( $post_id, 'star_rating', floatval( $_POST['hub_star_rating'] ) );
        }
        if ( isset( $_POST['hub_provider_name'] ) ) {
            update_post_meta( $post_id, 'provider_name', sanitize_text_field( $_POST['hub_provider_name'] ) );
        }
        if ( isset( $_POST['hub_rtp'] ) ) {
            update_post_meta( $post_id, 'rtp', floatval( $_POST['hub_rtp'] ) );
        }
        if ( isset( $_POST['hub_min_wager'] ) ) {
            update_post_meta( $post_id, 'min_wager', floatval( $_POST['hub_min_wager'] ) );
        }
        if ( isset( $_POST['hub_max_wager'] ) ) {
            update_post_meta( $post_id, 'max_wager', floatval( $_POST['hub_max_wager'] ) );
        }
    }
}
