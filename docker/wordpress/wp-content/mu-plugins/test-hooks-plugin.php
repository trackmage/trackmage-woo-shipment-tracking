<?php
/*
  Plugin Name: Test Hooks
  Description: Hooks that help testing
*/

add_filter( 'woocommerce_prevent_automatic_wizard_redirect', 'wc_subscriber_auto_redirect', 20, 1 );
function wc_subscriber_auto_redirect( $boolean ) {
    return true;
}
