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

Route::get('auth/verify', 'AuthController@verify'); // The user can press F5 repeatedly, so remove throttle from this

Route::post('media', 'MediaController@store');
Route::get('media/{key}', 'MediaController@show');

Route::group([
    'middleware' => 'throttle:10,1', // The user can call auth APIs up to 10 times in every a minute
], function () {
    Route::post('auth/login_with_email_password', 'AuthController@loginWithEmailPassword');
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
    Route::get('companies/{uuid}/departments', 'CompanyController@showDepartments');
    Route::get('companies/{uuid}/users', 'CompanyController@showUsers');

    Route::get('departments', 'DepartmentController@index');
    Route::get('departments/{uuid}', 'DepartmentController@show');
    Route::post('departments', 'DepartmentController@store');
    Route::put('departments/{uuid}', 'DepartmentController@update');
    Route::delete('departments/{uuid}', 'DepartmentController@destroy');
    Route::patch('departments/{uuid}', 'DepartmentController@restore');
    Route::get('departments/{uuid}/users', 'DepartmentController@showUsers');

    Route::get('belong_to/{uuid}', 'BelongToController@show');
    Route::post('belong_to', 'BelongToController@store');
    Route::delete('belong_to/{uuid}', 'BelongToController@destroy');

    Route::get('users', 'UserController@index');
    Route::get('users/{uuid}', 'UserController@show');
    Route::post('users', 'UserController@store');
    Route::put('users/{uuid}', 'UserController@update');
    Route::delete('users/{uuid}', 'UserController@destroy');
    Route::patch('users/{uuid}', 'UserController@restore');

    Route::get('work_at', 'WorkAtController@index');
    Route::get('work_at/{uuid}', 'WorkAtController@show');
    Route::post('work_at', 'WorkAtController@store');
    Route::put('work_at/{uuid}', 'WorkAtController@update');
    Route::delete('work_at/{uuid}', 'WorkAtController@destroy');
});
