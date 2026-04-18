<?php

namespace TrackMage\WordPress\Synchronization;

use WC_Order;

trait SyncSharedTrait
{
    /**
     * @param WC_Order $order
     * @return bool
     */
    private function canSyncOrder($order)
    {
        // Refresh meta to pick up external post_meta writes that older WC
        // versions do not invalidate on the cached WC_Order instance.
        $order->read_meta_data(true);
        $trackmage_order_id = $order->get_meta('_trackmage_order_id', true);
        if (!empty($trackmage_order_id)) { //if linked
            return true;
        }
        $sync_statuses = get_option('trackmage_sync_statuses', []);
        $status = $order->get_status();
        $startDate = get_option('trackmage_sync_start_date', null);
        $startDate = $startDate !== '' && $startDate !== null ? date_create_from_format('Y-m-d', $startDate)->getTimestamp() : null;
        $orderDate = $order->get_date_created();
        $orderDate = null !== $orderDate ? $orderDate->getTimestamp() : null;

        return $status !== 'draft' && (empty($sync_statuses) || in_array('wc-' . $status, $sync_statuses, true))
            && ($startDate === null || $orderDate === null || $startDate < $orderDate);
    }
}
