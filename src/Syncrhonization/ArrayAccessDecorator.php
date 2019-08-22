<?php

namespace TrackMage\WordPress\Syncrhonization;

class ArrayAccessDecorator implements \ArrayAccess
{
    const SETTER = 'set_';
    const GETTER = 'get_';

    private $object;
    private $position;

    public function __construct($object)
    {
        $this->object = $object;
        $this->position = 0;
    }

    public function offsetExists($offset)
    {
        return method_exists($this->object, self::GETTER.$offset);
    }

    public function offsetGet($offset)
    {
        return call_user_func([$this->object, self::GETTER.$offset]);
    }

    public function offsetSet($offset, $value)
    {
        call_user_func([$this->object, self::SETTER.$offset], $value);
    }

    public function offsetUnset($offset)
    {
        call_user_func([$this->object, self::SETTER.$offset], null);
    }
}
