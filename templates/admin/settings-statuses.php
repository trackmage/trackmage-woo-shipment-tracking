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

use TrackMage\WordPress\Utils as Utils;

// Get the registered statuses.
$statuses = Utils::get_order_statuses();
?>

<div class="intro"><?php _e( 'You can edit and add new order statuses.', 'trackmage' ); ?></div>

<table class="wp-list-table widefat fixed striped status-manager" id="statusManager">
	<thead>
		<tr>
			<th><?php _e( 'Name', 'trackmage' ); ?></th>
			<th><?php _e( 'Slug', 'trackmage' ); ?></th>
			<th colspan="2"><?php _e( 'Aliases in TrackMage', 'trackmage' ); ?></th>
		</tr>
	</thead>
	<tbody id="the-list">
		<?php foreach( $statuses as $status ) : ?>
		<tr id="status-<?php echo $status['slug']; ?>"
			data-status-name="<?php echo $status['name']; ?>"
			data-status-slug="<?php echo $status['slug']; ?>"
			data-status-aliases="<?php echo $status['aliases']; ?>"
			data-status-is-core="<?php echo $status['is_core']; ?>">
			<td>
				<?php echo $status['name']; ?>
				<div class="row-actions">
					<span class="inline"><button type="button" class="button-link edit-status"><?php _e( 'Edit', 'trackmage' ); ?></button> | </span>
					<span class="inline delete"><button type="button" class="button-link delete-status"><?php _e( 'Delete', 'trackmage' ); ?></button></span>
				</div>
			</td>
			<td><?php echo $status['slug']; ?></td>
			<td colspan="2">
				<?php if( preg_replace('/\s+/', '', $status['aliases'] ) ) : ?>
					<?php foreach( explode( ',', $status['aliases'] ) as $alias ) : ?>
						<span class="alias"><?php echo $alias; ?></span>
					<?php endforeach; ?>
				<?php endif; ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
	<tfoot>
		<tr class="add-status">
			<td><input type="text" name="status_name" placeholder="<?php _e( 'Name', 'trackmage' ); ?>" /></td>
			<td><input type="text" name="status_slug" placeholder="<?php _e( 'Slug', 'trackmage' ); ?>" /></td>
			<td><input type="text" name="status_aliases" placeholder="<?php _e( 'Aliases', 'trackmage' ); ?>" /></td>
			<td><button type="submit" id="addStatus" class="button button-primary add-status"><?php _e( 'Add New Status', 'trackmage' ); ?></button></td>
		</tr>
	</tfoot>
</table>