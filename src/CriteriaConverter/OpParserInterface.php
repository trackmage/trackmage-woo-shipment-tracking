<?php

namespace TrackMage\WordPress\CriteriaConverter;

interface OpParserInterface
{
    public function supports($op);
    public function getSql($op, $value, $parentOp);
}
