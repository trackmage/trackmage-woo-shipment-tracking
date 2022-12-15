<?php
/**
 * Shows a shipment
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */

defined( 'WPINC' ) || exit;

$carrierKey = array_search($shipment['originCarrier'], array_column($carriers, 'code'), true);
$carrier = false !== $carrierKey ? esc_attr($carriers[$carrierKey]['name']) : __('No Info', 'trackmage');
?>
<tr class="shipment" data-id="<?php echo $shipment['id']; ?>">
    <td class="shipment__tracking-number">
        <a href="<?php echo \TrackMage\WordPress\Helper::getTrackingPageLink(['id' => $shipment['id']]) ?? '';?>"><?php echo $shipment['trackingNumber'] !== null ? esc_attr($shipment['trackingNumber']) : __('No Info', 'trackmage'); ?></a>
    </td>
    <td class="shipment__status"><?php echo $shipment['trackingStatus'] !== null ? ucwords(str_replace('_',' ', $shipment['trackingStatus'])) : __('No Info', 'trackmage')?></td>
    <td class="shipment__carrier">
        <?php echo $carrier; ?>
    </td>
    <td class="shipment__items">
        <ul>
            <?php foreach ($shipment['items'] as $item): ?>
            <li><a href=""><?php echo esc_attr($orderItems[$item['order_item_id']]->get_name()); ?></a> &times; <?php echo esc_attr($item['qty']); ?></li>
            <?php endforeach; ?>
        </ul>
    </td>
    <td class="shipment__actions">
        <div class="shipment__actions__wrap">
            <?php if($shipment['shippedAt'] === null):?>
                <a class="shipment__actions__action shipment__actions__action--edit button button-secondary"><?php echo __('Edit', 'trackmage')?></a>
            <?php endif; ?>
            <a class="shipment__actions__action shipment__actions__action--delete button button-secondary"><?php echo __('Delete', 'trackmage')?></a>
        </div>
    </td>
</tr>
