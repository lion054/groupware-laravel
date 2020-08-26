<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->group([
    'middleware' => [
        // 'throttle:10,1', // The user can call auth APIs up to 10 times in every a minute
        'token.generate',
    ]
], function () use ($router) {
    $router->post('auth/login_with_email_password', 'AuthController@loginWithEmailPassword');
});
