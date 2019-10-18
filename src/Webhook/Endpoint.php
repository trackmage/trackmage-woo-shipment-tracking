<?php
/**
 * The Endpoint class.
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */

namespace TrackMage\WordPress\Webhook;

use TrackMage\WordPress\Exception\RuntimeException;
use TrackMage\WordPress\Helper;
use TrackMage\WordPress\Webhook\Mappers\OrdersMapper;
use TrackMage\WordPress\Webhook\Mappers\ShipmentsMapper;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Webhook callback URL.
 *
 * @since 0.1.0
 */
class Endpoint {

    const TAG = '[Endpoint]';

    private $logger;
    private $updaters = [];

	/**
	 * The constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct(LoggerInterface $logger, OrdersMapper $orders_mapper, ShipmentsMapper $shipments_mapper) {

	    $this->logger = $logger;

		$this->updaters[] = $orders_mapper;
		$this->updaters[] = $shipments_mapper;

		$this->bindEvents();
	}

	private function bindEvents(){
        add_filter( 'query_vars', [ $this, 'add_query_vars' ], 0 );
        add_action( 'init', [ $this, 'endpoint' ] , 0);
        add_action( 'parse_request', [ $this, 'handle_endpoint_requests' ], 0 );
        add_action( 'trackmage_endpoint_callback', [ $this, 'authorize' ], 10, 2 );
        add_action( 'process_request', [$this, 'process'], 10, 2);
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
		}else{
		    do_action('process_request', $headers, $response);
        }
	}

	public function process($headers, $response){
	    $responseData = json_decode($response, true);
	    try{
            $data = $responseData['data'];
            foreach ($data as $key => $item){
                $updater = $this->resolveUpdater($item);
                $updater->handle($item);
            }
        }catch (RuntimeException $e){
            //http_response_code( 400);
            //die( 'Error during processing data: ' . $e->getCode(). ' '.$e->getMessage() );
            $this->logger->warning(self::TAG.'Unable to process mapper', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'responseData' => $responseData
            ]);
        }
    }

    private function resolveUpdater(array $item){
        foreach ($this->updaters as $updater) {
            if ($updater->supports($item)) {
                return $updater;
            }
        }
    }
}
