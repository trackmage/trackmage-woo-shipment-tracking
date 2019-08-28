<?php

namespace TrackMage\WordPress\Repository;

use TrackMage\WordPress\CriteriaConverter\Converter;

require_once ABSPATH . '/wp-admin/includes/upgrade.php';

class AbstractRepository implements EntityRepositoryInterface
{
    private $table;
    private $createQuery;
    private $dropOnDeactivate;
    private $db;

    /**
     * @param \wpdb $db
     * @param string $table
     * @param string $tableDdl
     * @param bool $dropOnDeactivate
     */
    public function __construct($db, $table, $tableDdl, $dropOnDeactivate) {
        $this->db = $db;
        $this->table = $table;
        $this->createQuery = $this->replaceTable($tableDdl);
        $this->dropOnDeactivate = $dropOnDeactivate;
    }

    public function getTable() {
        return $this->table;
    }

    /**
     * @param string $sql
     * @return string
     */
    private function replaceTable($sql) {
        return str_replace('_TBL_', $this->table, $sql);
    }

    public function  init() {
        if($this->createQuery === null) {
            return;
        }
        if ($this->db->get_var("SHOW TABLES LIKE '{$this->table}'") !== $this->table) {
            dbDelta(sprintf($this->createQuery, $this->table));
        }
    }

    public function drop() {
        if ($this->dropOnDeactivate) {
            $this->db->query("DROP TABLE IF EXISTS `{$this->table}`");
        }
    }

    public function executeQuery($sql) {
        $this->db->query($this->replaceTable($sql));
    }

    public function getQuery($sql) {
        return $this->db->get_results($this->replaceTable($sql));
    }

    /**
     * @param array $data
     * @return array|null
     */
    public function insert(array $data)
    {
        if ($this->db->insert($this->table, $data) === false) {
            return null;
        }
        return $this->find($this->db->insert_id);
    }

    /**
     * @param int $id
     * @return array|null
     */
    public function find($id)
    {
        return $this->db->get_row(
            $this->db->prepare( "SELECT * FROM `{$this->table}` WHERE id = %d", [$id]), ARRAY_A
        );
    }

    /**
     * @param array $criteria
     * @param int|null $limit
     * @return array|null
     */
    public function findBy(array $criteria, $limit = null)
    {
        $whereSql = $this->getWhereStringValue($criteria);
        $limitSql = $limit !== null ? 'LIMIT 0,'.$limit : '';

        return $this->db->get_results("SELECT * FROM `{$this->table}` {$whereSql} {$limitSql}", ARRAY_A);
    }

    /**
     * @param array $criteria
     * @return array|null
     */
    public function findOneBy(array $criteria)
    {
        $whereSql = $this->getWhereStringValue($criteria);

        return $this->db->get_row("SELECT * FROM `{$this->table}` {$whereSql} LIMIT 0,1", ARRAY_A);
    }

    /**
     * @param array $criteria
     * @param int|null $limit
     * @return false|int
     */
    public function delete(array $criteria, $limit = null)
    {
        $whereSql = $this->getWhereStringValue($criteria);
        $limitSql = $limit !== null ? 'LIMIT 0,'.$limit : '';

        return $this->db->query("DELETE FROM `{$this->table}` {$whereSql} {$limitSql}");
    }

    protected function getWhereStringValue($filter_key_values_array)
    {
        $filterConverter = new Converter();
        $whereSql = $filterConverter->getSqlForCriteria($filter_key_values_array);
        if ($whereSql !== null) {
            return 'WHERE '.$whereSql;
        }
        return '';
    }
}
