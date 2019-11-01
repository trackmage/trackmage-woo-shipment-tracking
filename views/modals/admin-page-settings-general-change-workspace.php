<?php
/**
 * Created by Yevhenii Harbuzniak
 * trackmage-wordpress-plugin
 */
?>

<!-- The modal / dialog box, hidden -->
<div id="change-workspace-dialog" class="hidden">
    <p>Changing a workspace will lead to changes that can not be reverted. If you change your workspace all your currently synched orders,
        order items and shipments in TrackMage workspace will be unlinked from your WooCommerce orders, order items and shipments and vice versa.</p>
    <div class="notice-large">
        <input type="checkbox" class="checkbox checkbox-input required" rel="#agree-change-workspace" id="agree_to_change_cb" value="1"><label for="agree_to_change_cb" class="checkbox-title">Yes, change workspace</label>
        <p class="description hidden">You must check this before change the workspace.</p>
    </div>
    <p>Would you like to DELETE all previously synced orders from TrackMage workspace, order items and shipments in your previous workspace?</p>
    <div class="notice-large">
        <input type="checkbox" class="checkbox checkbox-input" rel="#delete-data" id="delete_data_cb" value="1"><label for="delete_data_cb" class="checkbox-title">Delete data</label>
    </div>
    <p>Would you like to trigger synchronization with your new workspace now? If you want to change your statuses settings before this,
        you can do this and then trigger synchronization manually later by pressing the button Trigger sync</p>
    <div class="notice-large">
        <input type="checkbox" class="checkbox checkbox-input" rel="#trigger-sync" id="trigger_sync_cb" value="1"><label for="trigger_sync_cb" class="checkbox-title">Trigger sync</label>
    </div>
</div>

<!-- This script should be enqueued properly in the footer -->
<script type="text/javascript">
    (function ($) {
        // initalise the dialog
        /*
        $(document).ready(function () {
            dialog = $('#change-workspace-dialog').dialog({
                title: 'Changing Workspace Confirmation',
                dialogClass: 'wp-dialog',
                autoOpen: false,
                draggable: false,
                width: $(window).width() > 600 ? 600 : $(window).width(),
                height: 'auto',
                modal: true,
                resizable: false,
                closeOnEscape: true,
                position: {
                    my: "center",
                    at: "center",
                    of: window
                },
                buttons: {
                    "Apply": function() {
                        //$('#trigger-sync').val(1);
                        if(!$('#agree_to_change_cb').is(':checked')) {
                            $('#agree_to_change_cb').parent().addClass('error').find('p.description').show();
                        }else {
                            $(this).dialog("close");
                        }
                    },
                    "Cancel": function() {
                        //$('#trigger-sync').val(0);
                        $( this ).dialog( "close" );
                    }
                },
                open: function () {
                    // close dialog by clicking the overlay behind it
                    $('.ui-widget-overlay').bind('click', function () {
                        $('#change-workspace-dialog').dialog('close');
                    })
                },
                create: function () {
                    // style fix for WordPress admin
                    $('.ui-dialog-titlebar-close').addClass('ui-button');
                },
            });
        });*/
    })(jQuery);
</script>
