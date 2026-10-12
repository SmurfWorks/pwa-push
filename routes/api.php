<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\PushAppController;
use App\Http\Controllers\VapidKeyController;
use Illuminate\Support\Facades\Route;

Route::get('/vapid-public-key', VapidKeyController::class);

Route::middleware('push.admin')->group(function () {
    Route::get('/apps', [PushAppController::class, 'index']);
    Route::post('/apps', [PushAppController::class, 'store']);
    Route::patch('/apps/{pushApp}', [PushAppController::class, 'update']);
    Route::post('/apps/{pushApp}/token', [PushAppController::class, 'rotate']);
    Route::delete('/apps/{pushApp}', [PushAppController::class, 'destroy']);
});

Route::middleware('push.app')->group(function () {
    Route::get('/devices', [DeviceController::class, 'index']);
    Route::post('/devices', [DeviceController::class, 'store']);
    Route::delete('/devices/{device}', [DeviceController::class, 'destroy']);

    Route::get('/devices/{device}/notifications', [AlertController::class, 'index']);
    Route::put('/devices/{device}/notifications/{key}', [AlertController::class, 'upsert'])
        ->where('key', '[A-Za-z0-9][A-Za-z0-9:._-]*');
    Route::delete('/devices/{device}/notifications/{key}', [AlertController::class, 'destroy'])
        ->where('key', '[A-Za-z0-9][A-Za-z0-9:._-]*');
    Route::delete('/devices/{device}/notifications', [AlertController::class, 'clear']);
});
