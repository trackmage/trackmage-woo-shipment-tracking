<?php

namespace TrackMage\WordPress\Repository;

class ShipmentItemRepository extends AbstractRepository
{
    const TABLE = 'trackmage_shipment_item';

    /**
     * @param \wpdb $db
     * @param $dropOnDeactivate
     */
    public function __construct($db, $dropOnDeactivate) {
        $table = $db->prefix . self::TABLE;

        $ddl = "CREATE TABLE {$table} (
                    `id` int(11)  UNSIGNED NOT NULL AUTO_INCREMENT,
                    `order_item_id` INT NOT NULL,
                    `shipment_id` INT NOT NULL,
                    `qty` INT NOT NULL,
                    `trackmage_id` VARCHAR(100) DEFAULT NULL,
                    `hash` VARCHAR(100) DEFAULT NULL,
                    PRIMARY KEY (id),
                    INDEX shipment_item_idx_shipment_id (shipment_id),
                    INDEX shipment_item_idx_search (order_item_id, shipment_id)
                ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        parent::__construct($db, $table, $ddl, $dropOnDeactivate);
    }
}
