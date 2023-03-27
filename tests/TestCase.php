<?php

namespace Sheerockoff\BitrixElastic\Test;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

abstract class TestCase extends PhpUnitTestCase
{
    /**
     * Исключает ошибки Bitrix при формировании
     * запросов к базе данных.
     *
     * @var bool
     */
    protected $backupGlobals = false;

    public static function getElasticClient(): Client
    {
        $hosts = getenv('ELASTICSEARCH_HOSTS', true) ?: getenv('ELASTICSEARCH_HOSTS');
        $hosts = explode(',', $hosts);

        return ClientBuilder::create()->setHosts($hosts)->build();
    }
}