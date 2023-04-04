<?php

namespace Sheerockoff\BitrixElastic;

use ArrayObject;
use InvalidArgumentException;
use JsonSerializable;

class IndexMapping implements JsonSerializable
{
    /** @var ArrayObject|PropertyMapping[] */
    private $properties;

    public function __construct()
    {
        $this->properties = new ArrayObject();
    }

    /**
     * @return ArrayObject|PropertyMapping[]
     */
    public function getProperties(): ArrayObject
    {
        return $this->properties;
    }

    public function setProperty(string $code, PropertyMapping $propertyMapping): void
    {
        $this->properties[$code] = $propertyMapping;
    }

    public function getProperty(string $code): PropertyMapping
    {
        $property = $this->properties[$code] ?? null;
        if ($property instanceof PropertyMapping) {
            return $property;
        } else {
            throw new InvalidArgumentException(sprintf('Свойство "%s" не найдено в карте индекса.', $code));
        }
    }

    public function normalizePropertyCode(string $code): string
    {
        if (array_key_exists($code, $this->properties->getArrayCopy())) {
            return $code;
        }

        foreach (array_keys($this->properties->getArrayCopy()) as $existCode) {
            if (strtolower($existCode) === strtolower($code)) {
                return $existCode;
            }
        }

        return $code;
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
        return [
            'properties' => $this->properties->getArrayCopy()
        ];
    }

    public function toArray(): array
    {
        return json_decode(json_encode($this), true);
    }
}