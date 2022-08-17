<?php

use Sheerockoff\BitrixCi;

require __DIR__ . '/../vendor/autoload.php';

if (!getenv('SKIP_MIGRATION', true) && !getenv('SKIP_MIGRATION')) {
    file_put_contents('php://stdout', 'Migration...');
    BitrixCi\Bootstrap::migrate();
    file_put_contents('php://stdout', "\e[92mCOMPLETE\e[0m\n");
}

BitrixCi\Bootstrap::bootstrap();

while (ob_get_level()) {
    ob_end_clean();
}
