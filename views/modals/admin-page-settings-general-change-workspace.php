<?php
/**
 * Created by Yevhenii Harbuzniak
 * trackmage-wordpress-plugin
 */
?>

<!-- The modal / dialog box, hidden -->
<div id="change-workspace-dialog" class="hidden">
    <p>Changing a workspace will lead to changes that can not be reverted. If you change your workspace all your currently synced orders, order items and shipments will be unlinked from the TrackMage workspace. The data in TrackMage will not be affected.</p>
    <div class="notice-large">
        <input type="checkbox" class="checkbox checkbox-input required" rel="#agree-change-workspace" id="agree_to_change_cb" value="1"><label for="agree_to_change_cb" class="checkbox-title">Yes, change workspace</label>
        <p class="description hidden">You must check this before change the workspace.</p>
    </div>
    <p>Would you like to DELETE all previously synced orders from TrackMage workspace, order items and shipments in your previous workspace?</p>
    <div class="notice-large">
        <input type="checkbox" class="checkbox checkbox-input" disabled="disabled" rel="#delete-data" id="delete_data_cb" value="1"><label for="delete_data_cb" class="checkbox-title">Delete data</label>
    </div>
    <p>Would you like to trigger synchronization with your new workspace now? If you want to change your statuses settings before this,
        you can do this and then trigger synchronization manually later by pressing the button Trigger sync</p>
    <div class="notice-large">
        <input type="checkbox" class="checkbox checkbox-input" disabled="disabled"  rel="#trigger-sync" id="trigger_sync_cb" value="1"><label for="trigger_sync_cb" class="checkbox-title">Trigger sync</label>
    </div>
</div>
