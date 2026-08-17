<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\MediaController;

Route::get('/', function () {
    return response()->json(['name' => 'AIEV Laravel API', 'version' => '0.1.0']);
});

Route::get('media/{path}', [MediaController::class, 'show'])->where('path', '.*');
