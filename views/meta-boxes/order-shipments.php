<?php
/**
 * Order shipments HTML for meta box
 */

use TrackMage\WordPress\Helper;

defined('WPINC') || exit;
/** @var int $orderId */
$order = wc_get_order($orderId);
if ($order) {
    $order->read_meta_data(true);
}
$trackmage_order_id = $order ? $order->get_meta('_trackmage_order_id', true) : '';
if($order && !in_array($trackmage_order_id, [null, false, ''], true)){
    $orderItems = $order->get_items();
    $shipments = Helper::getOrderShipmentsWithJoinedItems($orderId);
    $carriers = Helper::get_shipment_carriers();
?>
<input type="hidden" value="<?php echo $orderId; ?>" name="trackmage_order_id" />
<?php
// Tracking page link — generated lazily by Helper::getOrderTrackingPageLink
// the first time this order is rendered. Surface it here so the shop owner
// can copy the URL and send it to the customer without digging through
// post-meta or the database.
$tracking_page_link = Helper::getOrderTrackingPageLink( $order );
if ( ! empty( $tracking_page_link ) ) :
?>
<div class="trackmage-tracking-page" style="margin:8px 0 14px;padding:10px 12px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;">
    <strong style="display:block;margin-bottom:4px;"><?php _e( 'Customer tracking page', 'trackmage' ); ?></strong>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <input type="text"
               readonly
               value="<?php echo esc_attr( $tracking_page_link ); ?>"
               class="trackmage-tracking-page__url"
               style="flex:1 1 280px;min-width:280px;font-family:Menlo,Consolas,monospace;font-size:12px;"
               onclick="this.select();">
        <a href="<?php echo esc_url( $tracking_page_link ); ?>"
           target="_blank"
           rel="noopener noreferrer"
           class="button button-secondary"><?php _e( 'Open', 'trackmage' ); ?></a>
        <button type="button"
                class="button button-secondary trackmage-copy-tracking-page"
                data-url="<?php echo esc_attr( $tracking_page_link ); ?>"><?php _e( 'Copy link', 'trackmage' ); ?></button>
    </div>
    <p class="description" style="margin:6px 0 0;color:#646970;">
        <?php _e( 'Shareable link the customer can open to see this order\'s shipment status.', 'trackmage' ); ?>
    </p>
</div>
<script>
(function(){
    document.querySelectorAll('.trackmage-copy-tracking-page').forEach(function(btn){
        btn.addEventListener('click', function(e){
            e.preventDefault();
            var url = btn.getAttribute('data-url') || '';
            if (!url) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function(){
                    var orig = btn.textContent;
                    btn.textContent = '<?php echo esc_js( __( 'Copied!', 'trackmage' ) ); ?>';
                    setTimeout(function(){ btn.textContent = orig; }, 1500);
                });
            } else {
                var input = btn.parentNode.querySelector('.trackmage-tracking-page__url');
                if (input) { input.select(); document.execCommand('copy'); }
            }
        });
    });
})();
</script>
<?php endif; ?>
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
