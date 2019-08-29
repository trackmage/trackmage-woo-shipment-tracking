<?php
/**
 * Renders a row to add order items to a shimpent.
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */

defined( 'WPINC' ) || exit;
?>
<div class="items__row clear-fix">
    <input type="hidden" name="id" />
    <span class="items__product"><select name="order_item_id" data-placeholder="<?php _e('Search for a product&hellip;', 'trackmage'); ?>"></select></span>
    <span class="items__qty"><input type="number" name="qty" min="1" placeholder="<?php _e('Qty', 'trackmage' ); ?>" /></span>
    <span class="items__delete" style="display:none;"><a class="btn-delete"></a></span>
    <div class="clear-fix"></div>
</div>