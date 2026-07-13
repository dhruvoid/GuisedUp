<?php
require __DIR__.'/vendor/autoload.php';
$key = '12345678901234567890123456789012';
$len = mb_strlen($key, '8bit');
var_dump($len);
if ($len === 32) {
    echo "SUCCESS\n";
} else {
    echo "FAIL\n";
}
