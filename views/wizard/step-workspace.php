<?php
/**
* Trackmage/Wizard/Workspace
*
* @package TrackMage\WordPress
* @author  TrackMage
*/
use TrackMage\WordPress\Helper;
?>
<!-- Step: Workspace -->
<div class="section<?php Helper::add_css_class( ! $credentials, 'disabled', true, true ); ?>">
    <h2 class="headline"><?php _e( 'Workspace', 'trackmage' ); ?></h2>
    <p class="message"><?php echo sprintf( __( 'Please select a workspace in TrackMage.', 'trackmage' ) ); ?></p>
    <table class="form-table">
        <tbody>
        <tr>
            <th scope="row"><label for="trackmage_workspace"><?php _e( 'Workspace', 'trackmage' ); ?></label></th>
            <td>
                <select name="trackmage_workspace" id="trackmage_workspace">
                    <option value="0"><?php _e( '— Select —', 'trackmage' ); ?></option>
                    <?php if($workspaces):?>
                        <?php foreach ( $workspaces as $ws ) : ?>
                            <option value="<?php echo $ws['id']; ?>" <?php selected( $ws['id'], $workspace ); ?>><?php echo $ws['title']; ?></option>
                        <?php endforeach; ?>
                    <?php endif;?>
                </select>
                <p class="description"><?php echo sprintf( __( 'Select a workspace or <a href="%1$s">create a new one</a> in TrackMage.', 'trackmage'), 'https://app.test.trackmage.com/dashboard/workspaces' ); ?></p>
            </td>
        </tr>
        </tbody>
    </table>
</div>
<!-- End Step: Workspace -->
