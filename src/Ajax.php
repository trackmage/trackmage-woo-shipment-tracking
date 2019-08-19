<?php
/**
 * Ajax event handlers
 *
 * @class   Ajax
 * @package TrackMage\WordPress
 * @Author  TrackMage
 */

namespace TrackMage\WordPress;

// Called directly? abort.
defined( 'WPINC' ) || exit;

/**
 * Ajax class.
 *
 * All methods in this class should be static.
 *
 * @since 1.0.0
 */
class Ajax {

	/**
	 * Init Ajax class.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		self::add_ajax_events();
	}

	/**
	 * Hook in methods.
	 *
	 * @since 1.0.0
	 */
	public static function add_ajax_events() {
		$ajax_events_nopriv = [
			// nopriv ajax events
		];

		foreach ( $ajax_events_nopriv as $ajax_event ) {
			add_action( 'wp_ajax_trackmage_' . $ajax_event, [ __CLASS__, $ajax_event ] );
			add_action( 'wp_ajax_nopriv_trackmage_' . $ajax_event, [ __CLASS__, $ajax_event ] );
		}

		$ajax_events = [
			'wooselect_order_statuses'
		];

		foreach ( $ajax_events as $ajax_event ) {
			add_action( 'wp_ajax_trackmage_' . $ajax_event, [ __CLASS__, $ajax_event ] );
		}
	}

	/**
	 * wooSelect: get the registered order statuses.
	 *
	 * @since 1.0.0
	 */
	public static function wooselect_order_statuses() {
		$statuses = Utils::get_order_statuses();
		$results = [];

		foreach ( $statuses as $slug => $status ) {
			array_push( $results, [
				'id' => $slug,
				'text' => $status['name'],
			] );
		}

		wp_send_json( $results );
	}
}
