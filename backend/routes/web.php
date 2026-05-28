<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => 'termovault-api',
        'status' => 'ok',
    ]);
});
