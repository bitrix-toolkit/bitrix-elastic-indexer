<?php

/** @noinspection PhpParamsInspection */

namespace Sheerockoff\BitrixElastic\Test;

use Bitrix\Main\Type\DateTime as BitrixDateTime;
use DateTime;
use InvalidArgumentException;
use Sheerockoff\BitrixElastic\PropertyMapping;

class PropertyMappingTest extends TestCase
{
    public function testCanCreateFromBitrixProperty()
    {
        $bitrixProp = ['PROPERTY_TYPE' => 'S', 'USER_TYPE' => 'DateTime'];
        $propertyMap = PropertyMapping::fromBitrixProperty($bitrixProp);
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('date', $propertyMap->get('type'));

        $bitrixProp = ['PROPERTY_TYPE' => 'S', 'USER_TYPE' => false];
        $propertyMap = PropertyMapping::fromBitrixProperty($bitrixProp);
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('keyword', $propertyMap->get('type'));

        $bitrixProp = ['PROPERTY_TYPE' => 'N', 'USER_TYPE' => false];
        $propertyMap = PropertyMapping::fromBitrixProperty($bitrixProp);
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('float', $propertyMap->get('type'));

        $bitrixProp = ['PROPERTY_TYPE' => 'L', 'USER_TYPE' => false];
        $propertyMap = PropertyMapping::fromBitrixProperty($bitrixProp);
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('keyword', $propertyMap->get('type'));

        $bitrixProp = ['PROPERTY_TYPE' => 'E', 'USER_TYPE' => false];
        $propertyMap = PropertyMapping::fromBitrixProperty($bitrixProp);
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('integer', $propertyMap->get('type'));

        $bitrixProp = ['PROPERTY_TYPE' => 'F', 'USER_TYPE' => false];
        $propertyMap = PropertyMapping::fromBitrixProperty($bitrixProp);
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('integer', $propertyMap->get('type'));

        $bitrixProp = [];
        $this->expectException(InvalidArgumentException::class);
        PropertyMapping::fromBitrixProperty($bitrixProp);
    }

    public function testCanCreateFromBitrixField()
    {
        $propertyMap = PropertyMapping::fromBitrixField('LID');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('keyword', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('IBLOCK_TYPE_ID');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('keyword', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('IBLOCK_ID');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('integer', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('IBLOCK_CODE');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('keyword', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('IBLOCK_NAME');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('keyword', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('ID');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('integer', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('XML_ID');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('keyword', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('EXTERNAL_ID');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('alias', $propertyMap->get('type'));
        $this->assertEquals('XML_ID', $propertyMap->get('path'));

        $propertyMap = PropertyMapping::fromBitrixField('CODE');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('keyword', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('NAME');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('text', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('ACTIVE');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('boolean', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('DETAIL_PAGE_URL');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('keyword', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('LIST_PAGE_URL');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('keyword', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('TIMESTAMP_X');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('date', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('DATE_CREATE');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('date', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('IBLOCK_SECTION_ID');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('integer', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('ACTIVE_FROM');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('date', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('ACTIVE_TO');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('date', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('SORT');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('integer', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('PREVIEW_PICTURE');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('integer', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('PREVIEW_TEXT');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('text', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('PREVIEW_TEXT_TYPE');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('keyword', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('DETAIL_PICTURE');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('integer', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('DETAIL_TEXT');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('text', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('DETAIL_TEXT_TYPE');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('keyword', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('SEARCHABLE_CONTENT');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('text', $propertyMap->get('type'));

        $propertyMap = PropertyMapping::fromBitrixField('TAGS');
        $this->assertInstanceOf(PropertyMapping::class, $propertyMap);
        $this->assertEquals('keyword', $propertyMap->get('type'));

        $this->expectException(InvalidArgumentException::class);
        PropertyMapping::fromBitrixField('UNDEFINED_FIELD');
    }

    public function testCanSetData()
    {
        $propertyMap = new PropertyMapping();

        $propertyMap->set('type', 'boolean');
        $this->assertEquals('boolean', $propertyMap->get('type'));

        $propertyMap->getData()['type'] = 'keyword';
        $this->assertEquals('keyword', $propertyMap->getData()['type']);
    }

    public function testCanNormalizeValue()
    {
        $propertyMap = new PropertyMapping('keyword');
        $this->assertSame('100', $propertyMap->normalizeValue(100));

        $propertyMap = new PropertyMapping('text');
        $this->assertSame('200', $propertyMap->normalizeValue(200));

        $propertyMap = new PropertyMapping('integer');
        $this->assertSame(99, $propertyMap->normalizeValue(99.00));

        $propertyMap = new PropertyMapping('long');
        $this->assertSame(99, $propertyMap->normalizeValue('99'));

        $propertyMap = new PropertyMapping('float');
        $this->assertSame(99.00, $propertyMap->normalizeValue(99));

        $propertyMap = new PropertyMapping('double');
        $this->assertSame(99.00, $propertyMap->normalizeValue('99.0'));

        $propertyMap = new PropertyMapping('boolean');
        $this->assertSame(true, $propertyMap->normalizeValue('Y'));
        $this->assertSame(true, $propertyMap->normalizeValue(1));
        $this->assertSame(true, $propertyMap->normalizeValue(true));
        $this->assertSame(false, $propertyMap->normalizeValue('N'));
        $this->assertSame(false, $propertyMap->normalizeValue(0));
        $this->assertSame(false, $propertyMap->normalizeValue(false));

        $propertyMap = new PropertyMapping('date');
        $this->assertSame('2019-06-14 12:30:01', $propertyMap->normalizeValue('14.06.2019 12:30:01'));
        $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', '2019-06-14 12:30:01');
        $this->assertSame('2019-06-14 12:30:01', $propertyMap->normalizeValue($dateTime));
        $bitrixDateTime = BitrixDateTime::createFromPhp($dateTime);
        $this->assertSame('2019-06-14 12:30:01', $propertyMap->normalizeValue($bitrixDateTime));
        $timestamp = $dateTime->getTimestamp();
        $this->assertSame('2019-06-14 12:30:01', $propertyMap->normalizeValue($timestamp));

        $propertyMap = new PropertyMapping('alias', ['path' => 'another_prop']);
        $this->expectException(InvalidArgumentException::class);
        $propertyMap->normalizeValue('some value');
    }
}