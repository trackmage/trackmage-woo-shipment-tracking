<?php

namespace TrackMage\WordPress\CriteriaConverter\OperationParser;

use TrackMage\WordPress\CriteriaConverter\OpParserInterface;

class AndParser implements OpParserInterface
{
    protected $op = '$and';

    public function getSql($op, $value, $parentOp)
    {
        if (!is_array($value)) {
            if (!is_numeric($value)) {
                $value = "'$value'";
            }
            return '`'.addslashes($op).'` = '.$value;
        }
        if (count($value) > 1) {
            if ($op !== $this->op) {
                return false;
            }

            return '('.implode(' AND ', $value).')';
        }

        return current($value);
    }

    public function supports($op)
    {
        return true;
    }
}
