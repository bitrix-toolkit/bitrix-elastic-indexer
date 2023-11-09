# Bitrix Elasticsearch Indexer

[![PHPUnit](https://github.com/sheerockoff/bitrix-elastic-indexer/workflows/PHPUnit/badge.svg?branch=master)](https://github.com/sheerockoff/bitrix-elastic-indexer/actions)
[![Coverage](https://scrutinizer-ci.com/g/sheerockoff/bitrix-elastic-indexer/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/sheerockoff/bitrix-elastic-indexer/?branch=master)
[![Scrutinizer](https://scrutinizer-ci.com/g/sheerockoff/bitrix-elastic-indexer/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/sheerockoff/bitrix-elastic-indexer/?branch=master)

Хелпер для индексации данных инфоблока Bitrix в Elasticsearch.

## Установка

```bash
composer require sheerockoff/bitrix-elastic-indexer
```

## Быстрый старт

Подключаем зависимости, создаём клиент `Elasticsearch`.

```php
<?php

use Elasticsearch\ClientBuilder;
use Sheerockoff\BitrixElastic\Indexer;

require 'vendor/autoload.php';

$elastic = ClientBuilder::create()->setHosts(['http://elasticsearch:9200'])->build();
```

Создаём экземпляр `Indexer`.

```php
$indexer = new Indexer($elastic);
```

По-умолчанию методы `Indexer` выбрасывают исключения при ошибках. Чтобы игнорировать некоторые исключения, необходимо
передать `false` вторым аргументом (`strictMode`) конструктора.

```php
$indexer = new Indexer($elastic, false);
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

Пагинацию и другие параметры запроса можно указать в последнем аргументе метода `search`.

```php
$response = $indexer->search('goods', ['ACTIVE' => 'Y'], ['ID' => 'ASC'], [
    'from' => 40,
    'size' => 20
]);
```

## Разработка и тестирование

Запуск тестов в контейнерах Docker:
```bash
docker compose up -d
docker compose exec bitrix composer install
docker compose exec bitrix vendor/bin/phpunit
```
