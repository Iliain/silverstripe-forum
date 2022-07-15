<?php

namespace SilverStripe\Forum\Model;

use SilverStripe\ORM\DataQuery;

/**
 * This is a DataQuery that allows us to replace the underlying query. Hopefully this will
 * be a native ability in 3.1, but for now we need to.
 * TODO: Remove once API in core
 */
class ForumDataQuery extends DataQuery
{
    public function __construct($dataClass, $query)
    {
        parent::__construct($dataClass);
        $this->query = $query;
    }
}