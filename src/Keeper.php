<?php

namespace Sheerockoff\BitrixElastic;

use _CIBElement;
use Bitrix\Catalog\Model\Price;
use CCatalogStoreProduct;
use CIBlockElement;
use CIBlockSection;
use CModule;
use Elasticsearch\Client;
use Exception;

class Keeper
{
    /** @var Client */
    private $elastic;

    public function __construct(Client $elastic)
    {
        $this->elastic = $elastic;
    }

    public function getElementRawData(_CIBElement $element): array
    {
        $data = [];

        foreach ($element->GetFields() as $field => $value) {
            if (!strstr($field, '~')) {
                $data[$field] = $value;
            }
        }

        foreach ($element->GetProperties() as $property) {
            if ($property['PROPERTY_TYPE'] === 'L') {
                $data['PROPERTY_' . $property['CODE']] = $property['VALUE_ENUM_ID'];
                $data['PROPERTY_' . $property['CODE'] . '_VALUE'] = $property['VALUE'];
            } else {
                $data['PROPERTY_' . $property['CODE']] = $property['VALUE'];
            }
        }

        $groups = [];
        $navChain = [];
        $rs = CIBlockElement::GetElementGroups($element->fields['ID']);
        while ($group = $rs->Fetch()) {
            $groups[] = $group;
            $navChainRs = CIBlockSection::GetNavChain($group['IBLOCK_ID'], $group['ID']);
            while ($chain = $navChainRs->Fetch()) {
                $navChain[] = $chain;
            }
        }

        $data['GROUP_IDS'] = array_map(function ($group) {
            return (int)$group['ID'];
        }, $groups);

        $data['GROUP_CODES'] = array_values(array_filter(array_map(function ($group) {
            return $group['CODE'];
        }, $groups)));

        $data['NAV_CHAIN_IDS'] = array_map(function ($group) {
            return (int)$group['ID'];
        }, $navChain);

        $data['NAV_CHAIN_CODES'] = array_values(array_filter(array_map(function ($group) {
            return $group['CODE'];
        }, $navChain)));

        if (CModule::IncludeModule('catalog')) {
            $rs = CCatalogStoreProduct::GetList(null, ['PRODUCT_ID' => $element->fields['ID']]);
            while ($entry = $rs->Fetch()) {
                $data['CATALOG_STORE_AMOUNT_' . $entry['STORE_ID']] = $entry['AMOUNT'];
            }

            /** @noinspection PhpUnhandledExceptionInspection */
            $rs = Price::getList(['filter' => ['PRODUCT_ID' => $element->fields['ID']]]);
            while ($entry = $rs->fetch()) {
                $data['CATALOG_PRICE_' . $entry['CATALOG_GROUP_ID']] = $entry['PRICE'] ?? null;
                $data['CATALOG_CURRENCY_' . $entry['CATALOG_GROUP_ID']] = $entry['CURRENCY'] ?? null;
            }
        }

        return $data;
    }

    /**
     * @throws Exception
     */
    public function normalizeData(IndexMapping $mapping, array $data): array
    {
        $normalizedData = [];

        foreach ($mapping->getProperties() as $key => $propertyMapping) {
            if ($propertyMapping->get('type') === 'alias') {
                continue;
            }

            $rawValue = $data[$key] ?? null;

            if (is_array($rawValue) && array_key_exists('TEXT', $rawValue)) {
                $rawValue = $rawValue['TEXT'];
            }

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

    public function put(string $index, ?int $id, array $data): bool
    {
        $params = [
            'index' => $index,
            'id' => $id,
            'type' => '_doc',
            'body' => [
                'doc' => $data,
                'upsert' => $data
            ]
        ];

        $response = $this->elastic->update($params);

        return isset($response['result']) && in_array($response['result'], ['created', 'updated', 'noop']);
    }
}