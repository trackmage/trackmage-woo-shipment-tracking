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
use TrackMage\WordPress\Admin\Wizard;
use TrackMage\WordPress\Admin\Orders;
use TrackMage\WordPress\Synchronization\ProductSync;
use TrackMage\WordPress\Webhook\Endpoint;
use TrackMage\Client\TrackMageClient;
use TrackMage\WordPress\Repository\EntityRepositoryInterface;
use TrackMage\WordPress\Repository\LogRepository;
use TrackMage\WordPress\Repository\ShipmentItemRepository;
use TrackMage\WordPress\Repository\ShipmentRepository;
use TrackMage\WordPress\Repository\BackgroundTaskRepository;
use TrackMage\WordPress\Synchronization\OrderItemSync;
use TrackMage\WordPress\Synchronization\OrderSync;
use TrackMage\WordPress\Synchronization\ShipmentItemSync;
use TrackMage\WordPress\Synchronization\ShipmentSync;
use TrackMage\WordPress\Webhook\Mappers\OrdersMapper;

/**
 * Main plugin class.
 *
 * @since   0.1.0
 */
class Plugin {
    /** @var ShipmentRepository|null */
    private $shipmentRepo;

    /** @var ShipmentItemRepository|null */
    private $shipmentItemRepo;

    /** @var LogRepository|null */
    private $logRepo;

    /** @var BackgroundTaskRepository|null */
    private $backgroundTaskRepo;

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

    /** @var ProductSync|null */
    private $productSync;

    private $wpdb;

    private $dropOnDeactivate = true;

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

    /** @var Endpoint */
    private $endpoint;

    /** @var OrdersMapper|null */
    private $ordersMapper;

    /**
     * @var Shortcodes | null
     */
    private $shortcodes;


    /**
     * @param \wpdb $wpdb
     */
    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;

