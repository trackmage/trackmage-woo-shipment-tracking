<?php
/**
 * Disconnect Plugin (safe path, no TM-side data deletion).
 */
?>
<div id="reset-dialog" class="hidden">
    <p><?php _e( "This will disconnect this site from TrackMage by clearing the API keys, workspace selection and other plugin settings stored in WordPress. Your data on TrackMage (orders, shipments, statuses) is <strong>not</strong> deleted.", "trackmage" ); ?></p>
    <p><?php _e( "If you also want to permanently remove the orders this site has synced to TrackMage, cancel this dialog and use the red <em>Delete TrackMage Data</em> button instead.", "trackmage" ); ?></p>
</div>
