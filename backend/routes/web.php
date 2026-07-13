<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['status' => 'Guised Up API is running!']);
});

Route::get('/debug', function () {
    $key = config('app.key');
    $cipher = config('app.cipher');
    $len = mb_strlen((string)$key, '8bit');
    
    // Instantiate Encrypter directly to see if it throws!
    try {
        $enc = new \Illuminate\Encryption\Encrypter($key, $cipher);
        return "SUCCESS! " . get_class($enc);
    } catch (\Exception $e) {
        return "FAIL! " . $e->getMessage() . " | Key: {$key} | Cipher: {$cipher} | Len: {$len}";
    }
});
