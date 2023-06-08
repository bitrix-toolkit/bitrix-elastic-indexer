<?php

namespace Sheerockoff\BitrixElastic;

use CCatalogGroup;
use CCatalogStore;
use CIBlockProperty;
use CModule;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;

class Mapper
{
    /** @var Client */
    private $elastic;

    private $runtimeCache = [];

    public function __construct(Client $elastic)
    {
        $this->elastic = $elastic;
    }

    public function getInfoBlockMapping(int $infoBlockId): IndexMapping
    {
        $mapping = new IndexMapping();

        foreach (array_keys(PropertyMapping::$bitrixFieldTypesMap) as $field) {
            $mapping->setProperty($field, PropertyMapping::fromBitrixField($field));
        }

        $rs = CIBlockProperty::GetList(null, ['IBLOCK_ID' => $infoBlockId]);
        while ($property = $rs->Fetch()) {
            if ($property['PROPERTY_TYPE'] === 'L') {
                $mapping->setProperty(
                    'PROPERTY_' . $property['CODE'],
                    new PropertyMapping('integer', ['fields' => ['enum' => ['type' => 'integer']]])
                );

                $mapping->setProperty(
                    'PROPERTY_' . $property['ID'],
                    new PropertyMapping('alias', ['path' => 'PROPERTY_' . $property['CODE']])
                );

                $mapping->setProperty(
                    'PROPERTY_' . $property['CODE'] . '_VALUE',
                    PropertyMapping::fromBitrixProperty($property)
                );

                $mapping->setProperty(
                    'PROPERTY_' . $property['ID'] . '_VALUE',
                    new PropertyMapping('alias', ['path' => 'PROPERTY_' . $property['CODE'] . '_VALUE'])
                );
            } else {
                $mapping->setProperty(
                    'PROPERTY_' . $property['CODE'],
                    PropertyMapping::fromBitrixProperty($property)
                );

                $mapping->setProperty(
                    'PROPERTY_' . $property['ID'],
                    new PropertyMapping('alias', ['path' => 'PROPERTY_' . $property['CODE']])
                );
            }
        }

        $mapping->setProperty('GROUP_IDS', new PropertyMapping('integer'));
        $mapping->setProperty('GROUP_CODES', new PropertyMapping('keyword'));

        $mapping->setProperty('NAV_CHAIN_IDS', new PropertyMapping('integer'));
        $mapping->setProperty('NAV_CHAIN_CODES', new PropertyMapping('keyword'));

        if (CModule::IncludeModule('catalog')) {
            $rs = CCatalogStore::GetList();
            while ($store = $rs->Fetch()) {
                $mapping->setProperty('CATALOG_STORE_AMOUNT_' . $store['ID'], new PropertyMapping('integer'));
            }

            $rs = CCatalogGroup::GetList();
            while ($entry = $rs->Fetch()) {
                $mapping->setProperty('CATALOG_PRICE_' . $entry['ID'], new PropertyMapping('float'));
                $mapping->setProperty('CATALOG_CURRENCY_' . $entry['ID'], new PropertyMapping('keyword'));
            }
        }

        return $mapping;
    }

    public function getMapping(string $index): IndexMapping
    {
        if ($this->elastic->indices()->exists(['index' => $index])) {
            $response = $this->elastic->indices()->getMapping(['index' => $index]);
            $mappingData = $response[$index]['mappings'] ?: [];
        } else {
            $mappingData = [];
        }

        $mapping = new IndexMapping();
        foreach ($mappingData['properties'] ?? [] as $property => $propertyData) {
            $mapping->setProperty($property, new PropertyMapping($propertyData['type'] ?? 'keyword', $propertyData));
        }

        $this->runtimeCache['mapping'][$index] = $mapping;

        return $mapping;
    }

    public function getCachedMapping(string $index): IndexMapping
    {
        if (isset($this->runtimeCache['mapping'][$index]) && $this->runtimeCache['mapping'][$index] instanceof IndexMapping) {
            return $this->runtimeCache['mapping'][$index];
        } else {
            return $this->getMapping($index);
        }
    }

    private function getMappingTotalFieldsLimit(string $index): ?int
    {
        $response = $this->elastic->indices()->getSettings([
            'index' => $index,
            'include_defaults' => true,
        ]);

        $setting = $response[$index]['settings']['index']['mapping']['total_fields']['limit'] ?? null;
        $default = $response[$index]['defaults']['index']['mapping']['total_fields']['limit'] ?? null;

        return $setting ?? $default;
    }

    /**
     * @throws ClientResponseException
     * @throws \Elastic\Elasticsearch\Exception\ServerResponseException
     * @throws \Elastic\Elasticsearch\Exception\MissingParameterException
     */
    public function putMapping(string $index, IndexMapping $mapping): bool
    {
        if (array_key_exists($index, $this->runtimeCache['mapping'] ?? [])) {
            unset($this->runtimeCache['mapping'][$index]);
        }

        if ($this->elastic->indices()->exists(['index' => $index])->asBool()) {
            try {
                $response = $this->elastic->indices()->getMapping(['index' => $index]);
                $existMappingData = $response[$index]['mappings'] ?: [];
            }
            catch (ClientResponseException $exception) {
                if ($exception->getCode() === 404) {
                    $existMappingData = [];
                }
                else {
                    throw $exception;
                }
            }
        } else {
            $this->elastic->indices()->create(['index' => $index]);
            $existMappingData = [];
        }

        $mappingData = $mapping->toArray();
        if (array_key_exists('properties', $existMappingData)) {
            $mappingData['properties'] = array_diff_key(
                $mappingData['properties'] ?: [],
                $existMappingData['properties']
            );
        }

        $limit = $this->getMappingTotalFieldsLimit($index);
        $need = array_sum(array_map(function (PropertyMapping $property) {
            return $property->get('type') === 'alias' ? 2 : 1;
        }, $mapping->getProperties()->getArrayCopy()));

        if ($limit < $need) {
            $this->elastic->indices()->putSettings([
                'index' => $index,
                'body' => ['index' => ['mapping' => ['total_fields' => ['limit' => $need]]]]
            ]);
        }

        $response = $this->elastic->indices()->putMapping([
            'index' => $index,
            'body' => $mappingData
        ]);

        return $response['acknowledged'] ?: false;
    }
}