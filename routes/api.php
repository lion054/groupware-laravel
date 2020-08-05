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
    Route::get('companies', 'CompanyController@index');
    Route::get('companies/{uuid}', 'CompanyController@show');
    Route::post('companies', 'CompanyController@store');
    Route::put('companies/{uuid}', 'CompanyController@update');
    Route::delete('companies/{uuid}', 'CompanyController@destroy');
    Route::patch('companies/{uuid}', 'CompanyController@restore');

    Route::get('departments', 'DepartmentController@index');
    Route::get('departments/{uuid}', 'DepartmentController@show');
    Route::post('departments', 'DepartmentController@store');
    Route::put('departments/{uuid}', 'DepartmentController@update');
    Route::delete('departments/{uuid}', 'DepartmentController@destroy');
    Route::patch('departments/{uuid}', 'DepartmentController@restore');

    Route::get('assigned_to/{uuid}', 'AssignedToController@show');
    Route::post('assigned_to', 'AssignedToController@store');
    Route::delete('assigned_to/{uuid}', 'AssignedToController@destroy');

    Route::get('users', 'UserController@index');
    Route::get('users/{uuid}', 'UserController@show');
    Route::post('users', 'UserController@store');
    Route::put('users/{uuid}', 'UserController@update');
    Route::delete('users/{uuid}', 'UserController@destroy');
    Route::patch('users/{uuid}', 'UserController@restore');

    Route::get('works_at', 'WorksAtController@index');
    Route::get('works_at/{uuid}', 'WorksAtController@show');
    Route::post('works_at', 'WorksAtController@store');
    Route::put('works_at/{uuid}', 'WorksAtController@update');
    Route::delete('works_at/{uuid}', 'WorksAtController@destroy');
});
