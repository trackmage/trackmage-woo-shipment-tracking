<?php
/**
 * The Orders class.
 *
 * @package TrackMage\WordPress\Admin
 * @author  TrackMage
 */

namespace TrackMage\WordPress\Admin;

use TrackMage\WordPress\Utils;
use TrackMage\WordPress\Plugin;
use TrackMage\Client\Swagger\ApiException;
use TrackMage\Client\Swagger\Model\TrackingNumberPostTrackingNumberSetTrackingNumberMeta;

/**
 * The Orders class.
 *
 * @since 0.1.0
 */
class Orders {

	/**
	 * The constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box'] );
		add_action( 'save_post', [ $this, 'save_meta_box'] );
		add_filter( 'wc_order_statuses', [ $this, 'order_statuses' ], 999999, 1 );
		add_action( 'wp_ajax_trackmage_order_add_tracking_number', [ $this, 'add_tracking_number' ] );
		add_action( 'wp_ajax_trackmage_order_get_order_items', [ $this, 'get_order_items' ] );
		add_action( 'wp_ajax_trackmage_get_statuses', [ $this, 'get_statuses' ] );
		add_action( 'woocommerce_order_status_changed', [ $this, 'status_changed' ], 10, 3 );
		add_action( 'woocommerce_new_order', [ $this, 'new_order' ], 10, 1 );
		add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'new_order' ], 10, 1 );
		add_filter( 'woocommerce_hidden_order_itemmeta', [ $this, 'hide_order_itemmeta' ], 10, 1 );
	}

	public function hide_order_itemmeta( $fields ) {
		$fields[] = '_trackmage_order_item_id';
		return $fields;
	}

	/**
	 * Add shipment tracking metabox to the order page.
	 *
	 * @since 0.1.0
	 */
	public function add_meta_box() {
		add_meta_box(
			'trackmage-shipment-tracking',
			__( 'TrackMage Shipment Tracking', 'trackmage' ),
			[ $this, 'meta_box_html' ],
			'shop_order',
			'advanced',
			'high'
		);
	}

	/**
	 * Save meta box fields.
	 *
	 * @since 0.1.0
	 * @param [int] $post_id Post ID.
	 */
	public static function save_meta_box( $post_id ) {
		if ( array_key_exists( 'trackmage_carrier', $_POST ) ) {
			update_post_meta(
				$post_id,
				'trackmage_carrier',
				$_POST['trackmage_carrier']
			);
		}

		if ( array_key_exists( 'trackmage_tracking_number', $_POST ) ) {
			update_post_meta(
				$post_id,
				'trackmage_tracking_number',
				$_POST['trackmage_tracking_number']
			);
		}
	}

	/**
	 * Render meta box.
	 *
	 * @todo Move the HTML code to a template file.
	 *
	 * @since 0.1.0
	 * @param [object] $post Post object.
	 */
	public function meta_box_html( $post ) {
		$order_id = $post->ID;
		include TRACKMAGE_VIEWS_DIR . 'meta-boxes/order-tracking-numbers.php';
	}

	/**
	 * Add/rename order statuses.
	 *
	 * @since 0.1.0
	 *
	 * @param array $order_statuses
	 * @return array
	 */
	public function order_statuses( $order_statuses ) {
		$custom_statuses = get_option( 'trackmage_custom_order_statuses', [] );
		$modified_statuses = get_option( 'trackmage_modified_order_statuses', [] );

		// Register custom order statuses added by our plugin.
		$order_statuses = array_merge( $order_statuses, $custom_statuses );

		// Update the registered statuses.
		foreach ( $order_statuses as $key => $name ) {
			if ( array_key_exists( $key, $modified_statuses ) ) {
				$order_statuses[ $key ] = __( $modified_statuses[ $key ], 'trackmage' );
			}
		}

		return $order_statuses;
	}

