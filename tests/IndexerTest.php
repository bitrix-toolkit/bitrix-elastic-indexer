<?php

/** @noinspection PhpParamsInspection */

namespace Sheerockoff\BitrixElastic\Test;

use _CIBElement;
use CCatalog;
use CCatalogGroup;
use CCatalogStore;
use CCatalogStoreProduct;
use CIBlock;
use CIBlockElement;
use CIBlockProperty;
use CIBlockType;
use CPrice;
use Elasticsearch\Client;
use Sheerockoff\BitrixElastic\Indexer;
use Sheerockoff\BitrixElastic\IndexMapping;
use Sheerockoff\BitrixElastic\PropertyMapping;

class IndexerTest extends TestCase
{
    public static function setUpBeforeClass()
    {
        global $APPLICATION;

        $cIBlockType = new CIBlockType();
        $isTypeAdded = $cIBlockType->Add([
            'ID' => 'elastic_test',
            'SECTIONS' => 'Y',
            'IN_RSS' => 'N',
            'LANG' => [
                'ru' => [
                    'NAME' => 'Тестирование BitrixElastic'
                ]
            ]
        ]);

        self::assertNotEmpty($isTypeAdded, $cIBlockType->LAST_ERROR);

        $cIBlock = new CIBlock();
        $iBlockId = $cIBlock->Add([
            'LID' => SITE_ID,
            'CODE' => 'PRODUCTS',
            'IBLOCK_TYPE_ID' => 'elastic_test',
            'NAME' => 'Тестовые товары',
            'GROUP_ID' => ['1' => 'X', '2' => 'W']
        ]);

        self::assertNotEmpty($iBlockId, $cIBlock->LAST_ERROR);

        $isCatalogAdded = CCatalog::Add([
            'IBLOCK_ID' => $iBlockId,
            'YANDEX_EXPORT' => 'N'
        ]);

        self::assertNotEmpty($isCatalogAdded, $APPLICATION->GetException());

        $mainStoreId = CCatalogStore::Add([
            'TITLE' => 'Основной склад',
            'XML_ID' => 'MAIN',
            'ACTIVE' => 'Y',
            'ADDRESS' => '',
            'DESCRIPTION' => ''
        ]);

        self::assertNotEmpty($mainStoreId, $APPLICATION->GetException());

        $secondaryStoreId = CCatalogStore::Add([
            'TITLE' => 'Запасной склад',
            'XML_ID' => 'SECONDARY',
            'ACTIVE' => 'Y',
            'ADDRESS' => '',
            'DESCRIPTION' => ''
        ]);

        self::assertNotEmpty($secondaryStoreId, $APPLICATION->GetException());

        $basePriceType = CCatalogGroup::GetBaseGroup();
        self::assertTrue(isset($basePriceType['ID']));

        $cIBlockProperty = new CIBlockProperty();
        $isPropAdded = $cIBlockProperty->Add([
            'IBLOCK_ID' => $iBlockId,
            'CODE' => 'COLOR',
            'NAME' => 'Цвет',
            'MULTIPLE' => 'N',
            'PROPERTY_TYPE' => 'L',
            'VALUES' => [
                ['XML_ID' => 'BLACK', 'VALUE' => 'Black', 'DEF' => 'N'],
                ['XML_ID' => 'SILVER', 'VALUE' => 'Silver', 'DEF' => 'N'],
                ['XML_ID' => 'BLUE', 'VALUE' => 'Blue', 'DEF' => 'N'],
                ['XML_ID' => 'RED', 'VALUE' => 'Red', 'DEF' => 'N'],
            ]
        ]);

        self::assertNotEmpty($isPropAdded, $cIBlockProperty->LAST_ERROR);

        $colorValues = [];
        $rs = CIBlockProperty::GetPropertyEnum('COLOR');
        while ($entry = $rs->Fetch()) {
            $colorValues[$entry['VALUE']] = $entry;
        }

        self::assertTrue(!empty($colorValues));

        $cIBlockProperty = new CIBlockProperty();
        $isPropAdded = $cIBlockProperty->Add([
            'IBLOCK_ID' => $iBlockId,
            'CODE' => 'OLD_PRICE',
            'NAME' => 'Старая цена',
            'MULTIPLE' => 'N',
            'PROPERTY_TYPE' => 'N'
        ]);

        self::assertNotEmpty($isPropAdded, $cIBlockProperty->LAST_ERROR);

        $cIBlockProperty = new CIBlockProperty();
        $isPropAdded = $cIBlockProperty->Add([
            'IBLOCK_ID' => $iBlockId,
            'CODE' => 'TAGS',
            'NAME' => 'Теги',
            'MULTIPLE' => 'Y',
            'PROPERTY_TYPE' => 'S'
        ]);

        self::assertNotEmpty($isPropAdded, $cIBlockProperty->LAST_ERROR);

        $csv = fopen(__DIR__ . '/products.csv', 'r');
        $keys = fgetcsv($csv);

        $cIBlockElement = new CIBlockElement();

        $elementIds = [];
        while (!feof($csv)) {
            $row = array_combine($keys, fgetcsv($csv));
            $elementId = $cIBlockElement->Add([
                'IBLOCK_ID' => $iBlockId,
                'NAME' => $row['NAME'],
                'PROPERTY_VALUES' => [
                    'COLOR' => $colorValues[$row['COLOR']]['ID'],
                    'OLD_PRICE' => $row['OLD_PRICE'],
                    'TAGS' => explode(',', $row['TAGS']),
                ]
            ]);

            self::assertNotEmpty($elementId, $cIBlockElement->LAST_ERROR);

            $isPriceAdded = CPrice::Add([
                'PRODUCT_ID' => $elementId,
                'CATALOG_GROUP_ID' => $basePriceType['ID'],
                'PRICE' => $row['PRICE'],
                'CURRENCY' => 'RUB'
            ]);

            self::assertNotEmpty($isPriceAdded, $APPLICATION->GetException());

            $isMainStoreAmountAdded = CCatalogStoreProduct::Add([
                'PRODUCT_ID' => $elementId,
                'STORE_ID' => $mainStoreId,
                'AMOUNT' => $row['MAIN_STORE']
            ]);

            self::assertNotEmpty($isMainStoreAmountAdded, $APPLICATION->GetException());

            $isSecondaryStoreAmountAdded = CCatalogStoreProduct::Add([
                'PRODUCT_ID' => $elementId,
                'STORE_ID' => $secondaryStoreId,
                'AMOUNT' => $row['SECONDARY_STORE']
            ]);

            self::assertNotEmpty($isSecondaryStoreAmountAdded, $APPLICATION->GetException());

            $elementIds[] = $elementId;
        }

        fclose($csv);
    }

