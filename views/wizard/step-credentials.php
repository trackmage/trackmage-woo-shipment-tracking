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
    <h2 class="headline"><?php _e( 'Credentials', 'trackmage' ); ?></h2>
    <p class="message"><?php echo sprintf( __( 'If you have not created API keys yet, please <a href="%1$s" target="_blank">login</a> to TrackMage account and generate a new key for this website.', 'trackmage' ), 'https://app.test.trackmage.com/dashboard/user-profile/api-keys' ); ?></p>
    <table class="form-table">
        <tbody>
        <tr>
            <th scope="row"><label for="trackmage_client_id"><?php _e( 'Client ID', 'trackmage' ); ?></label></th>
            <td>
                <input <?php echo ($credentials && !empty($workspace))?'disabled="disabled"':'name="trackmage_client_id"';?> type="text" id="trackmage_client_id" value="<?php echo esc_attr( $client_id ); ?>" class="regular-text" />
                <?php if($credentials && !empty($workspace)):?><input type="hidden" name="trackmage_client_id" value="<?php echo esc_attr( $client_id ); ?>"><?php endif;?>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="trackmage_client_secret"><?php _e( 'Client Secret', 'trackmage' ); ?></label></th>
            <td>
                <input <?php echo ($credentials && !empty($workspace))?'disabled="disabled"':'name="trackmage_client_secret"';?> type="password" id="trackmage_client_secret" value="<?php echo esc_attr( $client_secret ); ?>" class="regular-text" />
                <?php if($credentials && !empty($workspace)):?><input type="hidden" name="trackmage_client_secret" value="<?php echo esc_attr( $client_secret ); ?>"><?php endif;?>
            </td>
        </tr>
        </tbody>
    </table>
    <div class="test-credentials">
        <input id="testCredentials" <?php echo ($credentials && !empty($workspace))?'disabled="disabled"':'';?> type="button" class="button" value="<?php _e( 'Test Credentials', 'trackmage' ); ?>"/>
        <span class="spinner"></span>
    </div>
</div>
<!-- End Step: Credentials -->

