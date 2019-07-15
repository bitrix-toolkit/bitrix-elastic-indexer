# Bitrix Elasticsearch Indexer

[![pipeline status](https://gitlab.com/sheerockoff/bitrix-elastic-indexer/badges/master/pipeline.svg)](https://gitlab.com/sheerockoff/bitrix-elastic-indexer/pipelines)
[![coverage report](https://gitlab.com/sheerockoff/bitrix-elastic-indexer/badges/master/coverage.svg)](https://gitlab.com/sheerockoff/bitrix-elastic-indexer/-/jobs)

Хелпер для индексации данных инфоблока Bitrix в Elasticsearch.

## Установка

```bash
composer require sheerockoff/bitrix-elastic-indexer:dev-master
```

## Быстрый старт

Подключаем зависимости, создаём клиент Elasticsearch, создаём экземпляр `Indexer`.

```php
<?php

use Elasticsearch\ClientBuilder;
use Sheerockoff\BitrixElastic\Indexer;

require 'vendor/autoload.php';

$elastic = ClientBuilder::create()->setHosts(['http://elasticsearch:9200'])->build();
$indexer = new Indexer($elastic);
```

Получаем карту индекса для инфоблока.

```php
$infoBlockMapping = $indexer->getInfoBlockMapping($iBlockId);
```

Обновляем карту индекса в Elasticsearch. Метод обновит карту только тех свойств,
которые отсутствуют в текущем индексе. Карты существующих свойств в индексе
изменяться не будут, чтобы избежать ошибок.

```php
$indexer->putMapping('goods', $infoBlockMapping);
```

Получаем текущую карту индекса из Elasticsearch.

```php
$elasticMapping = $indexer->getMapping('goods');
```

Получаем сырые данные индекса для элемента.

```php
/** @var _CIBElement $element */
$rawData = $indexer->getElementRawData($element);
```

Нормализуем сырые данные индекса в соответствии с картой индекса Elasticsearch.

```php
$normalizedData = $indexer->normalizeData($elasticMapping, $rawData);
```

Сохраняем данные в индексе Elasticsearch.

```php
$indexer->put('goods', $id, $normalizedData);
```

Ищем по индексу используя фильтры в формате похожем на формат Bitrix.

```php
$response = $indexer->search('goods', [
    'IBLOCK_ID' => 1,
    'SECTION_CODE' => 'mobile',
    'INCLUDE_SUBSECTIONS' => 'Y',
    'ACTIVE' => 'Y',
    '>CATALOG_PRICE_1' => 0,
    '>CATALOG_STORE_AMOUNT_1' => 0,
    'PROPERTY_TAGS' => ['hit', 'sale']
]);
```

Для сортировки также используется формат похожий на формат Bitrix.

```php
$response = $indexer->search('goods', ['ACTIVE' => 'Y'], [
    'CATALOG_PRICE_1' => 'ASC',
    'ID' => 'DESC'
]);
```