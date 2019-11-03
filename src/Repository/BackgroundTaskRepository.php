<?php

namespace TrackMage\WordPress\Repository;

class BackgroundTaskRepository extends AbstractRepository
{
    const TABLE = 'trackmage_background_task';

    /**
     * @param \wpdb $db
     * @param $dropOnDeactivate
     */
    public function __construct($db, $dropOnDeactivate) {
        $table = $db->prefix . self::TABLE;

        $ddl = "CREATE TABLE {$table} (
                    `id` int(11)  UNSIGNED NOT NULL AUTO_INCREMENT,
                    `action` VARCHAR(100) NOT NULL,
                    `params` TEXT DEFAULT NULL,
                    `status` VARCHAR(100) NOT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    INDEX background_task_idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        parent::__construct($db, $table, $ddl, $dropOnDeactivate);
    }
}
