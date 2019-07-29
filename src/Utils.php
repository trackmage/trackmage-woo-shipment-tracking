<?php
/**
 * Utilities and helper functions.
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */

namespace TrackMage\WordPress;

use TrackMage\Client\Swagger\ApiException;

/**
 * Static functions that can be called without instantiation.
 *
 * @since   0.1.0
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */
class Utils {
	/**
	 * Returns a list of the workspaces created by the current user.
	 *
	 * @since 0.1.0
	 * @return array Of workspaces, or an empty array if no workspaces found.
	 */
	public static function get_workspaces() {
		$workspaces = [];

		try {
			$client = Plugin::get_client();
			$result = $client->getWorkspaceApi()->getWorkspaceCollection();

			foreach ( $result as $workspace ) {
				array_push( $workspaces, [
					'id'    => $workspace->getId(),
					'title' => $workspace->getTitle(),
				] );
			}
		} catch( ApiException $e ) {
			// Do nothing. We will return an empty array.
		}

		return $workspaces;
	}

	/**
	 * Returns all the sent HTTP hearders.
	 *
	 * @since 0.1.0
	 * @return array Array of headers.
	 */
	public static function getallheaders() {
		$headers = array();

		foreach ( $_SERVER as $name => $value ) { 
			if ( substr( $name, 0, 5 ) == 'HTTP_' ) { 
				$headers[ str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $name, 5 ) ) ) ) ) ] = $value; 
			} 
		}

		return $headers;
	}

	/**
	 * Returns endpoint URL.
	 *
	 * @since 0.1.0
	 * @return string Endpoint URL.
	 */
	public static function get_endpoint() {
		return 'https://trackmage.infinue.com/webhook.php';
		// return get_site_url( null, '/?trackmage=callback' );
	}
}