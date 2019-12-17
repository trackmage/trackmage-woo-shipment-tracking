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
?>
<!-- Step: Sync With TrackMage -->
<div class="section<?php Helper::add_css_class( ! $credentials && !$workspace, 'disabled', true, true ); ?>">
    <p class="message"><?php echo sprintf( __( 'Please select orders statuses to sync with TrackMage.', 'trackmage' ) ); ?></p>
    <form id="statuses-form" method="post" class="form-horizontal" action="javascript:void(0);">
        <div class="form-group row">
            <label class="col-sm-2 col-form-label text-right font-weight-bold" for="trackmage_sync_statuses"><?php _e( 'Statuses', 'trackmage' ); ?></label>
            <div class="col-sm-10">
                <select name="trackmage_sync_statuses[]" id="trackmage_sync_statuses" multiple class="woo-select" aria-describedby="workspaceHelp">
                    <option value=""><?php _e( '— Select —', 'trackmage' ); ?></option>
                    <?php if($sync_statuses):?>
                        <?php foreach ( $sync_statuses as $slug ): ?>
                            <?php if ( isset( $statuses[ $slug ] ) ) : ?>
                                <option value="<?php echo $slug; ?>" selected><?php echo $statuses[ $slug ]['name']; ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif;?>
                </select>
                <small id="statusesHelp" class="form-text text-muted"><?php _e( 'Create an order on TrackMage when the status changes to. If none is selected, all new orders will be synced with TrackMage upon creation.', 'trackmage' ); ?></small>
            </div>
        </div>
    </form>
</div>
<!-- End Step: Sync With TrackMage -->
