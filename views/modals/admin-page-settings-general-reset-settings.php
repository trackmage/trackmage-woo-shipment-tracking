<?php
/**
 * Created by Yevhenii Harbuzniak
 * trackmage-woo-shipment-tracking
 */
?>

<!-- The modal / dialog box, hidden -->
<div id="reset-dialog" class="hidden">
    <p><?php _e('During resetting plugin all options will be cleared and all plugin information will be removed (Custom order statuses, shipments and shipment items).', 'trackmage');?></p>
    <div class="notice-large">
        <input type="checkbox" class="checkbox checkbox-input required" rel="#agree-change-workspace" id="agree_reset" value="1"><label for="agree_reset" class="checkbox-title"><?php _e('Yes, reset settings', 'trackmage'); ?></label>
        <p class="description hidden"><?php _e('You have to check this before reset plugin.', 'trackmage')?></p>
    </div>
    <p><?php _e('Would you like to DELETE all previously synced orders from TrackMage workspace, order items and shipments in your current workspace?', 'trackmage'); ?></p>
    <div class="notice-large">
        <input type="checkbox" class="checkbox checkbox-input" rel="#delete-data" id="delete_data_on_reset" value="1"><label for="delete_data_on_reset" class="checkbox-title"><?php _e('Delete data', 'trackmage'); ?></label>
    </div>
</div>
