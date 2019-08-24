<?php
/**
 * Settings/Statuses
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

use TrackMage\WordPress\Utils;

$sync_statuses = get_option( 'trackmage_sync_statuses', [] );

// Get the registered statuses.
$statuses = Utils::getOrderStatuses();

// Get the aliases.
$aliases = Utils::get_aliases();
?>
<div class="wrap trackmage">
    <h1><?php _e( 'Status Manager', 'trackmage' ); ?></h1>
    <div class="inside">
        <table class="wp-list-table widefat fixed striped status-manager" id="statusManager">
            <thead>
                <tr>
                    <th><?php _e( 'Name', 'trackmage' ); ?></th>
                    <th><?php _e( 'Slug', 'trackmage' ); ?></th>
                    <th colspan="2"><?php _e( 'Alias in TrackMage', 'trackmage' ); ?></th>
                </tr>
            </thead>
            <tbody id="the-list">
                <?php foreach( $statuses as $slug => $status ) : ?>
                <tr id="status-<?php echo $slug; ?>"
                    data-status-name="<?php echo $status['name']; ?>"
                    data-status-slug="<?php echo $slug; ?>"
                    data-status-alias="<?php echo $status['alias']; ?>"
                    data-status-is-custom="<?php echo $status['is_custom']; ?>">
                    <td>
                        <span data-update-status-name><?php echo $status['name']; ?></span>
                        <div class="row-actions">
                            <span class="inline"><button type="button" class="button-link edit-status"><?php _e( 'Edit', 'trackmage' ); ?></button> | </span>
                            <span class="inline delete"><button type="button" class="button-link delete-status"><?php _e( 'Delete', 'trackmage' ); ?></button></span>
                        </div>
                    </td>
                    <td><span data-update-status-slug><?php echo $slug; ?></span></td>
                    <td colspan="2"><span data-update-status-alias><?php echo isset( $aliases[ $status['alias'] ] ) ? $aliases[ $status['alias'] ] : ''; ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="add-status">
                    <td><input type="text" name="status_name" placeholder="<?php _e( 'Name', 'trackmage' ); ?>" /></td>
                    <td><span class="input-prefix slug-prefix">wc-</span><input type="text" name="status_slug" placeholder="<?php _e( 'Slug', 'trackmage' ); ?>" /></td>
                    <td>
                        <select name="status_alias">
                            <option value=""><?php _e( '— Select —', 'trackmage' ); ?></option>
                            <?php foreach ( $aliases as $id => $name ) : ?>
                                <option value="<?php echo $id; ?>"><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><button type="submit" id="addStatus" class="button button-primary add-status"><?php _e( 'Add New Status', 'trackmage' ); ?></button></td>
                </tr>
            </tfoot>
        </table>

        <form method="post" action="options.php">
            <?php settings_fields( 'trackmage_statuses' ); ?>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="trackmage_sync_statuses"><?php _e( 'Sync with TrackMage', 'trackmage' ); ?></label></th>
                        <td>
                            <select name="trackmage_sync_statuses[]" id="trackmage_sync_statuses" multiple>
                                <?php foreach ( $sync_statuses as $slug ): ?>
                                    <?php if ( isset( $statuses[ $slug ] ) ) : ?>
                                        <option value="<?php echo $slug; ?>" selected><?php echo $statuses[ $slug ]['name']; ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e( 'Create an order on TrackMage when the status changes to. If none is selected, all new orders will be synced with TrackMage upon creation.', 'trackmage' ); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p class="actions"><?php submit_button( 'Save Changes', 'primary', 'submit', false ); ?></p>
        </form>
    </div>
</div>
