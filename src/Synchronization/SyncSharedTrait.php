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
        // If the plugin isn't configured (e.g. during/after Reset, or before
        // wizard finishes), bail out before any network call. Otherwise every
        // background WC task that touches orders would hit /orders with an
        // empty IRI and flood wp_trackmage_log with 400 warnings.
        $workspace = get_option('trackmage_workspace', '');
        if (empty($workspace)) {
            return false;
        }
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
        if ($startDate !== '' && $startDate !== null) {
            // Pin to 00:00:00 of the configured day; date_create_from_format
            // with a date-only mask otherwise inherits the current H:i:s,
            // which made orders created within seconds of the wizard finish
            // fall on the wrong side of the cutoff.
            $dt = date_create_from_format('Y-m-d', $startDate);
            if ($dt !== false) {
                $dt->setTime(0, 0, 0);
                $startDate = $dt->getTimestamp();
            } else {
                $startDate = null;
            }
        } else {
            $startDate = null;
        }
        $orderDate = $order->get_date_created();
        $orderDate = null !== $orderDate ? $orderDate->getTimestamp() : null;

        return $status !== 'draft' && (empty($sync_statuses) || in_array('wc-' . $status, $sync_statuses, true))
            && ($startDate === null || $orderDate === null || $startDate <= $orderDate);
    }
}
