<?php
/**
 * Shows a shipment
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */

defined( 'WPINC' ) || exit;

$carrier = array_map(function($c) use (&$shipment) {
    if ($c['code'] === $shipment['carrier']) {
        return $c['name'];
    };
}, $carriers)[0];
?>
<tr class="shipment" data-meta-id="<?php echo $metaId; ?>">
    <td class="shipment__tracking-number">
        <a href=""><?php echo $shipment['tracking_number']; ?></a>
    </td>
    <td class="shipment__status">
        None
    </td>
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