<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RealEstateController;

Route::apiResource('properties', RealEstateController::class);

// TEMP: smoke test
Route::get('ping', fn () => response()->json(['pong' => true]));
