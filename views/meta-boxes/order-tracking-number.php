<?php
/**
 * Shows a tracking number
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
?>
<tr class="tracking-number">
	<td class="tracking-number__tracking-number">
		<a href=""><?php echo $tn['tracking_number']; ?></a>
	</td>
	<td class="tracking-number__status">
		None
	</td>
	<td class="tracking-number__carrier">
		<?php echo $tn['carrier']; ?>
	</td>
	<td class="tracking-number__items">
		<ul>
			<?php foreach( $tn['items'] as $i ): ?>
			<li><a href=""><?php echo $i['order_item_id']; ?></a> &times; <?php echo $i['qty']; ?></li>
			<?php endforeach; ?>
		</ul>
	</td>
	<td class="tracking-number__actions">
		<div class="actions">
			<a class="actions__edit action" href="#"></a>
			<a class="actions__delete action" href="#"></a>
		</div>
	</td>
</tr>