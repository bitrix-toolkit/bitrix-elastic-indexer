<?php

namespace Sheerockoff\BitrixElastic;

use ArrayObject;
use JsonSerializable;

class IndexMapping implements JsonSerializable
{
    /** @var ArrayObject<PropertyMapping> */
    private $properties;

    public function __construct()
    {
        $this->properties = new ArrayObject();
    }

    /**
     * @return ArrayObject<PropertyMapping>
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
        return $this->properties[$code];
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