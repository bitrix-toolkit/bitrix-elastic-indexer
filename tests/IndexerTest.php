<?php

/** @noinspection PhpParamsInspection */

namespace Sheerockoff\BitrixElastic\Test;

use _CIBElement;
use CCatalog;
use CCatalogGroup;
use CCatalogStore;
use CCatalogStoreProduct;
use CFile;
use CIBlock;
use CIBlockElement;
use CIBlockProperty;
use CIBlockSection;
use CIBlockType;
use CPrice;
use Elasticsearch\Client;
use InvalidArgumentException;
use ReflectionException;
use ReflectionObject;
use Sheerockoff\BitrixElastic\Indexer;
use Sheerockoff\BitrixElastic\IndexMapping;
use Sheerockoff\BitrixElastic\PropertyMapping;

class IndexerTest extends TestCase
{
    public static function setUpBeforeClass()
    {
        global $APPLICATION;

        self::tearDownAfterClass();

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
            'GROUP_ID' => ['1' => 'X', '2' => 'W'],
            'DETAIL_PAGE_URL' => '/catalog/#SECTION_CODE_PATH#//#ELEMENT_ID#/'
        ]);

        self::assertNotEmpty($iBlockId, $cIBlock->LAST_ERROR);

        $cIBlockSection = new CIBlockSection();
        $electronicSectionId = $cIBlockSection->Add([
            'IBLOCK_ID' => $iBlockId,
            'IBLOCK_SECTION_ID' => null,
            'CODE' => 'electronic',
            'NAME' => 'Электроника',
            'ACTIVE' => 'Y'
        ]);

        self::assertNotEmpty($electronicSectionId, $cIBlockSection->LAST_ERROR);

        $cIBlockSection = new CIBlockSection();
        $mobileSectionId = $cIBlockSection->Add([
            'IBLOCK_ID' => $iBlockId,
            'IBLOCK_SECTION_ID' => $electronicSectionId,
            'CODE' => 'mobile',
            'NAME' => 'Мобильная техника',
            'ACTIVE' => 'Y'
        ]);

        self::assertNotEmpty($mobileSectionId, $cIBlockSection->LAST_ERROR);

        $cIBlockSection = new CIBlockSection();
        $computerSectionId = $cIBlockSection->Add([
            'IBLOCK_ID' => $iBlockId,
            'IBLOCK_SECTION_ID' => $electronicSectionId,
            'CODE' => 'computer',
            'NAME' => 'Компьютеры',
            'ACTIVE' => 'Y'
        ]);

        self::assertNotEmpty($computerSectionId, $cIBlockSection->LAST_ERROR);

        $cIBlockSection = new CIBlockSection();
        $phoneSectionId = $cIBlockSection->Add([
            'IBLOCK_ID' => $iBlockId,
            'IBLOCK_SECTION_ID' => $mobileSectionId,
            'CODE' => 'phone',
            'NAME' => 'Телефоны',
            'ACTIVE' => 'Y'
        ]);

        self::assertNotEmpty($phoneSectionId, $cIBlockSection->LAST_ERROR);

        $cIBlockSection = new CIBlockSection();
        $tabletSectionId = $cIBlockSection->Add([
            'IBLOCK_ID' => $iBlockId,
            'IBLOCK_SECTION_ID' => $mobileSectionId,
            'CODE' => 'tablet',
            'NAME' => 'Планшеты',
            'ACTIVE' => 'Y'
        ]);

        self::assertNotEmpty($tabletSectionId, $cIBlockSection->LAST_ERROR);

        $cIBlockSection = new CIBlockSection();
        $notebookSectionId = $cIBlockSection->Add([
            'IBLOCK_ID' => $iBlockId,
            'IBLOCK_SECTION_ID' => $computerSectionId,
            'CODE' => 'notebook',
            'NAME' => 'Ноутбуки',
            'ACTIVE' => 'Y'
        ]);

        self::assertNotEmpty($notebookSectionId, $cIBlockSection->LAST_ERROR);

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

            $sectionIds = [];
            $sectionCodes = explode(',', $row['CATEGORY']);
            if ($sectionCodes) {
                $rs = CIBlockSection::GetList(
                    ['ID' => 'ASC'],
                    ['IBLOCK_ID' => $iBlockId, 'CODE' => $sectionCodes],
                    false,
                    ['ID', 'IBLOCK_ID']
                );

                while ($section = $rs->Fetch()) {
                    $sectionIds[] = $section['ID'];
                }
            }

            $elementId = $cIBlockElement->Add([
                'IBLOCK_ID' => $iBlockId,
                'IBLOCK_SECTION' => $sectionIds,
                'NAME' => $row['NAME'],
                'PREVIEW_PICTURE' => CFile::MakeFileArray(__DIR__ . '/' . $row['IMAGE']),
                'DETAIL_PICTURE' => CFile::MakeFileArray(__DIR__ . '/' . $row['IMAGE']),
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

        $elastic = self::getElasticClient();
        if ($elastic->indices()->exists(['index' => 'test_products'])) {
            $elastic->indices()->delete(['index' => 'test_products']);
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

        $indexer = new Indexer(self::getElasticClient());
        $this->assertInstanceOf(Indexer::class, $indexer);
        $this->assertInstanceOf(Client::class, $indexer->getElastic());

        $mapping = $indexer->getInfoBlockMapping($iBlock['ID']);

        $this->assertInstanceOf(IndexMapping::class, $mapping);
        $this->assertContainsOnlyInstancesOf(PropertyMapping::class, $mapping->getProperties());

        $this->assertEquals('integer', $mapping->getProperty('DETAIL_PICTURE')->get('type'));
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
        $data = $indexer->getElementRawData($element);

        $this->assertIsArray($data);
        $this->assertTrue(!empty($data));

        $groupIds = [];
        $groupCodes = [];
        $navChainIds = [];
        $navChainCodes = [];
        $rs = CIBlockElement::GetElementGroups($element->fields['ID']);
        while ($group = $rs->Fetch()) {
            $groupIds[] = (int)$group['ID'];
            if ($group['CODE']) {
                $groupCodes[] = $group['CODE'];
            }

            $navChainRs = CIBlockSection::GetNavChain($group['IBLOCK_ID'], $group['ID']);
            while ($chain = $navChainRs->Fetch()) {
                $navChainIds[] = (int)$chain['ID'];
                if ($chain['CODE']) {
                    $navChainCodes[] = $chain['CODE'];
                }
            }
        }

        $this->assertEquals($element->fields['ID'], $data['ID']);
        $this->assertEquals($element->fields['IBLOCK_ID'], $data['IBLOCK_ID']);
        $this->assertEquals($element->fields['PREVIEW_PICTURE'], $data['PREVIEW_PICTURE']);
        $this->assertEquals($element->fields['DETAIL_PICTURE'], $data['DETAIL_PICTURE']);
        $this->assertEquals('Notebook 15', $data['NAME']);
        $this->assertEquals(22999, $data['PROPERTY_OLD_PRICE']);
        $this->assertEquals(19999, $data['CATALOG_PRICE_' . $stack['basePriceType']['ID']]);
        $this->assertEquals('Black', $data['PROPERTY_COLOR']);
        $this->assertEquals(90, $data['CATALOG_STORE_AMOUNT_' . $stack['mainStore']['ID']]);
        $this->assertEquals(45, $data['CATALOG_STORE_AMOUNT_' . $stack['secondaryStore']['ID']]);
        $this->assertEquals(['sale', 'hit'], $data['PROPERTY_TAGS']);
        $this->assertEquals($groupIds, $data['GROUP_IDS']);
        $this->assertEquals($navChainIds, $data['NAV_CHAIN_IDS']);
        $this->assertEquals($groupCodes, $data['GROUP_CODES']);
        $this->assertEquals($navChainCodes, $data['NAV_CHAIN_CODES']);

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

        $groupIds = [];
        $groupCodes = [];
        $navChainIds = [];
        $navChainCodes = [];
        $rs = CIBlockElement::GetElementGroups($element->fields['ID']);
        while ($group = $rs->Fetch()) {
            $groupIds[] = (int)$group['ID'];
            if ($group['CODE']) {
                $groupCodes[] = $group['CODE'];
            }

            $navChainRs = CIBlockSection::GetNavChain($group['IBLOCK_ID'], $group['ID']);
            while ($chain = $navChainRs->Fetch()) {
                $navChainIds[] = (int)$chain['ID'];
                if ($chain['CODE']) {
                    $navChainCodes[] = $chain['CODE'];
                }
            }
        }

        $data = $indexer->normalizeData($stack['mapping'], $stack['rawData']);
        $this->assertIsArray($data);
        $this->assertSame((int)$element->fields['ID'], $data['ID']);
        $this->assertSame((int)$element->fields['IBLOCK_ID'], $data['IBLOCK_ID']);
        $this->assertSame((int)$element->fields['PREVIEW_PICTURE'], $data['PREVIEW_PICTURE']);
        $this->assertSame((int)$element->fields['DETAIL_PICTURE'], $data['DETAIL_PICTURE']);
        $this->assertSame('Notebook 15', $data['NAME']);
        $this->assertSame(22999.00, $data['PROPERTY_OLD_PRICE']);
        $this->assertSame(19999.00, $data['CATALOG_PRICE_' . $stack['basePriceType']['ID']]);
        $this->assertSame('Black', $data['PROPERTY_COLOR']);
        $this->assertSame(90, $data['CATALOG_STORE_AMOUNT_' . $stack['mainStore']['ID']]);
        $this->assertSame(45, $data['CATALOG_STORE_AMOUNT_' . $stack['secondaryStore']['ID']]);
        $this->assertSame(['sale', 'hit'], $data['PROPERTY_TAGS']);
        $this->assertSame($navChainIds, $data['NAV_CHAIN_IDS']);
        $this->assertSame($groupIds, $data['GROUP_IDS']);
        $this->assertSame($navChainCodes, $data['NAV_CHAIN_CODES']);
        $this->assertSame($groupCodes, $data['GROUP_CODES']);

        $stack['data'] = $data;
        return $stack;
    }

    /**
     * @depends testCanGetInfoblockMapping
     * @param array $stack
     * @return array
     */
    public function testCanPutIndexMapping(array $stack = [])
    {
        /** @var Indexer $indexer */
        $indexer = $stack['indexer'];

        /** @var IndexMapping $mapping */
        $mapping = $stack['mapping'];

        $existMapping = $indexer->getMapping('test_products');
        $this->assertInstanceOf(IndexMapping::class, $existMapping);

        for ($i = 0; $i < 2; $i++) {
            $isSuccess = $indexer->putMapping('test_products', $mapping);
            $this->assertTrue($isSuccess);

            $existMapping = $indexer->getMapping('test_products');
            $this->assertInstanceOf(IndexMapping::class, $existMapping);

            /** @var PropertyMapping $propertyMap */
            foreach ($mapping->getProperties()->getArrayCopy() as $property => $propertyMap) {
                $this->assertEquals(
                    $propertyMap->getData()->getArrayCopy(),
                    $existMapping->getProperty($property)->getData()->getArrayCopy()
                );
            }
        }

        return $stack;
    }

    public function testCanPutIndexData()
    {
        $iBlock = CIBlock::GetList(null, ['=TYPE' => 'elastic_test', '=CODE' => 'PRODUCTS'])->Fetch();
        $this->assertNotEmpty($iBlock['ID']);

        $indexer = new Indexer(self::getElasticClient());

        $infoBlockMapping = $indexer->getInfoBlockMapping($iBlock['ID']);
        $this->assertInstanceOf(IndexMapping::class, $infoBlockMapping);

        $isSuccess = $indexer->putMapping('test_products', $infoBlockMapping);
        $this->assertTrue($isSuccess);

        $elasticMapping = $indexer->getMapping('test_products');
        $this->assertInstanceOf(IndexMapping::class, $elasticMapping);

        $rs = CIBlockElement::GetList(null, ['IBLOCK_ID' => $iBlock['ID']]);
        while ($element = $rs->GetNextElement()) {
            $this->assertInstanceOf(_CIBElement::class, $element);

            $rawData = $indexer->getElementRawData($element);
            $normalizedData = $indexer->normalizeData($elasticMapping, $rawData);

            $isCreated = $indexer->put('test_products', $normalizedData['ID'], $normalizedData);
            $this->assertTrue($isCreated);

            $beforeUpdateData = $indexer->getElastic()->get(['index' => 'test_products', 'id' => $normalizedData['ID']]);

            $isUpdated = $indexer->put('test_products', $normalizedData['ID'], $normalizedData);
            $this->assertTrue($isUpdated);

            $afterUpdateData = $indexer->getElastic()->get(['index' => 'test_products', 'id' => $normalizedData['ID']]);

            $this->assertSame($beforeUpdateData['_source'], $afterUpdateData['_source']);
            $this->assertSame($normalizedData, $beforeUpdateData['_source']);
        }
    }

    /**
     * @depends testCanGetInfoblockMapping
     * @param array $stack
     * @return array
     */
    public function testCanSearch(array $stack = [])
    {
        sleep(1);

        /** @var Indexer $indexer */
        $indexer = $stack['indexer'];

        $phoneSection = CIBlockSection::GetList(null, ['IBLOCK_ID' => $stack['iBlockId'], 'CODE' => 'phone'])->Fetch();
        $this->assertNotEmpty($phoneSection['ID']);

        $basePriceId = $stack['basePriceType']['ID'];

        $filters = [
            [
                'IBLOCK_ID' => $stack['iBlockId'],
                'ACTIVE' => 'Y',
                '>CATALOG_PRICE_' . $basePriceId => 0,
                '>CATALOG_STORE_AMOUNT_' . $stack['mainStore']['ID'] => 0,
                'IBLOCK_SECTION_ID' => $phoneSection['ID']
            ],
            [
                '=IBLOCK_ID' => $stack['iBlockId'],
                '!ACTIVE' => 'N',
                '>=CATALOG_PRICE_' . $basePriceId => '10000',
                '<=CATALOG_PRICE_' . $basePriceId => '20000',
                'CATALOG_CURRENCY_' . $basePriceId => 'RUB',
            ],
            [
                'IBLOCK_ID' => $stack['iBlockId'],
                '=ACTIVE' => 'Y',
                '<DATE_CREATE' => date('d.m.Y H:i:s', strtotime('+1 day'))
            ],
            [
                '%NAME' => 'Phone',
                '><PROPERTY_OLD_PRICE' => [10000, 20000],
                'PROPERTY_TAGS' => ['new', 'hit']
            ],
            [
                'SECTION_CODE' => 'mobile',
                'INCLUDE_SUBSECTIONS' => 'Y'
            ],
            [
                'SECTION_ID' => $phoneSection['ID'],
                'INCLUDE_SUBSECTIONS' => $phoneSection['ID']
            ]
        ];

        foreach ($filters as $filter) {
            $response = $indexer->search('test_products', $filter);
            $this->assertNotEmpty($response['hits']['hits']);

            $elasticIds = array_map(function ($hit) {
                return (int)$hit['_source']['ID'];
            }, $response['hits']['hits']);

            $elements = [];
            $rs = CIBlockElement::GetList(null, $filter);
            while ($element = $rs->GetNextElement()) {
                $elements[] = $element;
            }

            $this->assertNotEmpty($elements);
            $this->assertContainsOnlyInstancesOf(_CIBElement::class, $elements);

            $bitrixIds = array_map(function (_CIBElement $element) {
                return (int)$element->fields['ID'];
            }, $elements);

            $this->assertSame(sort($bitrixIds), sort($elasticIds));
        }

        return $stack;
    }

    public function testExceptOnWrongBetweenFilter()
    {
        $indexer = new Indexer(self::getElasticClient());
        $this->expectException(InvalidArgumentException::class);
        $indexer->search('test_products', ['><PROPERTY_OLD_PRICE' => [20000]]);
    }

    public function testExceptOnNormalizeWrongFilterKey()
    {
        $indexer = new Indexer(self::getElasticClient());
        $this->expectException(InvalidArgumentException::class);
        $indexer->search('test_products', ['' => 'WRONG KEY']);
    }

    /**
     * @throws ReflectionException
     */
    public function testExceptOnPrepareWrongFilterKey()
    {
        $indexer = new Indexer(self::getElasticClient());
        $indexerRef = new ReflectionObject($indexer);
        $prepareFilterQueryRef = $indexerRef->getMethod('prepareFilterQuery');
        $prepareFilterQueryRef->setAccessible(true);
        $this->expectException(InvalidArgumentException::class);
        $prepareFilterQueryRef->invoke($indexer, ['' => 'WRONG KEY']);
        $prepareFilterQueryRef->setAccessible(false);
    }

    public function testExceptOnWrongFilterOperator()
    {
        $indexer = new Indexer(self::getElasticClient());
        $this->expectException(InvalidArgumentException::class);
        $indexer->search('test_products', ['@PROPERTY_COLOR' => 'WRONG_OPERATOR']);
    }

    public function testExceptOnFilterUndefinedProperty()
    {
        $indexer = new Indexer(self::getElasticClient());
        $this->expectException(InvalidArgumentException::class);
        $indexer->search('test_products', ['PROPERTY_UNDEFINED' => '']);
    }
}