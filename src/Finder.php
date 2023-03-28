<?php

namespace Sheerockoff\BitrixElastic;

use Elasticsearch\Client;
use Exception;
use InvalidArgumentException;
use stdClass;

class Finder
{
    private const LOGIC_AND = 'AND';
    private const LOGIC_OR = 'OR';

    /** @var Client */
    private $elastic;

    /** @var Mapper */
    private $mapper;

    /** @var bool */
    private $strictMode;

    public function __construct(Client $elastic, Mapper $mapper, $strictMode = true)
    {
        $this->elastic = $elastic;
        $this->mapper = $mapper;
        $this->strictMode = $strictMode;
    }

    /**
     * @throws Exception
     */
    public function search(string $index, array $filter, array $sort = ['SORT' => 'ASC', 'ID' => 'DESC'], array $parameters = []): array
    {
        $params = $this->prefabElasticSearchParams($index, $filter, $sort, $parameters);
        return $this->elastic->search($params);
    }

    /**
     * @throws Exception
     */
    public function prefabElasticSearchParams(string $index, array $filter, array $sort = ['SORT' => 'ASC', 'ID' => 'DESC'], array $parameters = []): array
    {
        $mapping = $this->mapper->getCachedMapping($index);
        $filter = $this->prefabFilter($filter);
        $filter = $this->normalizeFilter($mapping, $filter);
        $query = $this->prepareFilterQuery($filter);

        $params = ['index' => $index, 'body' => ['query' => $query]];
        $params['body'] = array_merge($params['body'], $this->normalizeSort($mapping, $sort));

        return array_merge($params, $parameters);
    }

