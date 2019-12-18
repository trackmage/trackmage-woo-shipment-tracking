<?php
/**
 * Trackmage/Wizard/Credentials
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */

// Fields.
$client_id     = get_option( 'trackmage_client_id', '' );
$client_secret = get_option( 'trackmage_client_secret', '' );

?>
<!-- Step: Credentials -->
<div class="section">
    <p class="message"><?php echo sprintf( __( 'If you have not created API keys yet, please <a href="%1$s" target="_blank">login</a> to TrackMage account and generate a new key for this website.', 'trackmage' ), TRACKMAGE_APP_DOMAIN.'/dashboard/user-profile/api-keys' ); ?></p>
    <form id="credentials-form" method="post" class="form-horizontal" action="javascript:void(0);">
        <div class="form-group row">
            <label class="col-12 col-md-3 col-lg-2 col-xl-2 col-form-label text-left text-md-right font-weight-bold" for="trackmage_client_id"><?php _e( 'Client ID', 'trackmage' ); ?></label>
            <div class="col-12 col-md-9 col-lg-10 col-xl-10 pl-md-0">
                <input name="trackmage_client_id" type="text" id="trackmage_client_id" value="<?php echo esc_attr( $client_id ); ?>" class="regular-text required form-control" required />
            </div>
        </div>
        <div class="form-group row">
            <label class="col-12 col-md-3 col-lg-2 col-xl-2 col-form-label text-left text-md-right font-weight-bold" for="trackmage_client_secret"><?php _e( 'Client Secret', 'trackmage' ); ?></label>
            <div class="col-12 col-md-9 col-lg-10 col-xl-10 pl-md-0">
                <input name="trackmage_client_secret" type="password" id="trackmage_client_secret" value="<?php echo esc_attr( $client_secret ); ?>" class="regular-text required form-control" required />
            </div>
        </div>
    </form>
</div>
<!-- End Step: Credentials -->