	/**
	 * Ajax - add tracking number.
	 *
	 * @since 0.1.0
	 * @throws Exception To return errors.
	 */
	public function add_tracking_number() {
		check_ajax_referer( 'add-tracking-number', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( -1 );
		}

		// Request data.
		$order_id        = $_POST['order_id'];
		$tracking_number = $_POST['tracking_number'];
		$carrier         = $_POST['carrier'];
		$items           = $_POST['items'];

		// Order data.
		$order                      = wc_get_order( $order_id );
		$order_items                = $order->get_items();
		$_trackmage_order_id        = get_post_meta( $order_id, '_trackmage_order_id', true );
		$_trackmage_tracking_number = get_post_meta( $order_id, '_trackmage_tracking_number', false );

		try {
			// Check tracking number.
			if ( empty( $tracking_number ) ) {
				throw new \Exception( __( 'Tracking number cannot be left empty.', 'trackmage' ) );
			}

			// Check carrier.
			if ( empty( $carrier ) ) {
				throw new \Exception( __( 'Carrier cannot be left empty.', 'trackmage' ) );
			}

			// Check if no items added.
			if ( ! is_array( $items ) || empty( $items ) ) {
				throw new \Exception( __( 'No items added.', 'trackmage' ) );
			}

			foreach ( $items as $item ) {
				// Check if any of the selected items no longer exists.
				if ( ! array_key_exists( $item['order_item_id'], $order_items ) ) {
					throw new \Exception( __( 'Order item does not exist.', 'trackmage' ) );
				}
			}

			foreach ( $items as $item ) {
				// Check if any of the items has non-positive quantities.
				if ( 0 >= $item['qty'] ) {
					throw new \Exception( __( 'Item quantity must be a positive integer.', 'trackmage' ) );
				}

				$total_qty = $order_items[ $item['order_item_id'] ]->get_quantity();
				$used_qty = 0;
				foreach ( $_trackmage_tracking_number as $tn ) {
					foreach ( $tn['items'] as $tn_item ) {
						if ( $item['order_item_id'] === $tn_item['order_item_id'] ) {
							$used_qty += (int) $tn_item['qty'];
						}
					}
				}
				$avail_qty = $total_qty - $used_qty;

				// Check the available quantities of each item.
				if ( $avail_qty < $item['qty'] ) {
					throw new \Exception( __( 'No available quantity.', 'trackmage' ) );
				}
			}

			// Send request to TrackMage.
			$workspace = get_option( 'trackmage_workspace' );
			$client = Plugin::get_client();

			$response = $client->getGuzzleClient()->post(
				'/tracking_numbers', [
					'json' => [
						'workspace' => '/workspaces/' . $workspace,
						'trackingNumber' => (string) $tracking_number,
						'orders' => [ '/orders/' . 'b14ac2a9-6deb-4fb9-97f0-59229eb872dc' ],
						'originCarrier' => $carrier,
					]
				]
			);

			if ( 201 === $response->getStatusCode() ) {
				$result = json_decode( $response->getBody()->getContents(), true );
				$trackmage_tracking_number_id = $result['id'];

				// Add tracking number items.
				foreach ( $items as $item ) {
					$response = $client->getGuzzleClient()->post(
						'/tracking_number_items', [
							'json' => [
								'workspace' => '/workspaces/' . $workspace,
								'trackingNumber' => '/tracking_numbers/' . $trackmage_tracking_number_id,
								'orderItem' => '/order_items/' . wc_get_order_item_meta( $item['order_item_id'], '_trackmage_order_item_id' ),
								'qty' => (int) $item['qty'],
							]
						]
					);

					if ( 201 === $response->getStatusCode() ) {
						$result = json_decode( $response->getBody()->getContents(), true );
						$trackmage_tracking_number_item_id = $result['id'];

						// Add the ID of TrackingNumberItem to the items array.
						$key = array_search( $trackmage_tracking_number_item_id, array_column( $items, 'order_item_id' ) );
						$items[ $key ]['id'] = $trackmage_tracking_number_item_id;
					} else {
						// Could not add an item to the tracking number.
						// Delete tracking number from TrackMage, then throw error.
						$client->getGuzzleClient()->delete( '/tracking_numbers/' . $trackmage_tracking_number_id );

						$result = json_decode( $response->getBody()->getContents(), true );
						$message = isset( $result['hydra:description'] ) ? $result['hydra:description'] : __( 'Could not add tracking number item.', 'trackmage' ); 
						throw new \Exception( $message );
					}
				}

				// Insert tracking number details into the database.
				add_post_meta(
					$order_id,
					'_trackmage_tracking_number', 
					[
						'id' => $trackmage_tracking_number_id,
						'tracking_number' => $tracking_number,
						'carrier' => $carrier,
						'items' => $items,
					],
					false
				);
			} else {
				$result = json_decode( $response->getBody()->getContents(), true );
				$message = isset( $result['hydra:description'] ) ? $result['hydra:description'] : __( 'Could not add tracking number.', 'trackmage' ); 
				throw new \Exception( $message );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}

		try {
		// Get HTML to return.
		ob_start();
		include  TRACKMAGE_VIEWS_DIR . 'meta-boxes/order-tracking-numbers.php';
		$tracking_numbers_html = ob_get_clean();
		} catch ( \Exception $e ) {
			wp_send_json_error(['error' => $e->getMessage()]);
		}


		wp_send_json_success( [
			'message' => __( 'Added tracking number successfully!', 'trackmage' ),
			'html' => $tracking_numbers_html,
		] );
	}

	public function get_order_items() {
		$order_id = $_POST['order_id'];
		$order = wc_get_order( $order_id );

		$results = [];
		
		foreach ($order->get_items() as $id => $item) {
			array_push( $results, [
				'id' => $id,
				'text' => $item['name'],
			] );
		}

		wp_send_json( $results );
	}

	/**
	 * Sync with TrackMage on status change.
	 *
	 * @return void
	 */
	public function status_changed( $order_id, $old_status, $status ) {
		$sync_statuses = get_option( 'trackmage_sync_statuses', [] );

		update_option( 'trackmage_test', json_encode( [
			'order_id' => $order_id,
			'status' => $status,
			'sync_statuses' => $sync_statuses
		] ) );

		if ( in_array( 'wc-' . $status, $sync_statuses ) ) {
			$this->_sync( $order_id );
		}
	}

	/**
	 * Sync with TrackMage on order creation.
	 *
	 * @return void
	 */
	public function new_order( $order_id ) {
		$order = wc_get_order( $order_id );

		// Exit if order meta has not been saved yet.
		// This will happen with the new orders created from the checkout page.
		// A second try will happen when the `woocommerce_checkout_update_order_meta` action is fired shortly.
		if ( empty( $order->get_items() ) ) {
			return;
		}

		$status = $order->get_status();
		$sync_statuses = get_option( 'trackmage_sync_statuses', [] );

		if ( empty( $sync_statuses ) || in_array( 'wc-' . $status, $sync_statuses ) ) {
			$this->_sync( $order_id );
		}
	}

	private function _sync( $order_id ) {
		$workspace = get_option( 'trackmage_workspace' );
		$order = wc_get_order( $order_id );
		$client = Plugin::get_client();
		$_trackmage_order_id = get_post_meta( $order_id, '_trackmage_order_id', 0 );
		
		if ( $_trackmage_order_id ) {
			return;
		}

		// Create order on TrackMage.
		try {
			$response = $client->getGuzzleClient()->post(
				'/orders', [
					'json' => [
						'workspace' => '/workspaces/' . $workspace,
						'externalSyncId' => (string) $order_id,
						'orderType' => 'customer',
					]
				]
			);

			if ( 201 === $response->getStatusCode() ) {
				$result = json_decode( $response->getBody()->getContents(), true );
				$trackmage_order_id = $result['id'];
				add_post_meta( $order_id, '_trackmage_order_id', $trackmage_order_id, true );

				/*
				 * Create order items on TrackMage.
				 */
				foreach( $order->get_items() as $id => $item ) {
					$product = $item->get_product();

					$response = $client->getGuzzleClient()->post(
						'/order_items', [
							'json' => [
								'order' => '/orders/' . $trackmage_order_id,
								'productName' => $item['name'],
								'qty' => $item['quantity'],
								'price' => (string) $product->get_id(),
								'rowTotal' => (string) $item['total'],
								'externalSyncId' => (string) $order_id,
							]
						]
					);

					if ( 201 === $response->getStatusCode() ) {
						$result = json_decode( $response->getBody()->getContents(), true );
						$trackmage_order_item_id = $result['id'];
						wc_add_order_item_meta( $id, '_trackmage_order_item_id', $trackmage_order_item_id, true );
					}
				
				}
			}
		} catch ( ApiException $e ) {
			// Do nothing for now.
		}
	}

	/**
	 * Ajax: get statuses.
	 */
	public function get_statuses() {
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