    private function prefabFilter(array $filter): array
    {
        $changedFilter = $filter;
        $includeSubsections = false;
        foreach ($filter as $key => $rawValue) {
            if (!preg_match('/^(?<operator>\W*)(?<property>\w+)$/ui', $key, $matches)) {
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
            if (!preg_match('/^(?<operator>\W*)(?<property>\w+)$/ui', $key, $matches)) {
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
     * @throws InvalidArgumentException
     */
    private function normalizeFilter(IndexMapping $mapping, array $filter): array
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

            if (!preg_match('/^(?<operator>\W*)(?<property>\w+)$/ui', $k, $matches)) {
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
     * @throws InvalidArgumentException
     */
    private function normalizeSort(IndexMapping $mapping, array $sort): array
    {
        $elasticSorts = [];
        foreach ($sort as $property => $term) {
            if (!$mapping->getProperties()->offsetExists($property)) {
                if ($this->strictMode) throw new InvalidArgumentException("$property не найден в карте индекса для сортировки.");
                continue;
            }

            $missing = null;
            if (preg_match('/(?<first>nulls\s*,\s*)?(?<dir>asc|desc)(?<last>\s*,\s*nulls)?/ui', $term, $matches)) {
                $sortOrder = strtolower($matches['dir']);
                if (!empty($matches['first'])) {
                    $missing = '_first';
                } elseif (!empty($matches['last'])) {
                    $missing = '_last';
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

            if (isset($missing)) {
                $elasticSorts[] = [$sortField => ['order' => $sortOrder, 'missing' => $missing]];
            } else {
                $elasticSorts[] = [$sortField => ['order' => $sortOrder]];
            }
        }

        return $elasticSorts ? ['sort' => $elasticSorts] : [];
    }

    /**
     * @throws InvalidArgumentException
     */
    private function prepareFilterQuery(array $filter, string $logic = self::LOGIC_AND): array
    {
        if (!in_array($logic, [self::LOGIC_AND, self::LOGIC_OR], true)) {
            throw new InvalidArgumentException("Неверный логический оператор ($logic).");
        }

        $terms = [];
        foreach ($filter as $k => $value) {
            if (is_array($value) && array_key_exists('LOGIC', $value)) {
                $subFilter = $value;
                unset($subFilter['LOGIC']);
                $subQuery = $this->prepareFilterQuery($subFilter, strtoupper($value['LOGIC']));
                $terms = array_merge_recursive($terms, [($logic === self::LOGIC_OR ? 'should' : 'must') => [$subQuery]]);
            } else {
                if (!preg_match('/^(?<operator>\W*)(?<field>\w+)$/ui', $k, $matches)) {
                    if ($this->strictMode) throw new InvalidArgumentException("Неверный ключ фильтра ($k).");
                    continue;
                }

                $operator = $matches['operator'] ?: '=';
                $field = $matches['field'];

                try {
                    if ($logic === self::LOGIC_OR) {
                        $entry = $this->or($field, $operator, $value);
                    } else {
                        $entry = $this->and($field, $operator, $value);
                    }
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
     * @throws InvalidArgumentException
     */
    private function and(string $field, string $operator, $value)
    {
        $handlers = [
            '=' => function ($field, $value) {
                if ($value === false || $value === null) {
                    return ['must_not' => [['exists' => ['field' => $field]]]];
                } else {
                    return ['must' => [[(is_array($value) ? 'terms' : 'term') => [$field => $value]]]];
                }
            },
            '!' => function ($field, $value) {
                if ($value === false || $value === null) {
                    return ['must' => [['exists' => ['field' => $field]]]];
                } else {
                    return ['must_not' => [[(is_array($value) ? 'terms' : 'term') => [$field => $value]]]];
                }
            },
            '%' => function ($field, $value) {
                return ['must' => [['wildcard' => [$field => "*$value*"]]]];
            },
            '>' => function ($field, $value) {
                return ['must' => [['range' => [$field => ['gt' => $value]]]]];
            },
            '>=' => function ($field, $value) {
                return ['must' => [['range' => [$field => ['gte' => $value]]]]];
            },
            '<' => function ($field, $value) {
                return ['must' => [['range' => [$field => ['lt' => $value]]]]];
            },
            '<=' => function ($field, $value) {
                return ['must' => [['range' => [$field => ['lte' => $value]]]]];
            },
            '><' => function ($field, $value) {
                if (!isset($value[0]) || !isset($value[1])) {
                    throw new InvalidArgumentException("Для фильтра ><$field должен быть указан массив значений.");
                }

                return ['must' => [['range' => [$field => ['gte' => min($value), 'lte' => max($value)]]]]];
            }
        ];

        if (!array_key_exists($operator, $handlers)) {
            throw new InvalidArgumentException("Невозможно отфильтровать $field по оператору $operator.");
        }

        return $handlers[$operator]($field, $value);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function or($field, $operator, $value)
    {
        $handlers = [
            '=' => function ($field, $value) {
                if ($value === false || $value === null) {
                    return ['should' => [['bool' => ['must_not' => [['exists' => ['field' => $field]]]]]]];
                } else {
                    return ['should' => [[(is_array($value) ? 'terms' : 'term') => [$field => $value]]]];
                }
            },
            '!' => function ($field, $value) {
                if ($value === false || $value === null) {
                    return ['should' => [['exists' => ['field' => $field]]]];
                } else {
                    return ['should' => [['bool' => ['must_not' => [[(is_array($value) ? 'terms' : 'term') => [$field => $value]]]]]]];
                }
            },
            '%' => function ($field, $value) {
                return ['should' => [['wildcard' => [$field => "*$value*"]]]];
            },
            '>' => function ($field, $value) {
                return ['should' => [['range' => [$field => ['gt' => $value]]]]];
            },
            '>=' => function ($field, $value) {
                return ['should' => [['range' => [$field => ['gte' => $value]]]]];
            },
            '<' => function ($field, $value) {
                return ['should' => [['range' => [$field => ['lt' => $value]]]]];
            },
            '<=' => function ($field, $value) {
                return ['should' => [['range' => [$field => ['lte' => $value]]]]];
            },
            '><' => function ($field, $value) {
                if (!isset($value[0]) || !isset($value[1])) {
                    throw new InvalidArgumentException("Для фильтра ><$field должен быть указан массив значений.");
                }

                return ['should' => [['range' => [$field => ['gte' => min($value), 'lte' => max($value)]]]]];
            }
        ];

        if (!array_key_exists($operator, $handlers)) {
            throw new InvalidArgumentException("Невозможно отфильтровать $field по оператору $operator.");
        }

        return $handlers[$operator]($field, $value);
    }
}