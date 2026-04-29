<?php
/**
 * Delete TrackMage Data — destructive path with type-to-confirm.
 *
 * The dialog requires the user to type the workspace title before the
 * Delete button enables. After confirmation the form is submitted with
 * trackmage_delete_data=1 (same backend as before).
 */
use TrackMage\WordPress\Helper;
$_dd_workspace_id = get_option( "trackmage_workspace", "" );
$_dd_workspaces   = Helper::get_workspaces();
$_dd_workspace_title = "";
if ( is_array( $_dd_workspaces ) ) {
    foreach ( $_dd_workspaces as $_dd_w ) {
        if ( isset( $_dd_w["id"] ) && $_dd_w["id"] === $_dd_workspace_id ) {
            $_dd_workspace_title = isset( $_dd_w["title"] ) ? $_dd_w["title"] : "";
            break;
        }
    }
}
?>
<div id="delete-tm-data-dialog" class="hidden">
    <p><strong><?php _e( "This is a destructive action.", "trackmage" ); ?></strong></p>
    <p><?php
        printf(
            __( "Every order, order item and shipment that this site has synced to TrackMage workspace <strong>%s</strong> will be permanently deleted from TrackMage. After the deletion this site will also be disconnected (the same as the <em>Disconnect Plugin</em> button).", "trackmage" ),
            esc_html( $_dd_workspace_title !== "" ? $_dd_workspace_title : __( "(unknown)", "trackmage" ) )
        );
    ?></p>
    <p><?php _e( "Orders that were imported into the workspace by other integrations are not affected.", "trackmage" ); ?></p>
    <p><strong><?php _e( "This cannot be undone.", "trackmage" ); ?></strong></p>
    <div class="notice-large">
        <label for="delete-tm-data-confirm" class="font-weight-bold">
            <?php
                printf(
                    __( "To confirm, type the workspace name <code>%s</code> below:", "trackmage" ),
                    esc_html( $_dd_workspace_title !== "" ? $_dd_workspace_title : "—" )
                );
            ?>
        </label>
        <input type="text"
               id="delete-tm-data-confirm"
               class="regular-text"
               autocomplete="off"
               data-expected="<?php echo esc_attr( $_dd_workspace_title ); ?>"
               style="display:block;margin-top:6px;width:100%;">
    </div>
</div>
