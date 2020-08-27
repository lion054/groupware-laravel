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

$router->post('media', 'MediaController@store');
$router->get('media/{key}', 'MediaController@show');

$router->group([
    'middleware' => [
        // 'throttle:10,1', // The user can call auth APIs up to 10 times in every a minute
        'token.generate',
    ]
], function () use ($router) {
    $router->post('auth/login_with_email_password', 'AuthController@loginWithEmailPassword');
});

$router->group([
    'middleware' => [
        // 'throttle:10,1', // The user can call auth APIs up to 10 times in every a minute
        'token.validate',
        'token.generate',
    ]
], function () use ($router) {
    $router->get('auth/refresh', 'AuthController@refresh');
});

$router->group([
    'middleware' => 'token.validate'
], function () use ($router) {
    $router->get('auth/verify', 'AuthController@verify');

    $router->get('companies', 'CompanyController@index');
    $router->get('companies/{uuid}', 'CompanyController@show');
    $router->post('companies', 'CompanyController@store');
    $router->put('companies/{uuid}', 'CompanyController@update');
    $router->delete('companies/{uuid}', 'CompanyController@destroy');
    $router->patch('companies/{uuid}', 'CompanyController@restore');
    $router->get('companies/{uuid}/departments', 'CompanyController@showDepartments');
    $router->get('companies/{uuid}/users', 'CompanyController@showUsers');

    $router->get('departments', 'DepartmentController@index');
    $router->get('departments/{uuid}', 'DepartmentController@show');
    $router->post('departments', 'DepartmentController@store');
    $router->put('departments/{uuid}', 'DepartmentController@update');
    $router->delete('departments/{uuid}', 'DepartmentController@destroy');
    $router->patch('departments/{uuid}', 'DepartmentController@restore');
    $router->get('departments/{uuid}/users', 'DepartmentController@showUsers');

    $router->get('belong_to/{uuid}', 'BelongToController@show');
    $router->post('belong_to', 'BelongToController@store');
    $router->delete('belong_to/{uuid}', 'BelongToController@destroy');

    $router->get('users', 'UserController@index');
    $router->get('users/{uuid}', 'UserController@show');
    $router->post('users', 'UserController@store');
    $router->put('users/{uuid}', 'UserController@update');
    $router->delete('users/{uuid}', 'UserController@destroy');
    $router->patch('users/{uuid}', 'UserController@restore');

    $router->get('work_at', 'WorkAtController@index');
    $router->get('work_at/{uuid}', 'WorkAtController@show');
    $router->post('work_at', 'WorkAtController@store');
    $router->put('work_at/{uuid}', 'WorkAtController@update');
    $router->delete('work_at/{uuid}', 'WorkAtController@destroy');
});
