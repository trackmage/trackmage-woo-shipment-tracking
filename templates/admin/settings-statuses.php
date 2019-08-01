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

// Get the registered statuses.
$statuses = wc_get_order_statuses();
?>

<div class="intro"><?php _e( 'You can edit and add new order statuses.', 'trackmage' ); ?></div>

<table class="widefat status-manager" id="statusManager">
	<thead>
		<tr>
		<th><?php _e( 'Name', 'trackmage' ); ?></th>
		<th><?php _e( 'Slug', 'trackmage' ); ?></th>
		<th><?php _e( 'Alias in TrackMage', 'trackmage' ); ?></th>
		<th><?php _e( 'Actions', 'trackmage' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach( $statuses as $slug => $name ) : ?>
		<tr>
			<td><?php echo $name; ?></td>
			<td><?php echo $slug; ?></td>
			<td></td>
			<td><a href="">Edit</a></td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<form>
	<input type="submit" class="button button-primary" value="<?php _e( 'Add New Status', 'trackmage' ); ?>" />
</form>