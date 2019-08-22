<?php

namespace TrackMage\WordPress\Syncrhonization;

use WC_Order;

trait SyncSharedTrait
{
    /**
     * @param WC_Order $order
     * @return bool
     */
    private function canSyncOrder($order)
    {
        $trackmage_order_id = get_post_meta( $order->get_id(), '_trackmage_order_id', true );
        if (!empty($trackmage_order_id)) { //if linked
            return true;
        }
        $sync_statuses = get_option('trackmage_sync_statuses', []);
        return empty($sync_statuses) || in_array('wc-' . $order->get_status(), $sync_statuses, true);
    }
}