        $this->bindEventDeleteShipment();
    }

    private function bindEventDeleteShipment()
    {
        // Deletes TrackMage shipments that reference a WooCommerce order when
        // that order is deleted. Registers parallel handlers for classic CPT
        // storage (before_delete_post) and HPOS storage
        // (woocommerce_before_delete_order); a static dedupe guard prevents
        // double-processing when both fire on classic stores.
        $handler = function ($orderId) {
            static $seen = [];
            $orderId = (int) $orderId;
            if (isset($seen[$orderId])) {
                return;
            }
            $seen[$orderId] = true;
            foreach ($this->getShipmentRepo()->findBy(['orderNumbers' => $orderId]) as $shipment) {
                if (in_array($orderId, $shipment['orderNumbers'] ?? [], true)) {
                    Helper::deleteShipment($shipment['id']);
                }
            }
        };
        add_action('before_delete_post', function ($postId) use ($handler) {
            if (get_post_type($postId) === 'shop_order') {
                $handler($postId);
            }
        }, 10, 1);
        add_action('woocommerce_before_delete_order', $handler, 10, 1);
    }

    /**
     * Returns the singleton instance of TrackMageClient.
     *
     * Ensures only one instance of TrackMageClient is/can be loaded.
     *
     * @since 0.1.0
     * @return TrackMageClient
     */
    public static function get_client($config = []): ?TrackMageClient
    {
        if ( null === self::$client ) {
            $client_id = $config['client_id'] ?? get_option('trackmage_client_id', '');
            $client_secret = $config['client_secret'] ?? get_option('trackmage_client_secret', '');
            $mw = RemoveTransientOnErrorMiddleware::get($client_id, $client_secret);
            self::$client = new TrackMageClient( $client_id, $client_secret, null, TRACKMAGE_API_DOMAIN, null, $mw);
        }

        return self::$client;
    }

    /**
     * @return Synchronizer
     */
    public function getSynchronizer(): Synchronizer
    {
        if ($this->synchronizer === null) {
            $this->synchronizer = new Synchronizer($this->getLogger(), $this->getOrderSync(), $this->getOrderItemSync(),
                $this->getProductSync(), $this->getBackgroundTaskRepo());
        }

        return $this->synchronizer;
    }

    /**
     * @return Endpoint
     */
    public function getEndpoint(): Endpoint
    {
        if($this->endpoint === null){
            $this->endpoint = new Endpoint($this->getLogger(), $this->getOrdersMapper());
        }

        return $this->endpoint;
    }

    /**
     * @return self
     */
    public static function instance(): self
    {
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
    public function init() {
        $this->getSynchronizer();
        $this->getEndpoint();
        // Initialize classes.
        new Admin;
        new Wizard;
        new Orders();
        new TrackingInfo();

        $initClasses = [
            'Ajax',
            'Assets',
            'Admin\Assets',
        ];

        foreach ( $initClasses as $class ) {
            call_user_func( [ __NAMESPACE__ . "\\{$class}", 'init' ] );
        }

        Helper::scheduleNextBackgroundTask(1);

        $this->scheduleLogRotation();

        if(Helper::canSync()) {
            $this->getShortcodes()->init();
        }
    }

    /**
     * Hook the daily log-rotation cron event and bind its handler.
     *
     * Idempotent: re-runs on every plugin load but only adds the schedule
     * once. The schedule is cleared on plugin deactivation
     * (see trackMageDeactivate in trackmage.php).
     *
     * @since 2.1.2
     */
    private function scheduleLogRotation(): void
    {
        add_action('trackmage_rotate_logs', [$this, 'runLogRotation']);
        if (!wp_next_scheduled('trackmage_rotate_logs')) {
            // Stagger the first run an hour out so back-to-back activations
            // of multiple plugins don't all fire at exactly the same minute.
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'trackmage_rotate_logs');
        }
    }

    /**
     * Cron handler: trims the log table down to at most
     * apply_filters('trackmage_log_max_rows', 1000) rows.
     *
     * Filter `trackmage_log_max_rows` lets a high-volume shop bump the cap
     * (or set 0 to wipe the table on each rotation) without editing the
     * plugin source.
     *
     * @since 2.1.2
     */
    public function runLogRotation(): void
    {
        /**
         * Filter: maximum number of rows to keep in wp_trackmage_log after
         * the daily rotation pass. Default 1000.
         *
         * Set to 0 to clear the table on every rotation, or to a larger
         * value (e.g. 10000) on a busy store.
         *
         * @param int $max Default 1000.
         */
        $max = (int) apply_filters('trackmage_log_max_rows', 1000);
        if ($max < 0) {
            return;
        }
        $repo = $this->getLogRepo();
        if ($repo === null) {
            return;
        }
        $deleted = $repo->rotate($max);
        if ($deleted > 0) {
            $this->getLogger()->info(
                sprintf('Log rotation: removed %d entries, keeping %d most recent', $deleted, $max)
            );
        }
    }

    /**
     * @return ShipmentRepository
     */
    public function getShipmentRepo(): ?ShipmentRepository
    {
        if($this->shipmentRepo === null) {
            $this->shipmentRepo = new ShipmentRepository($this->getShipmentSync());
        }
        return $this->shipmentRepo;
    }

    /**
     * @return ShipmentItemRepository
     */
    public function getShipmentItemsRepo(): ?ShipmentItemRepository
    {
        if($this->shipmentItemRepo === null) {
            $this->shipmentItemRepo = new ShipmentItemRepository($this->getShipmentItemSync());
        }
        return $this->shipmentItemRepo;
    }

    /**
     * @return LogRepository
     */
    public function getLogRepo(): ?LogRepository
    {
        if($this->logRepo === null) {
            $this->logRepo = new LogRepository($this->wpdb, $this->dropOnDeactivate);
        }
        return $this->logRepo;
    }

    /**
     * @return BackgroundTaskRepository
     */
    public function getBackgroundTaskRepo(): ?BackgroundTaskRepository
    {
        if($this->backgroundTaskRepo === null) {
            $this->backgroundTaskRepo = new BackgroundTaskRepository($this->wpdb, $this->dropOnDeactivate);
        }
        return $this->backgroundTaskRepo;
    }

    /**
     * @return Logger
     */
    public function getLogger(): ?Logger
    {
        if($this->logger === null) {
            $this->logger = new Logger($this->getLogRepo());
        }
        return $this->logger;
    }

    /**
     * @return EntityRepositoryInterface[]
     */
    public function getRepos(): array
    {
        return [$this->getLogRepo(), $this->getBackgroundTaskRepo()];
    }

    public function getIntegrationId()
    {
        return get_option('trackmage_integration','');
    }

    /**
     * @return OrderSync
     */
    public function getOrderSync(): ?OrderSync
    {
        if (null === $this->orderSync) {
            $this->orderSync = new OrderSync($this->getIntegrationId());
        }
        return $this->orderSync;
    }

    /**
     * @return ShipmentSync
     */
    public function getShipmentSync(): ?ShipmentSync
    {
        if (null === $this->shipmentSync) {
            $this->shipmentSync = new ShipmentSync($this->getIntegrationId());
        }
        return $this->shipmentSync;
    }

    /**
     * @return ShipmentItemSync
     */
    public function getShipmentItemSync()
    {
        if (null === $this->shipmentItemSync) {
            $this->shipmentItemSync = new ShipmentItemSync();
        }
        return $this->shipmentItemSync;
    }

    /**
     * @return OrderItemSync
     */
    public function getOrderItemSync()
    {
        if (null === $this->orderItemSync) {
            $this->orderItemSync = new OrderItemSync($this->getIntegrationId());
        }
        return $this->orderItemSync;
    }

    /**
     * @return ProductSync|null
     */
    public function getProductSync()
    {
        if (null === $this->productSync) {
            $this->productSync = new ProductSync($this->getIntegrationId());
        }
        return $this->productSync;
    }

    /**
     * @return OrdersMapper
     */
    public function getOrdersMapper()
    {
        if (null === $this->ordersMapper) {
            $this->ordersMapper = new OrdersMapper($this->getIntegrationId());
        }

        return $this->ordersMapper;
    }

    public function dropOldTables(){
        $oldTables = ['trackmage_shipment_item', 'trackmage_shipment'];
        foreach ($oldTables as $oldTable){
            $tableName = $this->wpdb->prefix.$oldTable;
            $this->wpdb->query("DROP TABLE IF EXISTS `{$tableName}`");
        }
    }

    /**
     * @return Shortcodes
     */
    public function getShortcodes()
    {
        if($this->shortcodes === null){
            $this->shortcodes = new Shortcodes();
        }
        return $this->shortcodes;
    }

}
