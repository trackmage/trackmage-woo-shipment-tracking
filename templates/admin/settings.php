<?php
/**
 * Plugin settings
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 *
 * @license GPL-2.0+
 */

use TrackMage\WordPress\Utils;

// Fields.
$client_id     = get_option( 'trackmage_client_id', '' );
$client_secret = get_option( 'trackmage_client_secret', '' );
$workspace     = get_option( 'trackmage_workspace', 0 );
$webhook       = get_option( 'trackmage_webhook', '' );
$webhook_id    = get_option( 'trackmage_webhook_id', '' );

$workspaces = Utils::get_workspaces();

settings_errors();
?>
<div class="wrap trackmage">
	<h1><?php _e( 'TrackMage Settings', 'trackmage' ); ?></h1>
	<form method="post" action="options.php" id="trackmage-settings-general">
		<nav class="nav-tab-wrapper trackmage-nav-tab-wrapper">
			<a href="<?php echo admin_url( 'admin.php?page=trackmage&amp;tab=general' ); ?>" class="nav-tab"><?php _e( 'General', 'trackmage' ); ?></a>
			<a href="<?php echo admin_url( 'admin.php?page=trackmage&amp;tab=advanced' ); ?>" class="nav-tab"><?php _e( 'Advanced', 'trackmage' ); ?></a>
		</nav>
		<div class="postbox">
			<div class="inside tab tab-<?php echo $tab; ?>">
			<?php settings_fields( 'trackmage_general' ); ?>
				<!-- Section: Credentials -->
				<div class="section">
					<h2 class="headline"><?php _e( 'Credentials', 'trackmage' ); ?></h2>
					<p class="message"><?php echo sprintf( __( 'If you have not created API keys yet, please <a href="%1$s" target="_blank">login</a> to TrackMage account and generate a new key for this website.', 'trackmage' ), 'https://app.stage.trackmage.com/dashboard/user-profile/api-keys' ); ?></p>
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row"><label for="trackmage_client_id"><?php _e( 'Client ID', 'trackmage' ); ?></label></th>
								<td><input name="trackmage_client_id" type="text" id="trackmage_client_id" value="<?php echo esc_attr( $client_id ); ?>" class="regular-text" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="trackmage_client_secret"><?php _e( 'Client Secret', 'trackmage' ); ?></label></th>
								<td><input name="trackmage_client_secret" type="password" id="trackmage_client_secret" value="<?php echo esc_attr( $client_secret ); ?>" class="regular-text" /></td>
							</tr>
						</tbody>
					</table>
					<div class="test-credentials">
						<input id="testCredentials" type="button" class="button" value="<?php _e( 'Test Credentials', 'trackmage' ); ?>"/>
						<div class="trackmage-notification" data-notification="test-credentials"></div>
					</div>
				</div>
				<!-- End Section: Credentials -->
				<!-- Section: Webhook -->
				<div class="section">
					<h2 class="headline"><?php _e( 'Workspace', 'trackmage' ); ?></h2>
					<p class="message"><?php echo sprintf( __( 'Please select a workspace in TrackMage. You can add/remove webhooks in that workspace.', 'trackmage' ) ); ?></p>
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row"><label for="trackmage_workspace"><?php _e( 'Workspace', 'trackmage' ); ?></label></th>
								<td>
									<select name="trackmage_workspace" id="trackmage_workspace">
										<option value="0"><?php _e( '— Select —', 'trackmage' ); ?></option>
										<?php foreach ( $workspaces as $ws ) : ?>
											<option value="<?php echo $ws['id']; ?>" <?php selected( $ws['id'], $workspace ); ?>><?php echo $ws['title']; ?></option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php echo sprintf( __( 'Select a workspace or <a href="%1$s">create a new one</a> in TrackMage.', 'trackmage'), 'https://app.stage.trackmage.com/dashboard/workspaces' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php _e( 'Add/Remove Webhook', 'trackmage' ); ?></th>
								<td>
									<fieldset class="trackmage-input-toggle" id="toggleWebhook">
										<input name="trackmage_webhook" type="checkbox" id="trackmage_webhook" <?php checked( $webhook, 'on' ); ?>>
										<input name="trackmage_webhook_id" type="hidden" value="<?php echo $webhook_id; ?>">
										<span class="input-toggle"></span>
									</fieldset>
									<div class="trackmage-notification" data-notification="toggle-webhook"></div>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
				<!-- End Section: Webhook -->

				<p class="actions"><?php submit_button( 'Save Changes', 'primary', 'submit', false ); ?></p>
			</div>
		</div>
	</form>
</div>