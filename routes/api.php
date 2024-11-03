<?php

use App\Http\Controllers\Api\AccessTokenController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\TestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', [TestController::class, 'index']);

Route::group(['namespace' => 'Api'], function () {
    Route::any('/login', [LoginController::class, 'login']);

    Route::any('/contact', [LoginController::class, 'contact'])
        ->middleware('checkUser');

    Route::any('/get_rtc_token', [AccessTokenController::class, 'getRtcToken'])
        ->middleware('checkUser');
});
