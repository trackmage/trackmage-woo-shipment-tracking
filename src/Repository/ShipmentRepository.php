<?php

namespace TrackMage\WordPress\Repository;

class ShipmentRepository extends AbstractRepository
{
    const TABLE = 'trackmage_shipment';

    /**
     * @param \wpdb $db
     * @param $dropOnDeactivate
     */
    public function __construct($db, $dropOnDeactivate) {
        $table = $db->prefix . self::TABLE;

        $ddl = "CREATE TABLE {$table} (
                    `id` int(11)  UNSIGNED NOT NULL AUTO_INCREMENT,
                    `order_id` INT NOT NULL,
                    `tracking_number` VARCHAR(100) DEFAULT NULL,
                    `carrier` VARCHAR(64) DEFAULT NULL,
                    `status` VARCHAR(100) DEFAULT NULL,
                    `trackmage_id` VARCHAR(100) DEFAULT NULL,
                    PRIMARY KEY (id),
                    INDEX shipment_idx_order_id (order_id),
                    INDEX shipment_idx_tracking_number (tracking_number)
                ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        parent::__construct($db, $table, $ddl, $dropOnDeactivate);
    }
}
