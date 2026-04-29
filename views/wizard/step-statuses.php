<?php
/**
 * Trackmage/Wizard/Statuses
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */
use TrackMage\WordPress\Helper;
$statuses = Helper::getOrderStatuses();
$sync_statuses = (array) get_option( 'trackmage_sync_statuses', [] );
// On first install the option is empty, which would mean "sync everything"
// (including pending-payment drafts and cart abandonments). Pre-select the
// two statuses that real shops actually want — paid (Processing) and
// shipped/finished (Completed) — so the user lands on this step with a
// sensible starting selection rather than no selection.
if ( empty( $sync_statuses ) ) {
    $sync_statuses = [ 'wc-processing', 'wc-completed' ];
}
$trackmage_sync_start_date = get_option( 'trackmage_sync_start_date', '' );
if ( $trackmage_sync_start_date === '' ) {
    // Default the cutoff to today so the first wizard run doesn't accidentally
    // back-import every historical order in the store.
    $trackmage_sync_start_date = date( 'Y-m-d' );
}
?>
<!-- Step: Sync With TrackMage -->
<div class="section<?php Helper::add_css_class( ! $credentials && !$workspace, 'disabled', true, true ); ?>">
    <p class="message"><?php echo sprintf( __( 'Please select orders statuses to sync with TrackMage.', 'trackmage' ) ); ?></p>
    <form id="statuses-form" method="post" class="form-horizontal" action="javascript:void(0);">
        <div class="form-group row">
            <label class="col-12 col-md-3 col-lg-2 col-xl-2 col-form-label text-left text-md-right font-weight-bold text-nowrap" for="trackmage_sync_statuses"><?php _e( 'Statuses', 'trackmage' ); ?></label>
            <div class="col-12 col-md-9 col-lg-10 col-xl-10 pl-md-0">
                <select name="trackmage_sync_statuses[]" id="trackmage_sync_statuses" multiple class="woo-select" aria-describedby="workspaceHelp">
                    <option value=""><?php _e( '— Select —', 'trackmage' ); ?></option>
                    <?php foreach ( $sync_statuses as $slug ): ?>
                        <?php if ( isset( $statuses[ $slug ] ) ) : ?>
                            <option value="<?php echo esc_attr( $slug ); ?>" selected><?php echo esc_attr( $statuses[ $slug ]['name'] ); ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <small id="statusesHelp" class="form-text text-muted"><?php _e( 'Create an order on TrackMage when the status changes to. Defaults to Processing and Completed; clear the field to sync every status (including pending-payment drafts).', 'trackmage' ); ?></small>
            </div>
        </div>
        <div class="form-group row">
            <label for="start-date" class="col-12 col-md-3 col-lg-2 col-xl-2 col-form-label text-left text-md-right font-weight-bold text-nowrap" for="trackmage_sync_statuses"><?php _e( 'Start Date', 'trackmage' ); ?></label>
            <div class="col-12 col-md-9 col-lg-10 col-xl-10 pl-md-0">
                <input type="date"
                       id="trackmage_sync_start_date"
                       name="trackmage_sync_start_date"
                       max="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>"
                       value="<?php echo esc_attr( $trackmage_sync_start_date ); ?>"
                >
                <small id="statusesHelp" class="form-text text-muted"><?php _e( 'Only orders created on or after this date will be synced. Defaults to today; clear it to import every historical order.', 'trackmage' ); ?></small>
            </div>
        </div>
    </form>
</div>
<!-- End Step: Sync With TrackMage -->
