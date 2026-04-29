<?php

namespace TrackMage\WordPress\Repository;

class LogRepository extends AbstractRepository
{
    const TABLE = 'trackmage_log';

    /**
     * @param \wpdb $db
     * @param $dropOnDeactivate
     */
    public function __construct($db, $dropOnDeactivate) {
        $table = $db->prefix . self::TABLE;

        $ddl = "CREATE TABLE {$table} (
                    `id` int(11)  UNSIGNED NOT NULL AUTO_INCREMENT,
                    `message` TEXT NOT NULL,
                    `context` TEXT NOT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    INDEX log_idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        parent::__construct($db, $table, $ddl, $dropOnDeactivate);
    }

    /**
     * Trim the log table to at most $keepCount most recent rows.
     *
     * Returns the number of rows actually deleted (0 if the table already had
     * <= $keepCount rows). Implementation: one indexed seek to find the id at
     * offset $keepCount counted from the newest row, then a single range
     * delete by id. No-op if the table is at or below the cap.
     *
     * @param int $keepCount Number of newest rows to keep. Pass 0 to wipe
     *                       the entire table (equivalent to truncate()).
     * @return int Rows deleted.
     */
    public function rotate(int $keepCount): int
    {
        if ($keepCount < 0) {
            return 0;
        }
        if ($keepCount === 0) {
            $rows = (int) $this->db->get_var("SELECT COUNT(*) FROM `{$this->table}`");
            $this->truncate();
            return $rows;
        }
        $cutoff = $this->db->get_var(
            $this->db->prepare(
                "SELECT id FROM `{$this->table}` ORDER BY id DESC LIMIT %d, 1",
                $keepCount
            )
        );
        if ($cutoff === null) {
            return 0;
        }
        return (int) $this->db->query(
            $this->db->prepare(
                "DELETE FROM `{$this->table}` WHERE id <= %d",
                (int) $cutoff
            )
        );
    }
}
