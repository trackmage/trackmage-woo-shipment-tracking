<?php
/**
 * Main class
 *
 * The main class of the plugin.
 *
 * @package TrackMage\WordPress
 * @author  TrackMage
 */

namespace TrackMage\WordPress;

use TrackMage\WordPress\Admin\Admin;
use TrackMage\WordPress\Admin\Orders;
use BrightNucleus\Config\ConfigInterface;
use BrightNucleus\Config\ConfigTrait;
use TrackMage\Client\TrackMageClient;
use TrackMage\Client\Swagger\ApiException;
use TrackMage\WordPress\Repository\EntityRepositoryInterface;
use TrackMage\WordPress\Repository\LogRepository;
use TrackMage\WordPress\Repository\ShipmentItemRepository;
use TrackMage\WordPress\Repository\ShipmentRepository;
use TrackMage\WordPress\Synchronization\OrderItemSync;
use TrackMage\WordPress\Synchronization\OrderSync;
use TrackMage\WordPress\Synchronization\ShipmentItemSync;
use TrackMage\WordPress\Synchronization\ShipmentSync;

/**
 * Main plugin class.
 *
 * @since   0.1.0
 */
class Plugin {

	use ConfigTrait;

    /** @var ShipmentRepository|null */
	private $shipmentRepo;

	/** @var ShipmentItemRepository|null */
	private $shipmentItemRepo;

	/** @var LogRepository|null */
	private $logRepo;

	/** @var Logger|null */
	private $logger;

    /** @var OrderSync|null */
    private $orderSync;

    /** @var OrderItemSync|null */
    private $orderItemSync;

    /** @var ShipmentSync|null */
    private $shipmentSync;

    /** @var ShipmentItemSync|null */
    private $shipmentItemSync;

    private $wpdb;

    /** @var string|null */
    private $instanceId;

    /**
	 * Static instance of the plugin.
	 *
	 * @since 0.1.0
	 *
	 * @var self
	 */
	protected static $instance;

	/**
	 * The singleton instance of TrackMageClient.
	 *
	 * @since 0.1.0
	 * @var TrackMageClient
	 */
	protected static $client = null;

	/** @var Synchronizer */
	private $synchronizer;

    /**
     * @param \wpdb $wpdb
     */
    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

	/**
	 * Returns the singleton instance of TrackMageClient.
	 *
	 * Ensures only one instance of TrackMageClient is/can be loaded.
	 *
	 * @since 0.1.0
	 * @return TrackMageClient
	 */
	public static function get_client($config = []) {
		if ( null === self::$client ) {
			self::$client = new TrackMageClient();

			try {
				$client_id = isset( $config['client_id'] ) ? $config['client_id'] : get_option( 'trackmage_client_id', '' );
				$client_secret = isset( $config['client_secret'] ) ? $config['client_secret'] : get_option( 'trackmage_client_secret', '' );

				self::$client = new TrackMageClient( $client_id, $client_secret );
				self::$client->setHost( 'https://api.test.trackmage.com' );
			} catch( ApiException $e ) {
				return null;
			}
		}

		return self::$client;
	}

    /**
     * @return Synchronizer
     */
    public function getSynchronizer()
    {
        if ($this->synchronizer === null) {
            $this->synchronizer = new Synchronizer($this->getLogger(), $this->getOrderSync(), $this->getOrderItemSync(),
                $this->getShipmentSync(), $this->getShipmentItemSync());
        }

        return $this->synchronizer;
	}

    /**
     * @return self
     */
    public static function instance() {
        if(null === self::$instance) {
            global $wpdb;
            self::$instance = new self($wpdb);
        }
        return self::$instance;
    }

    /**
	 * Launch the initialization process.
	 *
	 * @since 0.1.0
	 */
	public function init(ConfigInterface $config) {
        $this->processConfig( $config );

		// Initialize classes.
		new Endpoint;
		new Templates;
		new Admin;
		new Orders($this->getSynchronizer());

		$init_classes = [
			'Ajax',
			'Assets',
			'Admin\Assets',
		];

		foreach ( $init_classes as $class ) {
			call_user_func( [ __NAMESPACE__ . "\\{$class}", 'init' ] );
		}
	}

    /**
     * @return ShipmentRepository
     */
    public function getShipmentRepo() {
        if($this->shipmentRepo === null) {
            $dropOnDeactivate = $this->getConfigKey('dropOnDeactivate');
            $this->shipmentRepo = new ShipmentRepository($this->wpdb, $dropOnDeactivate);
        }
        return $this->shipmentRepo;
    }

    /**
     * @return ShipmentItemRepository
     */
    public function getShipmentItemsRepo() {
        if($this->shipmentItemRepo === null) {
            $dropOnDeactivate = $this->getConfigKey('dropOnDeactivate');
            $this->shipmentItemRepo = new ShipmentItemRepository($this->wpdb, $dropOnDeactivate);
        }
        return $this->shipmentItemRepo;
    }

    /**
     * @return LogRepository
     */
    public function getLogRepo() {
        if($this->logRepo === null) {
            $dropOnDeactivate = $this->getConfigKey('dropOnDeactivate');
            $this->logRepo = new LogRepository($this->wpdb, $dropOnDeactivate);
        }
        return $this->logRepo;
    }

    /**
     * @return Logger
     */
    public function getLogger() {
        if($this->logger === null) {
            $this->logger = new Logger($this->getLogRepo());
        }
        return $this->logger;
    }

    /**
     * @return EntityRepositoryInterface[]
     */
    public function getRepos() {
        return [$this->getLogRepo(), $this->getShipmentRepo(), $this->getShipmentItemsRepo()];
    }

    /**
     * @return string
     */
    public function getInstanceId()
    {
        if ($this->instanceId === null) {
            $instanceId = get_option('trackmage_instance_id');
            if ($instanceId === false) {
                add_option('trackmage_instance_id', $instanceId = uniqid('wp-'));
            }
            $this->instanceId = $instanceId;
        }
        return $this->instanceId;
    }

    /**
     * @return OrderSync
     */
    public function getOrderSync()
    {
        if (null === $this->orderSync) {
            $this->orderSync = new OrderSync($this->getInstanceId());
        }
        return $this->orderSync;
    }

    /**
     * @return OrderItemSync
     */
    public function getOrderItemSync()
    {
        if (null === $this->orderItemSync) {
            $this->orderItemSync = new OrderItemSync($this->getInstanceId());
        }
        return $this->orderItemSync;
    }

    /**
     * @return ShipmentSync
     */
    public function getShipmentSync()
    {
        if (null === $this->shipmentSync) {
            $this->shipmentSync = new ShipmentSync($this->getShipmentRepo(), $this->getInstanceId());
        }
        return $this->shipmentSync;
    }

    /**
     * @return ShipmentItemSync
     */
    public function getShipmentItemSync()
    {
        if (null === $this->shipmentItemSync) {
            $this->shipmentItemSync = new ShipmentItemSync($this->getShipmentItemsRepo(), $this->getShipmentRepo(), $this->getInstanceId());
        }
        return $this->shipmentItemSync;
    }
}
