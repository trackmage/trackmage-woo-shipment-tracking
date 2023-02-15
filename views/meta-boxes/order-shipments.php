<?php
/**
 * Order shipments HTML for meta box
 */

use TrackMage\WordPress\Helper;

defined('WPINC') || exit;

$trackmage_order_id = get_post_meta( $orderId, '_trackmage_order_id', true );
if(!in_array($trackmage_order_id, [null, false, ''])){
    $order = wc_get_order($orderId);
    $orderItems = $order->get_items();
    $shipments = Helper::getOrderShipmentsWithJoinedItems($orderId);
    $carriers = Helper::get_shipment_carriers();
?>
<input type="hidden" value="<?php echo $orderId; ?>" name="trackmage_order_id" />
<div class="shipments">
    <table class="shipments__table" cellpadding="0" cellspacing="0">
        <thead class="shipments__thead">
            <tr>
                <th class="shipments__thead__th shipments__thead__th--shipment"><?php _e('Shipment', 'trackmage'); ?></th>
                <th class="shipments__thead__th shipments__thead__th--stauts"><?php _e('Status', 'trackmage'); ?></th>
                <th class="shipments__thead__th shipments__thead__th--carrier"><?php _e('Carrier', 'trackmage'); ?></th>
                <th class="shipments__thead__th shipments__thead__th--items"><?php _e('Products'); ?></th>
                <th class="shipments__thead__th shipments__thead__th--actions"></th>
            </tr>
        </thead>
        <tbody class="shipments__tbody">
            <?php
            foreach ($shipments as $shipment) {
                include 'order-shipment.php';
            }
            ?>
        </tbody>
    </table>
</div>
<div class="edit-shipment" data-action-group="edit" style="display:none;"></div>
<div class="add-shipment" data-action-group="new" style="display:none;">
    <?php include 'order-add-shipment.php'; ?>
</div>
<div class="actions">
    <div class="actions__action-group actions__action-group--default">
        <div class="left">
            <button class="button button-secondary btn-new"><?php _e('New Shipment', 'trackmage'); ?></button>
        </div>
    </div>
    <div class="actions__action-group actions__action-group--new" style="display:none;">
        <div class="right">
        <button class="button button-secondary btn-cancel" ><?php _e('Cancel', 'trackmage'); ?></button>
        <button class="button button-primary btn-add-shipment"><?php _e('Add Shipment', 'trackmage'); ?></button>
        </div>
    </div>
    <div class="actions__action-group actions__action-group--edit" style="display:none;">
        <div class="right">
            <button class="button button-secondary btn-cancel"><?php _e('Cancel', 'trackmage'); ?></button>
            <button class="button button-primary btn-save"><?php _e('Save', 'trackmage'); ?></button>
        </div>
    </div>
</div>
<div id="delete-shipment-confirm-dialog" class="hidden">
    <p class="description"><?php _e('Note! The deletion/unlinking of shipment cannot be undone.', 'trackmage');?></p>
</div>
<span class="spinner"></span>
<?php }
