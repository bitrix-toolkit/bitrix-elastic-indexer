<?php

namespace Sheerockoff\BitrixElastic\Test;

use Sheerockoff\BitrixElastic\IndexMapping;

class IndexMappingTest extends TestCase
{
    public function testExceptOnGetUndefinedProperty() {
        $this->expectException(\InvalidArgumentException::class);
        $mapping = new IndexMapping();
        $mapping->getProperty('undefined');
    }
}