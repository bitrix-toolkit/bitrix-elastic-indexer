<?php

namespace Sheerockoff\BitrixElastic;

use _CIBElement;
use Elasticsearch\Client;
use Exception;

class Indexer
{
    /** @var Client */
    private $elastic;

    /** @var Mapper */
    private $mapper;

    /** @var Keeper */
    private $keeper;

    /** @var Finder */
    private $finder;

    public function __construct(Client $elastic, $strictMode = true)
    {
        $this->elastic = $elastic;
        $this->mapper = new Mapper($this->elastic);
        $this->keeper = new Keeper($this->elastic);
        $this->finder = new Finder($this->elastic, $this->mapper, $strictMode);
    }

    public function getElastic(): Client
    {
        return $this->elastic;
    }

    public function getMapper(): Mapper
    {
        return $this->mapper;
    }

    public function getKeeper(): Keeper
    {
        return $this->keeper;
    }

    public function getFinder(): Finder
    {
        return $this->finder;
    }

    public function getInfoBlockMapping(int $infoBlockId): IndexMapping
    {
        return $this->mapper->getInfoBlockMapping($infoBlockId);
    }

    public function getMapping(string $index): IndexMapping
    {
        return $this->mapper->getMapping($index);
    }

    /** @noinspection PhpUnused */
    public function getCachedMapping(string $index): IndexMapping
    {
        return $this->mapper->getCachedMapping($index);
    }

    public function putMapping(string $index, IndexMapping $mapping): bool
    {
        return $this->mapper->putMapping($index, $mapping);
    }

    /**
     * @throws Exception
     */
    public function getElementRawData(_CIBElement $element): array
    {
        return $this->keeper->getElementRawData($element);
    }

    /**
     * @throws Exception
     */
    public function normalizeData(IndexMapping $mapping, array $data): array
    {
        return $this->keeper->normalizeData($mapping, $data);
    }

    public function put(string $index, ?int $id, array $data): bool
    {
        return $this->keeper->put($index, $id, $data);
    }

    /**
     * @throws Exception
     */
    public function search(string $index, array $filter, array $sort = ['SORT' => 'ASC', 'ID' => 'DESC'], array $parameters = []): array
    {
        return $this->finder->search($index, $filter, $sort, $parameters);
    }

    /**
     * @noinspection PhpUnused
     * @throws Exception
     */
    public function prefabElasticSearchParams(string $index, array $filter, array $sort = ['SORT' => 'ASC', 'ID' => 'DESC'], array $parameters = []): array
    {
        return $this->finder->prefabElasticSearchParams($index, $filter, $sort, $parameters);
    }
}