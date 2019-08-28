<?php

namespace TrackMage\WordPress\Repository;

interface EntityRepositoryInterface
{

    /**
     * getTable
     *
     * @return string
     */
    public function getTable();

    /**
     * init
     *
     * Create / update database table
     */
    public function init();

    /**
     * Drop database table
     */
    public function drop();

    /**
     * setQuery
     *
     * @param string $sql
     */
    public function executeQuery($sql);

    /**
     * getQuery
     *
     * @param string $sql
     * @return array|object|NULL
     */
    public function getQuery($sql);
}
