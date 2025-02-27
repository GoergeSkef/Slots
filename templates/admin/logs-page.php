<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'hubplugin_logs';

// Simple pagination
$per_page = 20;
$page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$offset = ( $page - 1 ) * $per_page;

// Get total count
$total = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
$total_pages = ceil( $total / $per_page );

// Get logs
$logs = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM $table_name ORDER BY id DESC LIMIT %d, %d",
        $offset,
        $per_page
    )
);

?>
<div class="wrap">
    <h1><?php esc_html_e( 'Hub Logs', 'hub-plugin' ); ?></h1>
    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'ID', 'hub-plugin' ); ?></th>
                <th><?php esc_html_e( 'Site ID', 'hub-plugin' ); ?></th>
                <th><?php esc_html_e( 'Event Type', 'hub-plugin' ); ?></th>
                <th><?php esc_html_e( 'Message', 'hub-plugin' ); ?></th>
                <th><?php esc_html_e( 'Payload', 'hub-plugin' ); ?></th>
                <th><?php esc_html_e( 'Date', 'hub-plugin' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if ( ! empty( $logs ) ) : ?>
            <?php foreach ( $logs as $log ) : ?>
            <tr>
                <td><?php echo esc_html( $log->id ); ?></td>
                <td><?php echo esc_html( $log->site_id ); ?></td>
                <td><?php echo esc_html( $log->event_type ); ?></td>
                <td><?php echo esc_html( $log->message ); ?></td>
                <td><pre><?php echo esc_html( $log->payload ); ?></pre></td>
                <td><?php echo esc_html( $log->created_at ); ?></td>
            </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr>
                <td colspan="6"><?php esc_html_e( 'No logs found.', 'hub-plugin' ); ?></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <?php
    // Simple pagination
    if ( $total_pages > 1 ) {
        $base_url = remove_query_arg( 'paged' );
        if ( strpos( $base_url, '?' ) === false ) {
            $base_url .= '?paged=%#%';
        } else {
            $base_url .= '&paged=%#%';
        }

        echo paginate_links( array(
            'base'    => $base_url,
            'format'  => '',
            'current' => $page,
            'total'   => $total_pages,
        ) );
    }
    ?>
</div>
