<?php

namespace Sheerockoff\BitrixElastic;

use Elasticsearch\Client;

class Indexer
{
    private $elastic;

    public function __construct(Client $elastic)
    {
        $this->elastic = $elastic;
    }

    /**
     * @return Client
     */
    public function getElastic()
    {
        return $this->elastic;
    }
}