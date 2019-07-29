<?php
/**
 * Utilities and helper functions.
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */

namespace TrackMage\WordPress;

/**
 * Static functions that can be called without instantiation.
 *
 * @since   0.1.0
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */
class Utils {
	public static function get_workspaces() {
		$workspaces = [];

		$client = TrackMage::get_client();
		$result = $client->getWorkspaceApi()->getWorkspaceCollection();

		foreach ( $result as $workspace ) {
			array_push( $workspaces, [
				'id'    => $workspace->getId(),
				'title' => $workspace->getTitle(),
			] );
		}

		return $workspaces;
	}
}