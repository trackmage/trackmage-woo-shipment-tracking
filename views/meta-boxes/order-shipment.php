<?php
/**
 * Shows a shipment
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */

defined( 'WPINC' ) || exit;

$carrierKey = array_search($shipment['originCarrier'], array_column($carriers, 'code'), true);
$carrier = $carrierKey ? $carriers[$carrierKey]['name'] : __('No Info', 'trackmage');
?>
<tr class="shipment" data-id="<?php echo $shipment['id']; ?>">
    <td class="shipment__tracking-number">
        <a href=""><?php echo $shipment['trackingNumber'] !== null ? $shipment['trackingNumber'] : __('No Info', 'trackmage'); ?></a>
    </td>
    <td class="shipment__status"><?php echo $shipment['trackingStatus'] !== null ? ucwords(str_replace('_',' ', $shipment['trackingStatus'])) : __('No Info', 'trackmage')?></td>
    <td class="shipment__carrier">
        <?php echo $carrier; ?>
    </td>
    <td class="shipment__items">
        <ul>
            <?php foreach ($shipment['items'] as $item): ?>
            <li><a href=""><?php echo $orderItems[$item['order_item_id']]->get_name(); ?></a> &times; <?php echo $item['qty']; ?></li>
            <?php endforeach; ?>
        </ul>
    </td>
    <td class="shipment__actions">
        <?php if($shipment['shippedAt'] === null):?>
        <div class="shipment__actions__wrap">
            <a class="shipment__actions__action shipment__actions__action--edit"></a>
            <a class="shipment__actions__action shipment__actions__action--delete"></a>
        </div>
        <?php endif; ?>
    </td>
</tr>
