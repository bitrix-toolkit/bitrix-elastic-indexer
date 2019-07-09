<?php

use Sheerockoff\BitrixCi;

require __DIR__ . '/../vendor/autoload.php';

BitrixCi\Bootstrap::migrate();
BitrixCi\Bootstrap::bootstrap();
