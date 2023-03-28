<?php

namespace Sheerockoff\BitrixElastic;

use ArrayObject;
use Bitrix\Main\Type\DateTime as BitrixDateTime;
use DateTime;
use InvalidArgumentException;
use JsonSerializable;

class PropertyMapping implements JsonSerializable
{
    public static $bitrixFieldTypesMap = [
        'LID' => ['keyword'],
        'IBLOCK_TYPE_ID' => ['keyword'],
        'IBLOCK_ID' => ['integer'],
        'IBLOCK_CODE' => ['keyword'],
        'IBLOCK_NAME' => ['keyword'],
        'ID' => ['integer'],
        'XML_ID' => ['keyword'],
        'EXTERNAL_ID' => ['alias', ['path' => 'XML_ID']],
        'CODE' => ['keyword'],
        'NAME' => ['keyword'],
        'ACTIVE' => ['boolean'],
        'DETAIL_PAGE_URL' => ['keyword'],
        'LIST_PAGE_URL' => ['keyword'],
        'TIMESTAMP_X' => ['date', ['format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd']],
        'DATE_CREATE' => ['date', ['format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd']],
        'IBLOCK_SECTION_ID' => ['integer'],
        'SECTION_ID' => ['alias', ['path' => 'IBLOCK_SECTION_ID']],
        'SECTION_CODE' => ['keyword'],
        'ACTIVE_FROM' => ['date', ['format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd']],
        'ACTIVE_TO' => ['date', ['format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd']],
        'SORT' => ['integer'],
        'PREVIEW_PICTURE' => ['integer'],
        'PREVIEW_TEXT' => ['keyword'],
        'PREVIEW_TEXT_TYPE' => ['keyword'],
        'DETAIL_PICTURE' => ['integer'],
        'DETAIL_TEXT' => ['keyword'],
        'DETAIL_TEXT_TYPE' => ['keyword'],
        'SEARCHABLE_CONTENT' => ['keyword'],
        'TAGS' => ['keyword'],
    ];

    /** @var ArrayObject */
    private $data;

    public function __construct(string $type = 'keyword', array $parameters = [])
    {
        $this->data = new ArrayObject($parameters);
        $this->data['type'] = $type;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function fromBitrixProperty(array $property): self
    {
        if (empty($property['PROPERTY_TYPE'])) {
            throw new InvalidArgumentException('PROPERTY_TYPE должен быть определён в массиве $property.');
        }

        $bitrixPropertyType = $property['PROPERTY_TYPE'];
        $bitrixUserType = $property['USER_TYPE'] ?: null;

        if ($bitrixPropertyType === 'S' && $bitrixUserType === 'DateTime') {
            $indexType = 'date';
            $parameters = ['format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd'];
        } elseif ($bitrixPropertyType === 'N') {
            $indexType = 'float';
        } elseif ($bitrixPropertyType === 'E' || $bitrixPropertyType === 'F') {
            $indexType = 'integer';
        } else {
            $indexType = 'keyword';
        }

        return new self($indexType, $parameters ?? []);
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function fromBitrixField(string $field): self
    {
        if (!array_key_exists($field, self::$bitrixFieldTypesMap)) {
            throw new InvalidArgumentException('Для поля ' . $field . ' не предопределён тип.');
        }

        $indexType = self::$bitrixFieldTypesMap[$field][0];
        $indexParameters = self::$bitrixFieldTypesMap[$field][1] ?? [];
        return new self($indexType, $indexParameters);
    }

    public function set(string $parameter, $value): void
    {
        $this->data[$parameter] = $value;
    }

    /**
     * @return mixed
     */
    public function get(string $parameter)
    {
        return $this->data[$parameter];
    }

    public function getData(): ArrayObject
    {
        return $this->data;
    }

    /**
     * @throws InvalidArgumentException
     * @return mixed
     */
    public function normalizeValue($value)
    {
        $map = [
            'keyword' => function ($value) {
                return $value !== null && $value !== false ? strval($value) : null;
            },
            'text' => function ($value) {
                return $value !== null && $value !== false ? strval($value) : null;
            },
            'integer' => function ($value) {
                return is_numeric($value) ? intval($value) : null;
            },
            'long' => function ($value) {
                return is_numeric($value) ? intval($value) : null;
            },
            'float' => function ($value) {
                return is_numeric($value) ? floatval($value) : null;
            },
            'double' => function ($value) {
                return is_numeric($value) ? floatval($value) : null;
            },
            'boolean' => function ($value) {
                return $value !== null ? $value && $value !== 'N' : null;
            },
            'date' => function ($value) {
                if (empty($value)) {
                    return null;
                }

                $format = 'Y-m-d H:i:s';
                if (is_string($value) && preg_match('/^\d{2,4}[-.]\d{2}[-.]\d{2,4}$/ui', $value)) {
                    $format = 'Y-m-d';
                }

                if ($value instanceof BitrixDateTime) {
                    return $value->format($format);
                } elseif ($value instanceof DateTime) {
                    return $value->format($format);
                } elseif (preg_match('/^-?\d+$/u', (string)$value)) {
                    return date($format, $value);
                } else {
                    return (new DateTime($value))->format($format);
                }
            },
            'alias' => function () {
                throw new InvalidArgumentException('Свойства типа alias не должны передаваться.');
            },
        ];

        return array_key_exists($this->get('type'), $map) ? call_user_func($map[$this->get('type')], $value) : $value;
    }

    /**
     * Specify data which should be serialized to JSON
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     * @noinspection PhpReturnDocTypeMismatchInspection
     */
    public function jsonSerialize()
    {
        return $this->data->getArrayCopy();
    }

    public function toArray(): array
    {
        return json_decode(json_encode($this), true);
    }
}