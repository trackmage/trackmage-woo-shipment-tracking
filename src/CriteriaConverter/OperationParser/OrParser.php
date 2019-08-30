<?php

namespace TrackMage\WordPress\CriteriaConverter\OperationParser;

use TrackMage\WordPress\CriteriaConverter\OpParserInterface;
use TrackMage\WordPress\Exception\RuntimeException;

class OrParser implements OpParserInterface
{
    protected $op = '$or';

    public function getSql($op, $value, $parentOp)
    {
        if (!is_array($value)) {
            throw new RuntimeException('Value should be an array');
        }
        return '('.implode(' OR ', $value).')';
    }

    public function supports($op)
    {
        return $this->op === $op;
    }
}
