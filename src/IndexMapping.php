<?php

namespace Sheerockoff\BitrixElastic;

use ArrayObject;
use JsonSerializable;

class IndexMapping implements JsonSerializable
{
    /**
     * @var ArrayObject
     */
    private $properties;

    public function __construct()
    {
        $this->properties = new ArrayObject();
    }

    /**
     * @return ArrayObject|PropertyMapping[]
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * @param string $code
     * @param PropertyMapping $propertyMapping
     */
    public function setProperty(string $code, PropertyMapping $propertyMapping)
    {
        $this->properties[$code] = $propertyMapping;
    }

    /**
     * @param string $code
     * @return PropertyMapping
     */
    public function getProperty(string $code)
    {
        return $this->properties[$code];
    }

    /**
     * Specify data which should be serialized to JSON
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return [
            'properties' => $this->properties->getArrayCopy()
        ];
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return json_decode(json_encode($this), true);
    }
}