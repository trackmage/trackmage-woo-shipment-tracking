<?php
namespace TrackMage\WordPress;

class TrackingInfo {
    public function __construct()
    {
        add_action('woocommerce_view_order', [$this, 'addToOrderPage'], 5);
        add_action('woocommerce_thankyou', [$this, 'addToOrderPage'], 5);
        add_action('woocommerce_email_order_details', [$this, 'addToEmail'], 5, 4);
    }

    /**
     * @param int $order_id
     */
    public function addToOrderPage( $order_id ) {
        $order = wc_get_order( $order_id );
        if (null === $link = Helper::getOrderTrackingPageLink($order)) {
            return;
        }
        printf( '<p>Track your order <strong><a href="%s">here</a></strong>.</p>', esc_url( $link, array( 'http', 'https' )));
    }

    /**
     * @param \WC_Order $order
     * @param bool $sent_to_admin
     * @param string $plain_text
     * @param $email
     */
    public function addToEmail( $order, $sent_to_admin, $plain_text, $email ) {
        if ( 'customer_completed_order' === $email->id ) {
            return;
        }
        if (null === $link = Helper::getOrderTrackingPageLink($order)) {
            return;
        }
        printf( '<p>Track your order <strong><a href="%s">here</a></strong>.</p>', esc_url( $link, array( 'http', 'https' )));
    }
}
