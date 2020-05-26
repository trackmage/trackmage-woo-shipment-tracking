<?php
/**
 * Order shipments HTML for meta box
 */

use TrackMage\WordPress\Helper;

defined('WPINC') || exit;

$order = wc_get_order($orderId);
if($order->get_status() !== 'auto-draft'){
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
<span class="spinner"></span>
<?php } else { ?>
<div class="shipments">
    <p style="margin-left: 12px;"><strong><?php _e('You cannot add shipments to draft orders.', 'trackmage');?></strong></p>
</div>
<?php } ?>
