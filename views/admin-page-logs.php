<?php
/**
 * TrackMage Logs page.
 *
 * Read-only viewer for the wp_trackmage_log table. Lets the shop owner
 * see recent sync activity (and failures) without having to open a
 * database client.
 *
 * @package TrackMage\WordPress
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'You do not have permission to view this page.', 'trackmage' ) );
}

global $wpdb;
$table = $wpdb->prefix . 'trackmage_log';

// Inputs.
$allowed_levels = [ 'all', 'debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency' ];
$level   = isset( $_GET['level'] ) && in_array( $_GET['level'], $allowed_levels, true ) ? $_GET['level'] : 'all';
$paged   = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
$per_page = 25;
$offset  = ( $paged - 1 ) * $per_page;

// "Clear logs" handler.
$cleared = false;
if (
    isset( $_POST['trackmage_logs_action'] )
    && $_POST['trackmage_logs_action'] === 'clear'
    && check_admin_referer( 'trackmage_logs_clear' )
) {
    $wpdb->query( "TRUNCATE TABLE `{$table}`" );
    $cleared = true;
}

// Build WHERE clause.
$where_sql = '';
$where_args = [];
if ( $level !== 'all' ) {
    // Messages start with `[level] ` prefix in Logger::log.
    $where_sql = 'WHERE message LIKE %s';
    $where_args[] = '[' . $level . ']%';
}

$total = (int) $wpdb->get_var(
    $where_sql
        ? $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` {$where_sql}", $where_args )
        : "SELECT COUNT(*) FROM `{$table}`"
);

$rows = $wpdb->get_results(
    $where_sql
        ? $wpdb->prepare(
            "SELECT id, created_at, message, context FROM `{$table}` {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d",
            array_merge( $where_args, [ $per_page, $offset ] )
        )
        : $wpdb->prepare(
            "SELECT id, created_at, message, context FROM `{$table}` ORDER BY id DESC LIMIT %d OFFSET %d",
            [ $per_page, $offset ]
        )
);

$total_pages = max( 1, (int) ceil( $total / $per_page ) );
$base_url = admin_url( 'admin.php?page=trackmage-logs' );

/**
 * Pull severity out of the [level]-prefixed message produced by Logger::log.
 *
 * @param string $msg
 * @return array{level:string,text:string}
 */
$parse_level = static function ( $msg ) {
    if ( preg_match( '/^\[([a-z]+)\]\s*(.*)$/s', (string) $msg, $m ) ) {
        return [ 'level' => strtolower( $m[1] ), 'text' => $m[2] ];
    }
    return [ 'level' => 'info', 'text' => (string) $msg ];
};