    public static function tearDownAfterClass()
    {
        CIBlockType::Delete('elastic_test');

        $rs = CCatalogStore::GetList(null, ['XML_ID' => ['MAIN', 'SECONDARY']]);
        while ($store = $rs->Fetch()) {
            CCatalogStore::Delete($store['ID']);
        }
    }

    /**
     * @return array
     */
    public function testCanGetInfoblockMapping()
    {
        $stack = [];

        $iBlock = CIBlock::GetList(null, ['=TYPE' => 'elastic_test', '=CODE' => 'PRODUCTS'])->Fetch();
        $this->assertNotEmpty($iBlock['ID']);

        $mainStore = CCatalogStore::GetList(null, ['XML_ID' => 'MAIN'])->Fetch();
        $this->assertTrue(!empty($mainStore['ID']));

        $secondaryStore = CCatalogStore::GetList(null, ['XML_ID' => 'SECONDARY'])->Fetch();
        $this->assertTrue(!empty($secondaryStore['ID']));

        $basePriceType = CCatalogGroup::GetBaseGroup();
        $this->assertTrue(!empty($basePriceType));

        $indexer = new Indexer($this->getElasticClient());
        $this->assertInstanceOf(Indexer::class, $indexer);
        $this->assertInstanceOf(Client::class, $indexer->getElastic());

        $mapping = $indexer->getInfoBlockMapping($iBlock['ID']);

        $this->assertInstanceOf(IndexMapping::class, $mapping);
        $this->assertContainsOnlyInstancesOf(PropertyMapping::class, $mapping->getProperties());

        $this->assertEquals('float', $mapping->getProperty('PROPERTY_OLD_PRICE')->get('type'));
        $this->assertEquals('keyword', $mapping->getProperty('PROPERTY_COLOR')->get('type'));
        $this->assertEquals('keyword', $mapping->getProperty('PROPERTY_TAGS')->get('type'));
        $this->assertEquals('integer', $mapping->getProperty('CATALOG_STORE_AMOUNT_' . $mainStore['ID'])->get('type'));
        $this->assertEquals('integer', $mapping->getProperty('CATALOG_STORE_AMOUNT_' . $secondaryStore['ID'])->get('type'));
        $this->assertEquals('float', $mapping->getProperty('CATALOG_PRICE_' . $basePriceType['ID'])->get('type'));
        $this->assertEquals('keyword', $mapping->getProperty('CATALOG_CURRENCY_' . $basePriceType['ID'])->get('type'));

        $stack['iBlockId'] = $iBlock['ID'];
        $stack['mainStore'] = $mainStore;
        $stack['secondaryStore'] = $secondaryStore;
        $stack['basePriceType'] = $basePriceType;
        $stack['indexer'] = $indexer;
        $stack['mapping'] = $mapping;
        return $stack;
    }

