<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "\nAPP_KEY=" . config('app.key') . "\n";
echo "APP_CIPHER=" . config('app.cipher') . "\n";
$key = config('app.key');
if (str_starts_with($key, 'base64:')) {
    $decoded = base64_decode(substr($key, 7));
    echo "Decoded length=" . strlen($decoded) . " bytes\n";
} else {
    echo "Key length=" . strlen($key) . " bytes\n";
}
