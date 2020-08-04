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
    'middleware' => 'throttle:10,1', // The user can call auth APIs up to 10 times in every a minute
], function () {
    Route::post('auth/login_with_email_password', 'AuthController@loginWithEmailPassword');
    Route::get('auth/verify', 'AuthController@verify');
});

Route::group([
    'middleware' => 'jwt.auth',
], function () {
    Route::get('users', 'UserController@index');
});
