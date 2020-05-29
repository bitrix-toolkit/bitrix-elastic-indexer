<?php

use Sheerockoff\BitrixCi;

require __DIR__ . '/../vendor/autoload.php';

if (!getenv('SKIP_MIGRATION')) {
    echo "Migration...";
    BitrixCi\Bootstrap::migrate();
    echo "\e[92mCOMPLETE\e[0m\n";
}

BitrixCi\Bootstrap::bootstrap();
