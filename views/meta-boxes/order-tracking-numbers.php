<?php
/**
 * Order tracking numbers HTML for meta box
 */

use TrackMage\WordPress\Utils;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

$order = wc_get_order( $order_id );
$order_items = $order->get_items();
$tracking_numbers = get_post_meta( $order_id, '_trackmage_tracking_number', false );
$carriers = Utils::get_shipment_carriers();
?>
<input type="hidden" value="<?php echo $order_id; ?>" name="trackmage_order_id" />
<div class="trackmage-shipment-tracking__tracking-numbers">
	<table cellpadding="0" cellspacing="0">
		<thead>
			<tr>
				<th class="tracking-number"><?php _e( 'Tracking Number', 'trackmage' ); ?></th>
				<th class="status"><?php _e( 'Status', 'trackmage' ); ?></th>
				<th class="carrier"><?php _e( 'Carrier', 'trackmage' ); ?></th>
				<th class="items"><?php _e( 'Products' ); ?></th>
				<th class="actions"></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $tracking_numbers as $tn ) {
				do_action( 'trackmage_before_order_tracking_number_html', $tn, $order );

				include( 'order-tracking-number.php' );

				do_action( 'trackmage_after_order_tracking_number_html', $tn, $order );
			}
			?>
		</tbody>
	</table>
</div>
<div class="trackmage-shipment-tracking__add-tracking-number" style="display:none;">
	<div class="add-tracking-number__col">
		<p class="form-field form-field-wide">
			<label for=""><?php _e( 'Tracking Number', 'trackmage' ); ?></label>
			<input type="text" name="tracking_number" />
		</p>
		<p class="form-field form-field-wide">
			<label for=""><?php _e( 'Carrier', 'trackmage' ); ?></label>
			<select name="carrier" data-placeholder="Select a carrier">
				<?php foreach ( $carriers as $p ) : ?>
				<option value="<?php echo $p['code']; ?>"><?php echo $p['name']; ?></option>
				<?php endforeach; ?>
			</select>
		</p>
	</div>
	<div class="add-tracking-number__col">
		<div class="items">
			<div class="item-row">
				<select name="order_item_id" data-placeholder="Search for a product&hellip;"></select>
				<input type="number" name="qty" min="1" placeholder="<?php _e( 'Qty', 'trackmage' ); ?>" />
			</div>
		</div>
		<button class="button button-secondary" id="add-item-row"><?php _e( 'Add Row', 'trackmage' ); ?></button>
	</div>
</div>
<div class="trackmage-shipment-tracking__actions">
	<div class="actions__default actions-group">
		<div class="left">
			<button class="button button-secondary new"><?php _e( 'New Tracking Number', 'trackmage' ); ?></button>
		</div>
	</div>
	<div class="actions__new actions-group" style="display:none;">
		<div class="right">
		<button class="button button-secondary cancel"><?php _e( 'Cancel', 'trackmage' ); ?></button>
		<button class="button button-secondary add-all"><?php _e( 'Add All Products', 'trackmage' ); ?></button>
			<button class="button button-primary" id="add-tracking-number"><?php _e( 'Add Tracking Number', 'trackmage' ); ?></button>
		</div>
	</div>
	<div class="actions__update actions-group" style="display:none;">
		<div class="right">
			<button class="button button-secondary cancel"><?php _e( 'Cancel', 'trackmage' ); ?></button>
			<button class="button button-primary save"><?php _e( 'Save', 'trackmage' ); ?></button>
		</div>
	</div>
</div>