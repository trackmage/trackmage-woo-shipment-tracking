<?php
/**
* Trackmage/Wizard/Workspace
*
* @package TrackMage\WordPress
* @author  TrackMage
*/
use TrackMage\WordPress\Helper;
$workspace     = get_option( 'trackmage_workspace', 0 );

$workspaces = Helper::get_workspaces();
?>
<!-- Step: Workspace -->
<div class="section<?php Helper::add_css_class( ! $credentials, 'disabled', true, true ); ?>">
    <p class="message"><?php echo sprintf( __( 'Please select a workspace in TrackMage.', 'trackmage' ) ); ?></p>
    <form id="workspace-form" method="post" class="form-horizontal" action="javascript:void(0);">
        <div class="form-group row">
            <label class="col-12 col-md-3 col-lg-2 col-xl-2 col-form-label text-left text-md-right font-weight-bold text-nowrap" for="trackmage_workspace"><?php _e( 'Workspace', 'trackmage' ); ?></label>
            <div class="col-12 col-md-9 col-lg-10 col-xl-10 pl-md-0">
                <select name="trackmage_workspace" id="trackmage_workspace" class="required form-control custom-select" required aria-describedby="workspaceHelp">
                    <option value=""><?php _e( '— Select —', 'trackmage' ); ?></option>
                    <?php if($workspaces):?>
                        <?php foreach ( $workspaces as $ws ) : ?>
                            <option value="<?php echo $ws['id']; ?>" <?php selected( $ws['id'], $workspace ); ?>><?php echo $ws['title']; ?></option>
                        <?php endforeach; ?>
                    <?php endif;?>
                </select>
                <small id="workspaceHelp" class="form-text text-muted"><?php echo sprintf( __( 'Select a workspace or <a target="_blank" href="%1$s">create a new one</a> in TrackMage.', 'trackmage'), TRACKMAGE_APP_DOMAIN.'/dashboard/workspaces' ); ?></small>
            </div>
        </div>
    </form>
</div>
<!-- End Step: Workspace -->
