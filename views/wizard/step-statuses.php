<?php
/**
 * Trackmage/Wizard/Statuses
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */
use TrackMage\WordPress\Helper;
?>
<!-- Step: Sync With TrackMage -->
<div class="section<?php Helper::add_css_class( ! $credentials && !$workspace, 'disabled', true, true ); ?>">
    <h2 class="headline"><?php _e( 'Sync With TrackMage', 'trackmage' ); ?></h2>
    <p class="message"><?php echo sprintf( __( 'Please select orders statuses to sync with TrackMage.', 'trackmage' ) ); ?></p>
    <table class="form-table">
        <tbody>
        <tr>
            <th scope="row"><label for="trackmage_sync_statuses"><?php _e( 'Statuses', 'trackmage' ); ?></label></th>
            <td>
                <select name="trackmage_sync_statuses[]" id="trackmage_sync_statuses" multiple>
                    <?php foreach ( $sync_statuses as $slug ): ?>
                        <?php if ( isset( $statuses[ $slug ] ) ) : ?>
                            <option value="<?php echo $slug; ?>" selected><?php echo $statuses[ $slug ]['name']; ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php _e( 'Create an order on TrackMage when the status changes to. If none is selected, all new orders will be synced with TrackMage upon creation.', 'trackmage' ); ?></p>
            </td>
        </tr>
        </tbody>
    </table>
</div>
<!-- End Step: Sync With TrackMage -->
