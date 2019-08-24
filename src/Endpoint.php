<?php
/**
 * The Endpoint class.
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */

namespace TrackMage\WordPress;

/**
 * Webhook callback URL.
 *
 * @since 0.1.0
 */
class Endpoint {

	/**
	 * The constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		add_filter( 'query_vars', [ $this, 'add_query_vars' ], 0 );
		add_action( 'init', [ $this, 'endpoint' ] , 0);
		add_action( 'parse_request', [ $this, 'handle_endpoint_requests' ], 0 );
		add_action( 'trackmage_endpoint_callback', [ $this, 'authorize' ], 10, 2 );
	}

	public function add_query_vars( $vars ) {
		$vars[] = 'trackmage';
		return $vars;
	}

	public function endpoint() {
		add_rewrite_endpoint( 'trackmage', EP_ALL );
	}

	public function handle_endpoint_requests() {
		global $wp;

		if ( ! empty( $_GET['trackmage'] ) ) {
			$wp->query_vars['trackmage'] = sanitize_key( wp_unslash( $_GET['trackmage'] ) );
		}

		if ( ! empty( $wp->query_vars['trackmage'] ) ) {
			// Buffer, we won't want any output here.
			ob_start();

			// HTTP headers.
			$headers = Helper::getallheaders();

			// Endpoint request.
			$request = strtolower( $wp->query_vars['trackmage'] );

			// Response body.
			$response = file_get_contents( 'php://input', true );

			// Trigger an action which can be hooked into to fulfill the request.
			do_action( 'trackmage_endpoint_' . $request, $headers, $response );

			// Done, clear buffer and exit.
			//ob_end_clean();
			die( '-1' );
		}
	}

	public function authorize( $headers, $response ) {
		$username = get_option( 'trackmage_webhook_username', '' );
		$password = get_option( 'trackmage_webhook_password', '' );

		if ( ! isset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] )
			|| $_SERVER['PHP_AUTH_USER'] !== $username
			|| $_SERVER['PHP_AUTH_PW'] !== $password
		) {
			header( 'WWW-Authenticate: Basic realm="Restricted area"' );
			http_response_code( 401 );
			die( 'Unauthorized ' . $_SERVER['REQUEST_URI'] . ' from ' . $_SERVER['REMOTE_ADDR'] );
		}
	}
}
