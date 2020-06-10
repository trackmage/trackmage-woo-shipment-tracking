<?php

namespace TrackMage\WordPress\Synchronization;

use ArrayAccess;
use TrackMage\WordPress\Exception\InvalidArgumentException;

class ChangesDetector
{
    private $fields;
    private $getStoredHashCallback;
    private $storeHashCallback;

    /**
     * @param array $fields Tracked fields
     * @param callable $getStoredHashCallback function(entity): string
     * @param callable $storeHashCallback function(entity, hash): entity
     */
    public function __construct(array $fields, $getStoredHashCallback, $storeHashCallback)
    {
        if (empty($fields)) {
            throw new InvalidArgumentException('Fields must be specified');
        }
        $this->fields = $fields;
        $this->getStoredHashCallback = $getStoredHashCallback;
        $this->storeHashCallback = $storeHashCallback;
    }

    /**
     * @param $entity
     * @return bool
     */
    public function isChanged($entity)
    {
        $this->checkArrayAccessible($entity);
        $hash = $this->calculateHash($entity);
        $storedHash = call_user_func($this->getStoredHashCallback, $entity);

        return $hash !== $storedHash;
    }

    public function lockChanges($entity)
    {
        $this->checkArrayAccessible($entity);
        $hash = $this->calculateHash($entity);
        $entity = call_user_func($this->storeHashCallback, $entity, $hash);

        return $entity;
    }

    private function calculateHash($entity)
    {
        $items = array_map(function($field) use($entity) {
            return $this->getPropertyValue($entity, $field);
        }, $this->fields);

        return md5(implode(',', $items));
    }

    /**
     * @param array|ArrayAccess $entity
     * @param string $field
     * @return string
     */
    private function getPropertyValue($entity, $field)
    {
        if (1 === preg_match('/\[(.+?)\]/', $field, $matches)
            && null !== ($value = $matches[1]) && isset($entity[$value])
        ) {
            return $entity[$value];
        }
        return '';
    }

    private function checkArrayAccessible($var)
    {
        if (!(is_array($var) || $var instanceof ArrayAccess)) {
            throw new InvalidArgumentException('Entity must provide array access');
        }
    }
}
