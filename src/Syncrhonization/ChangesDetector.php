<?php

namespace TrackMage\WordPress\Syncrhonization;

use ArrayAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use TrackMage\WordPress\Exception\InvalidArgumentException;

class ChangesDetector
{
    private $fields;
    private $getStoredHashCallback;
    private $storeHashCallback;

    /** @var PropertyAccessor|null */
    private $propertyAccessor;

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
            $accessor = $this->getPropertyAccessor();
            return $accessor->isReadable($entity, $field) ? $accessor->getValue($entity, $field) : '';
        }, $this->fields);

        return md5(implode(',', $items));
    }

    /**
     * @return PropertyAccessor
     */
    private function getPropertyAccessor()
    {
        if (null === $this->propertyAccessor) {
            $this->propertyAccessor = new PropertyAccessor();
        }
        return $this->propertyAccessor;
    }

    private function checkArrayAccessible($var)
    {
        if (!(is_array($var) || $var instanceof ArrayAccess)) {
            throw new InvalidArgumentException('Entity must provide array access');
        }
    }
}