    /**
     * @depends testCanGetInfoblockMapping
     * @param array $stack
     * @return array
     */
    public function testCanSerializeIndexMappingToArray(array $stack = [])
    {
        /** @var IndexMapping $mapping */
        $mapping = $stack['mapping'];
        $array = $mapping->toArray();
        $this->assertIsArray($array);
        $this->assertNotEmpty($array);
        $propertyArray = $mapping->getProperty('ID')->toArray();
        $this->assertIsArray($propertyArray);
        $this->assertNotEmpty($propertyArray);
        return $stack;
    }

    /**
     * @depends testCanGetInfoblockMapping
     * @param array $stack
     * @return array
     */
    public function testCanGetElementData(array $stack = [])
    {
        $element = CIBlockElement::GetList(null, [
            'IBLOCK_ID' => $stack['iBlockId'],
            '=NAME' => 'Notebook 15'
        ])->GetNextElement();

        $this->assertInstanceOf(_CIBElement::class, $element);

        /** @var Indexer $indexer */
        $indexer = $stack['indexer'];
        $data = $indexer->getElementIndexData($element);

        $this->assertIsArray($data);
        $this->assertTrue(!empty($data));

        $this->assertEquals($element->fields['ID'], $data['ID']);
        $this->assertEquals($element->fields['IBLOCK_ID'], $data['IBLOCK_ID']);
        $this->assertEquals('Notebook 15', $data['NAME']);
        $this->assertEquals(22999, $data['PROPERTY_OLD_PRICE']);
        $this->assertEquals(19999, $data['CATALOG_PRICE_' . $stack['basePriceType']['ID']]);
        $this->assertEquals('Black', $data['PROPERTY_COLOR']);
        $this->assertEquals(90, $data['CATALOG_STORE_AMOUNT_' . $stack['mainStore']['ID']]);
        $this->assertEquals(45, $data['CATALOG_STORE_AMOUNT_' . $stack['secondaryStore']['ID']]);
        $this->assertEquals(['sale', 'hit'], $data['PROPERTY_TAGS']);

        $stack['element'] = $element;
        $stack['rawData'] = $data;
        return $stack;
    }

    /**
     * @depends testCanGetElementData
     * @param array $stack
     * @return array
     */
    public function testCanNormalizeValue(array $stack = [])
    {
        /** @var Indexer $indexer */
        $indexer = $stack['indexer'];

        /** @var _CIBElement $element */
        $element = $stack['element'];

        $data = $indexer->normalizeData($stack['mapping'], $stack['rawData']);
        $this->assertIsArray($data);
        $this->assertSame((int)$element->fields['ID'], $data['ID']);
        $this->assertSame((int)$element->fields['IBLOCK_ID'], $data['IBLOCK_ID']);
        $this->assertSame('Notebook 15', $data['NAME']);
        $this->assertSame(22999.00, $data['PROPERTY_OLD_PRICE']);
        $this->assertSame(19999.00, $data['CATALOG_PRICE_' . $stack['basePriceType']['ID']]);
        $this->assertSame('Black', $data['PROPERTY_COLOR']);
        $this->assertSame(90, $data['CATALOG_STORE_AMOUNT_' . $stack['mainStore']['ID']]);
        $this->assertSame(45, $data['CATALOG_STORE_AMOUNT_' . $stack['secondaryStore']['ID']]);
        $this->assertSame(['sale', 'hit'], $data['PROPERTY_TAGS']);

        $stack['data'] = $data;
        return $stack;
    }
}