<?php

namespace Sheerockoff\BitrixElastic;

use _CIBElement;
use CCatalogGroup;
use CCatalogStore;
use CCatalogStoreProduct;
use CIBlockProperty;
use CModule;
use CPrice;
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

    /**
     * @param int $infoBlockId
     * @return IndexMapping
     */
    public function getInfoBlockMapping(int $infoBlockId)
    {
        $mapping = new IndexMapping();

        foreach (array_keys(PropertyMapping::$bitrixFieldTypesMap) as $field) {
            $mapping->setProperty($field, PropertyMapping::fromBitrixField($field));
        }

        $rs = CIBlockProperty::GetList(null, ['IBLOCK_ID' => $infoBlockId]);
        while ($property = $rs->Fetch()) {
            $mapping->setProperty(
                'PROPERTY_' . $property['CODE'],
                PropertyMapping::fromBitrixProperty($property)
            );
        }

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

    /**
     * @param string $index
     * @return IndexMapping
     */
    public function getIndexMapping(string $index)
    {

        if ($this->getElastic()->indices()->exists(['index' => $index])) {
            $response = $this->getElastic()->indices()->getMapping(['index' => $index]);
            $mappingData = $response[$index]['mappings'] ?: [];
        } else {
            $mappingData = [];
        }

        $mapping = new IndexMapping();
        foreach ($mappingData['properties'] ?? [] as $property => $propertyData) {
            $mapping->setProperty($property, new PropertyMapping($propertyData['type'] ?: null, $propertyData));
        }

        return $mapping;
    }

    /**
     * @param string $index
     * @param IndexMapping $mapping
     * @return bool
     */
    public function putIndexMapping(string $index, IndexMapping $mapping)
    {
        if ($this->getElastic()->indices()->exists(['index' => $index])) {
            $response = $this->getElastic()->indices()->getMapping(['index' => $index]);
            $existMappingData = $response[$index]['mappings'] ?: [];
        } else {
            $this->getElastic()->indices()->create(['index' => $index]);
            $existMappingData = [];
        }

        $mappingData = $mapping->toArray();
        if (array_key_exists('properties', $existMappingData)) {
            $mappingData['properties'] = array_diff_key(
                $mappingData['properties'] ?: [],
                $existMappingData['properties']
            );
        }

        $response = $this->getElastic()->indices()->putMapping([
            'index' => $index,
            'body' => $mappingData
        ]);

        return $response['acknowledged'] ?: false;
    }

    /**
     * @param _CIBElement $element
     * @return array
     */
    public function getElementIndexData(_CIBElement $element)
    {
        $data = [];

        foreach ($element->GetFields() as $field => $value) {
            if (!strstr($field, '~')) {
                $data[$field] = $value;
            }
        }

        foreach ($element->GetProperties() as $property) {
            $data['PROPERTY_' . $property['CODE']] = $property['VALUE'];
        }

        if (CModule::IncludeModule('catalog')) {
            $rs = CCatalogStoreProduct::GetList(null, ['PRODUCT_ID' => $element->fields['ID']]);
            while ($entry = $rs->Fetch()) {
                $data['CATALOG_STORE_AMOUNT_' . $entry['STORE_ID']] = $entry['AMOUNT'];
            }

            $rs = CPrice::GetList(null, ['PRODUCT_ID' => $element->fields['ID']]);
            while ($entry = $rs->Fetch()) {
                $data['CATALOG_PRICE_' . $entry['CATALOG_GROUP_ID']] = $entry['PRICE'];
                $data['CATALOG_CURRENCY_' . $entry['CATALOG_GROUP_ID']] = $entry['CURRENCY'];
            }
        }

        return $data;
    }

    /**
     * @param IndexMapping $mapping
     * @param array $data
     * @return array
     */
    public function normalizeData(IndexMapping $mapping, array $data)
    {
        $normalizedData = [];

        foreach ($mapping->getProperties() as $key => $propertyMapping) {
            if ($propertyMapping->get('type') === 'alias') {
                continue;
            }

            $rawValue = $data[$key] ?: null;
            if (is_array($rawValue)) {
                $value = array_map(function ($v) use ($propertyMapping) {
                    return $propertyMapping->normalizeValue($v);
                }, $rawValue);
            } else {
                $value = $propertyMapping->normalizeValue($rawValue);
            }

            $normalizedData[$key] = $value;
        }

        return $normalizedData;
    }
}