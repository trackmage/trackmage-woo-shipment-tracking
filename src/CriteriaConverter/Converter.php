<?php

namespace TrackMage\WordPress\CriteriaConverter;

use TrackMage\WordPress\CriteriaConverter\OperationParser\AndParser;
use TrackMage\WordPress\CriteriaConverter\OperationParser\OrParser;
use TrackMage\WordPress\Exception\RuntimeException;

class Converter
{
    /** @var OpParserInterface[] */
    private $parsers;

    /** @var OpParserInterface|NULL */
    private $defaultParser;

    public function __construct()
    {
        $this->parsers = [
            new OrParser(),
        ];
        $this->defaultParser = new AndParser();
    }

    /**
     * @param array $criteria
     * @return string|null
     */
    public function getSqlForCriteria(array $criteria)
    {
        if (empty($criteria)) {
            return null;
        };

        $conditions = [];
        foreach ($criteria as $key => $value)
        {
            if (!$this->isFilteredKey($key)) {
                continue;
            }
            $conditions[] = $this->getSqlForOpValue($key, $value, '');
        }

        return implode(' AND ', $conditions);
    }

    private function getSqlForOpValue($op, $value, $parentKey)
    {
        $opParser = $this->resolveParser($op);
        if ($opParser === null) {
            throw new RuntimeException('Can not parse operation '.$op);
        }
        $parsed_value = $this->getParsedValue($value, $op);

        return $opParser->getSql($op, $parsed_value, $parentKey);
    }

    /**
     * @param mixed $value
     * @param $parentOp
     * @return array
     */
    private function getParsedValue($value, $parentOp)
    {
        if (!is_array($value)) {
            return $value;
        }
        $parsedValue = array();
        $i = 0;
        foreach ($value as $k => $v) {
            if ($k === $i && !is_array($v)) {
                $parsedValue[] = $v;
            } else {
                if (!$this->isFilteredKey($k)) {
                    continue;
                }
                $parsedValue[] = $this->getSqlForOpValue($k, $v, $parentOp);
            }
            $i++;
        }
        return $parsedValue;
    }

    private function resolveParser($op)
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($op)) {
                return $parser;
            }
        }
        return $this->defaultParser;
    }

    private function isFilteredKey($key)
    {
        if (!is_scalar($key)) {
            return true;
        }
        $filtered_key = preg_replace('/[^a-zA-Z0-9_\$]/', '', $key);
        return $filtered_key === (string)$key;
    }
}
