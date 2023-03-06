<?php

namespace Sheerockoff\BitrixElastic;

use _CIBElement;
use CCatalogGroup;
use CCatalogStore;
use CCatalogStoreProduct;
use CIBlockElement;
use CIBlockProperty;
use CIBlockSection;
use CModule;
use CPrice;
use Elasticsearch\Client;
use Exception;
use InvalidArgumentException;
use stdClass;

class Indexer
{
    private $elastic;
    private $strictMode;

    private $runtimeCache = [];

    public function __construct(Client $elastic, $strictMode = true)
    {
        $this->elastic = $elastic;
        $this->strictMode = $strictMode;
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

    /**
     * @param string $index
     * @return IndexMapping
     */
    public function getMapping(string $index)
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

        $this->runtimeCache['mapping'][$index] = $mapping;

        return $mapping;
    }

    public function getCachedMapping(string $index)
    {
        if (array_key_exists($index, $this->runtimeCache['mapping'] ?? [])) {
            return $this->runtimeCache['mapping'][$index];
        } else {
            return $this->getMapping($index);
        }
    }

    /**
     * @param string $index
     * @param IndexMapping $mapping
     * @return bool
     */
    public function putMapping(string $index, IndexMapping $mapping)
    {
        if (array_key_exists($index, $this->runtimeCache['mapping'] ?? [])) {
            unset($this->runtimeCache['mapping'][$index]);
        }

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

        $limit = $this->getMappingTotalFieldsLimit($index);
        $need = array_sum(array_map(function (PropertyMapping $property) {
            return $property->get('type') === 'alias' ? 2 : 1;
        }, $mapping->getProperties()->getArrayCopy()));

        if ($limit < $need) {
            $this->getElastic()->indices()->putSettings([
                'index' => $index,
                'body' => ['index' => ['mapping' => ['total_fields' => ['limit' => $need]]]]
            ]);
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
    public function getElementRawData(_CIBElement $element)
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

    /**
     * @param string $index
     * @param int|null $id
     * @param array $data
     * @return bool
     */
    public function put(string $index, ?int $id, array $data)
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

        $response = $this->getElastic()->update($params);

        return isset($response['result']) && in_array($response['result'], ['created', 'updated', 'noop']);
    }

    /**
     * @param string $index
     * @param array $filter
     * @param array $sort
     * @param array $parameters
     * @return array
     */
    public function search(string $index, array $filter, array $sort = ['SORT' => 'ASC', 'ID' => 'DESC'], array $parameters = [])
    {
        $params = $this->prefabElasticSearchParams($index, $filter, $sort, $parameters);
        return $this->getElastic()->search($params);
    }

    public function prefabElasticSearchParams(string $index, array $filter, array $sort = ['SORT' => 'ASC', 'ID' => 'DESC'], array $parameters = [])
    {
        $mapping = $this->getCachedMapping($index);
        $filter = $this->prefabFilter($filter);
        $filter = $this->normalizeFilter($mapping, $filter);
        $query = $this->prepareFilterQuery($filter);

        $params = ['index' => $index, 'body' => ['query' => $query]];
        $params['body'] = array_merge($params['body'], $this->normalizeSort($mapping, $sort));
        $params = array_merge($params, $parameters);

        return $params;
    }

    /**
     * @param array $filter
     * @return array
     */
    private function prefabFilter(array $filter)
    {
        $changedFilter = $filter;
        $includeSubsections = false;
        foreach ($filter as $key => $rawValue) {
            if (!preg_match('/^(?<operator>\W*)(?<property>\w+)$/uis', $key, $matches)) {
                continue;
            }

            if ($matches['property'] !== 'INCLUDE_SUBSECTIONS') {
                continue;
            }

            $includeSubsections = $rawValue && $rawValue !== 'N';
            if (array_key_exists($key, $changedFilter)) {
                unset($changedFilter[$key]);
            }

            break;
        }

        foreach ($filter as $key => $rawValue) {
            if (!preg_match('/^(?<operator>\W*)(?<property>\w+)$/uis', $key, $matches)) {
                continue;
            }

            if ($matches['property'] === 'IBLOCK_SECTION_ID' || $matches['property'] === 'SECTION_ID') {
                if (array_key_exists($key, $changedFilter)) {
                    unset($changedFilter[$key]);
                }

                if ($includeSubsections) {
                    $changedFilter['NAV_CHAIN_IDS'] = $rawValue;
                } else {
                    $changedFilter['GROUP_IDS'] = $rawValue;
                }
            } elseif ($matches['property'] === 'SECTION_CODE') {
                if (array_key_exists($key, $changedFilter)) {
                    unset($changedFilter[$key]);
                }

                if ($includeSubsections) {
                    $changedFilter['NAV_CHAIN_CODES'] = $rawValue;
                } else {
                    $changedFilter['GROUP_CODES'] = $rawValue;
                }
            }
        }

        return $changedFilter;
    }

    /**
     * @param IndexMapping $mapping
     * @param array $filter
     * @return array
     */
    private function normalizeFilter(IndexMapping $mapping, array $filter)
    {
        $normalizedFilter = [];
        foreach ($filter as $k => $v) {
            if (is_array($v) && array_key_exists('LOGIC', $v)) {
                $subFilter = $v;
                unset($subFilter['LOGIC']);
                $subFilter = $this->normalizeFilter($mapping, $subFilter);
                $normalizedFilter[$k] = array_merge(['LOGIC' => $v['LOGIC']], $subFilter);
                continue;
            }

            if (!preg_match('/^(?<operator>\W*)(?<property>\w+)$/uis', $k, $matches)) {
                if ($this->strictMode) throw new InvalidArgumentException("Неверный ключ фильтра ($k).");
                continue;
            }

            $property = $matches['property'];

            if (!$mapping->getProperties()->offsetExists($property)) {
                if ($this->strictMode) throw new InvalidArgumentException("$property не найден в карте индекса.");
                continue;
            }

            $isAlias = $mapping->getProperty($property)->get('type') === 'alias';
            if ($isAlias) {
                $aliasPath = $mapping->getProperty($property)->get('path');
                if (!$aliasPath) {
                    if ($this->strictMode) throw new InvalidArgumentException("В $property типа alias не указан path.");
                    continue;
                }

                if (!$mapping->getProperties()->offsetExists($aliasPath)) {
                    if ($this->strictMode) {
                        throw new InvalidArgumentException(
                            "$property типа alias указывает на $aliasPath, который не найден в карте индекса."
                        );
                    }

                    continue;
                }

                $property = $aliasPath;
            }

            if (is_array($v)) {
                $value = array_map(function ($v) use ($mapping, $property) {
                    return $mapping->getProperty($property)->normalizeValue($v);
                }, $v);
            } else {
                $value = $mapping->getProperty($property)->normalizeValue($v);
            }

            $normalizedFilter[$k] = $value;
        }

        return $normalizedFilter;
    }

    /**
     * @param IndexMapping $mapping
     * @param array $sort
     * @return array
     */
    private function normalizeSort(IndexMapping $mapping, array $sort)
    {
        $elasticSorts = [];
        foreach ($sort as $property => $term) {
            if (!$mapping->getProperties()->offsetExists($property)) {
                if ($this->strictMode) throw new InvalidArgumentException("$property не найден в карте индекса для сортировки.");
                continue;
            }

            $sortOrder = 'asc';
            $emptySort = null;
            if (preg_match('/(?<first>nulls\s*,\s*)?(?<dir>asc|desc)(?<last>\s*,\s*nulls)?/ui', $term, $matches)) {
                $sortOrder = strtolower($matches['dir']);
                if (!empty($matches['first'])) {
                    $emptySort = 'asc';
                } elseif (!empty($matches['last'])) {
                    $emptySort = 'desc';
                }
            } else {
                if ($this->strictMode) throw new InvalidArgumentException("Неверный формат сортировки ($term).");
                continue;
            }

            $propMap = $mapping->getProperty($property);
            if (isset($propMap->getData()['fields']['enum'])) {
                $sortField = $property . '_VALUE';
            } else {
                $sortField = $property;
            }

            if ($emptySort) {
                $emptyValuesForTypes = [
                    'integer' => '0',
                    'long' => '0',
                    'float' => '0.0',
                    'double' => '0.0',
                    'date' => 'null',
                    'boolean' => 'false',
                    'string' => '',
                ];

                $elasticSorts[] = [
                    '_script' => [
                        'type' => 'number',
                        'script' => [
                            'lang' => 'painless',
                            'source' => sprintf(
                                "if (doc['%s'].empty || doc['%s'].value == %s) { return 0; } else { return 1; }",
                                $sortField,
                                $sortField,
                                $emptyValuesForTypes[$propMap->get('type')] ?? 'null'
                            )
                        ],
                        'order' => $emptySort
                    ]
                ];
            }

            $elasticSorts[] = [$sortField => ['order' => $sortOrder]];
        }

        return $elasticSorts ? ['sort' => $elasticSorts] : [];
    }

    /**
     * @param array $filter
     * @param string $condition must|should
     * @return array
     * @throws Exception
     */
    private function prepareFilterQuery(array $filter, $condition = 'must')
    {
        $operatorMap = [
            '' => function ($k, $v, $condition = 'must') {
                $query = is_array($v) ? 'terms' : 'term';
                return [$condition => [[$query => [$k => $v]]]];
            },
            '=' => function ($k, $v, $condition = 'must') {
                $query = is_array($v) ? 'terms' : 'term';
                return [$condition => [[$query => [$k => $v]]]];
            },
            '!' => function ($k, $v, $condition = 'must') {
                $query = is_array($v) ? 'terms' : 'term';
                return ["{$condition}_not" => [[$query => [$k => $v]]]];
            },
            '%' => function ($k, $v, $condition = 'must') {
                return [$condition => [['wildcard' => [$k => "*$v*"]]]];
            },
            '>' => function ($k, $v, $condition = 'must') {
                return [$condition => [['range' => [$k => ['gt' => $v]]]]];
            },
            '>=' => function ($k, $v, $condition = 'must') {
                return [$condition => [['range' => [$k => ['gte' => $v]]]]];
            },
            '<' => function ($k, $v, $condition = 'must') {
                return [$condition => [['range' => [$k => ['lt' => $v]]]]];
            },
            '<=' => function ($k, $v, $condition = 'must') {
                return [$condition => [['range' => [$k => ['lte' => $v]]]]];
            },
            '><' => function ($k, $v, $condition = 'must') {
                if (!isset($v[0]) || !isset($v[1])) {
                    throw new InvalidArgumentException("Для фильтра ><$k должен быть указан массив значений.");
                }

                return [$condition => [['range' => [$k => ['gte' => $v[0], 'lte' => $v[1]]]]]];
            }
        ];

        $terms = [];
        foreach ($filter as $k => $value) {
            if (is_array($value) && array_key_exists('LOGIC', $value)) {
                $subFilter = $value;
                unset($subFilter['LOGIC']);
                $subQuery = $this->prepareFilterQuery($subFilter, strtoupper($value['LOGIC']) === 'OR' ? 'should' : 'must');

                $terms = array_merge_recursive($terms, [$condition => [$subQuery]]);
            } else {
                if (!preg_match('/^(?<operator>\W*)(?<property>\w+)$/uis', $k, $matches)) {
                    if ($this->strictMode) throw new InvalidArgumentException("Неверный ключ фильтра ($k).");
                    continue;
                }

                $operator = $matches['operator'];
                $property = $matches['property'];

                if (!array_key_exists($operator, $operatorMap)) {
                    if ($this->strictMode) throw new InvalidArgumentException("Невозможно отфильтровать $property по оператору $operator.");
                    continue;
                }

                try {
                    $entry = call_user_func($operatorMap[$operator], $property, $value, $condition);
                } catch (Exception $exception) {
                    if ($this->strictMode) throw $exception;
                    continue;
                }

                $terms = array_merge_recursive($terms, $entry);
            }
        }

        return $terms ? ['bool' => $terms] : ['match_all' => new stdClass()];
    }

    /**
     * @param string $index
     * @return int|null
     */
    private function getMappingTotalFieldsLimit($index)
    {
        $response = $this->getElastic()->indices()->getSettings([
            'index' => $index,
            'include_defaults' => true,
        ]);

        $setting = $response[$index]['settings']['index']['mapping']['total_fields']['limit'] ?? null;
        $default = $response[$index]['defaults']['index']['mapping']['total_fields']['limit'] ?? null;

        return $setting ?? $default;
    }
}