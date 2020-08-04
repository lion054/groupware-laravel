<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group([
    'middleware' => 'throttle:60,1', // The user can call this up to 60 times in a minute
], function () {
    Route::post('auth/login_with_email_password', 'Auth\LoginController@loginWithEmailPassword');
    Route::post('auth/verify', 'Auth\LoginController@loginWithEmailPassword');
});

Route::group([
    // 'middleware' => 'jwt.auth',
], function () {
    // Route::post('', '');
});
