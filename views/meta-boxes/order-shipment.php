<?php
/**
 * Shows a shipment
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */

defined( 'WPINC' ) || exit;

$carrierKey = array_search($shipment['carrier'], array_column($carriers, 'code'));
$carrier = $carriers[$carrierKey]['name'];
?>
<tr class="shipment" data-id="<?php echo $shipment['id']; ?>">
    <td class="shipment__tracking-number">
        <a href=""><?php echo $shipment['tracking_number']; ?></a>
    </td>
    <td class="shipment__status"><?php echo ucwords(str_replace('_',' ', $shipment['status']))?></td>
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
        <div class="shipment__actions__wrap">
            <a class="shipment__actions__action shipment__actions__action--edit"></a>
            <a class="shipment__actions__action shipment__actions__action--delete"></a>
        </div>
    </td>
</tr>
