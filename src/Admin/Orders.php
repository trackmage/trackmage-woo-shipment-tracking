<?php
/**
 * The Orders class.
 *
 * @package TrackMage\WordPress\Admin
 * @author  TrackMage
 */

namespace TrackMage\WordPress\Admin;

use TrackMage\WordPress\Utils as Utils;

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
	}

	/**
	 * Add shipment tracking metabox to the order page.
	 *
	 * @since 0.1.0
	 */
	public function add_meta_box() {
		add_meta_box(
			'trackmage_shipment_tracking',
			__( 'TrackMage Shipment Tracking', 'trackmage' ),
			[ $this, 'meta_box_html' ],
			'shop_order',
			'side',
			'default'
		);
	}

	/**
	 * Save meta box fields.
	 *
	 * @since 0.1.0
	 * @param [int] $post_id Post ID.
	 */
	public static function save_meta_box( $post_id ) {
		if ( array_key_exists( 'trackmage_provider', $_POST ) ) {
			update_post_meta(
				$post_id,
				'trackmage_provider',
				$_POST['trackmage_provider']
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
		$provider = get_post_meta( $post->ID, 'trackmage_provider', true );
		$tracking_number = get_post_meta( $post->ID, 'trackmage_tracking_number', true );
		$status = get_post_meta( $post->ID, 'trackmage_status', true );
		$providers = Utils::get_shipment_providers();
		?>
		<p class="post-attributes-label-wrapper">
			<label class="post-attributes-label" for="trackmage_provider"><?php _e( 'Provider', 'trackmage' ); ?></label>
		</p>
		<select name="trackmage_provider" id="trackmage_provider">
			<option value=""><?php _e( '— Select —', 'trackmage' ); ?></option>
			<?php foreach ( $providers as $p ) : ?>
			<option value="<?php echo $p['code']; ?>" <?php selected( $p['code'], $provider ); ?>><?php echo $p['name']; ?></option>
			<?php endforeach; ?>
		</select>
		<p class="post-attributes-label-wrapper">
			<label class="post-attributes-label" for="trackmage_provider"><?php _e( 'Tracking Number', 'trackmage' ); ?></label>
		</p>
		<input type="text" name="trackmage_tracking_number" id="trackmage_tracking_number" value="<?php echo esc_attr( $tracking_number ); ?>" />
		<p class="post-attributes-label-wrapper">
			<label class="post-attributes-label"><?php _e( 'Status', 'trackmage' ); ?></label>
		</p>
		<?php echo $status; ?>
		<?php
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
}
