<?php
/**
 * Created by Yevhenii Harbuzniak
 * trackmage-wordpress-plugin
 */
?>

<!-- The modal / dialog box, hidden -->
<div id="trigger-sync-dialog" class="hidden">
    <p>Please review the order statuses in Sync With TrackMage section.</p>
    <p>You need to specify the statuses that you use for fulfilment.</p>
    <p>Do you want to sync your orders right now?</p>
</div>

<!-- This script should be enqueued properly in the footer -->
<script type="text/javascript">
    (function ($) {
        // initalise the dialog
        /*
        $(document).ready(function () {
            dialog = $('#new-workspace-dialog').dialog({
                title: 'Settings Save Confirmation',
                dialogClass: 'wp-dialog',
                autoOpen: false,
                draggable: false,
                width: 'auto',
                maxWidth: 500,
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
                    "Yes": function() {
                        $('#trigger-sync').val(1);
                        $( this ).dialog( "close" );
                    },
                    "No": function() {
                        $('#trigger-sync').val(0);
                        $( this ).dialog( "close" );
                    }
                },
                open: function () {
                    // close dialog by clicking the overlay behind it
                    $('.ui-widget-overlay').bind('click', function () {
                        $('#new-workspace-dialog').dialog('close');
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