$level_class = static function ( $lvl ) {
    if ( in_array( $lvl, [ 'error', 'critical', 'alert', 'emergency' ], true ) ) {
        return 'tm-log-error';
    }
    if ( $lvl === 'warning' ) {
        return 'tm-log-warning';
    }
    if ( $lvl === 'notice' ) {
        return 'tm-log-notice';
    }
    return 'tm-log-info';
};
?>
<style>
    .tm-logs-wrap .tm-log-error    { color: #b32d2e; font-weight: 600; }
    .tm-logs-wrap .tm-log-warning  { color: #b88300; font-weight: 600; }
    .tm-logs-wrap .tm-log-notice   { color: #2271b1; }
    .tm-logs-wrap .tm-log-info     { color: #50575e; }
    .tm-logs-wrap td.tm-context    { font-family: Menlo, Consolas, monospace; font-size: 12px; max-width: 600px; word-break: break-word; }
    .tm-logs-wrap td.tm-context details > summary { cursor: pointer; color: #2271b1; }
    .tm-logs-wrap td.tm-context pre { margin: 6px 0 0; white-space: pre-wrap; }
    .tm-logs-wrap .tm-filters { margin: 10px 0; display: flex; gap: 12px; align-items: center; }
</style>
<div class="wrap tm-logs-wrap">
    <h1><?php _e( 'TrackMage Logs', 'trackmage' ); ?></h1>

    <?php if ( $cleared ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php _e( 'All TrackMage log entries have been cleared.', 'trackmage' ); ?></p></div>
    <?php endif; ?>

    <p class="description">
        <?php _e( 'Recent activity recorded by the plugin. Use this page when sync seems to silently misbehave — info entries show what was attempted, warnings/errors show what failed and why.', 'trackmage' ); ?>
    </p>

    <form method="get" action="" class="tm-filters">
        <input type="hidden" name="page" value="trackmage-logs">
        <label for="trackmage-log-level"><?php _e( 'Level:', 'trackmage' ); ?></label>
        <select name="level" id="trackmage-log-level">
            <?php foreach ( $allowed_levels as $lvl ) : ?>
                <option value="<?php echo esc_attr( $lvl ); ?>" <?php selected( $level, $lvl ); ?>>
                    <?php echo esc_html( ucfirst( $lvl ) ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="button"><?php _e( 'Filter', 'trackmage' ); ?></button>
        <span style="color:#646970;">
            <?php
                printf(
                    /* translators: %1$d: shown rows, %2$d: total rows */
                    esc_html__( 'Showing %1$d of %2$d entries', 'trackmage' ),
                    count( $rows ),
                    $total
                );
            ?>
        </span>
    </form>

    <table class="widefat striped">
        <thead>
            <tr>
                <th style="width:60px;"><?php _e( 'ID', 'trackmage' ); ?></th>
                <th style="width:160px;"><?php _e( 'When', 'trackmage' ); ?></th>
                <th style="width:90px;"><?php _e( 'Level', 'trackmage' ); ?></th>
                <th><?php _e( 'Message', 'trackmage' ); ?></th>
                <th><?php _e( 'Context', 'trackmage' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $rows ) ) : ?>
                <tr>
                    <td colspan="5" style="text-align:center; padding:24px; color:#646970;">
                        <?php _e( 'No log entries match the current filter.', 'trackmage' ); ?>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ( $rows as $row ) :
                    $parsed = $parse_level( $row->message );
                    $cls    = $level_class( $parsed['level'] );
                    $ctx    = (string) $row->context;
                    $ctx_pretty = '';
                    if ( $ctx !== '' ) {
                        $decoded = json_decode( $ctx, true );
                        $ctx_pretty = $decoded === null ? $ctx : wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
                    }
                ?>
                    <tr>
                        <td><?php echo (int) $row->id; ?></td>
                        <td><?php echo esc_html( $row->created_at ); ?></td>
                        <td class="<?php echo esc_attr( $cls ); ?>"><?php echo esc_html( $parsed['level'] ); ?></td>
                        <td><?php echo esc_html( $parsed['text'] ); ?></td>
                        <td class="tm-context">
                            <?php if ( $ctx_pretty === '' ) : ?>
                                <span style="color:#a7aaad;">—</span>
                            <?php else : ?>
                                <details>
                                    <summary><?php echo esc_html( mb_substr( str_replace( "\n", ' ', $ctx_pretty ), 0, 80 ) ) . ( strlen( $ctx_pretty ) > 80 ? '…' : '' ); ?></summary>
                                    <pre><?php echo esc_html( $ctx_pretty ); ?></pre>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ( $total_pages > 1 ) :
        $level_qs = $level !== 'all' ? '&level=' . urlencode( $level ) : '';
    ?>
        <div class="tablenav" style="margin-top:12px;">
            <div class="tablenav-pages">
                <?php
                echo paginate_links( [
                    'base'      => $base_url . $level_qs . '&paged=%#%',
                    'format'    => '',
                    'current'   => $paged,
                    'total'     => $total_pages,
                    'prev_text' => '« ' . __( 'Previous', 'trackmage' ),
                    'next_text' => __( 'Next', 'trackmage' ) . ' »',
                ] );
                ?>
            </div>
        </div>
    <?php endif; ?>

    <form method="post" action="" style="margin-top:24px;" onsubmit="return confirm('<?php echo esc_js( __( 'Clear ALL TrackMage log entries? This only deletes log rows, not orders or settings.', 'trackmage' ) ); ?>');">
        <?php wp_nonce_field( 'trackmage_logs_clear' ); ?>
        <input type="hidden" name="trackmage_logs_action" value="clear">
        <button type="submit" class="button button-secondary"><?php _e( 'Clear all log entries', 'trackmage' ); ?></button>
    </form>
</div>
