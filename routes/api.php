<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::group([
    'prefix' => 'auth'
], function ($router) {
    Route::post('login', 'AuthController@login');
    Route::post('token', 'AuthController@token');
    Route::post('register', 'AuthController@register');
    Route::post('firebase', 'AuthController@firebase');
    Route::post('logout', 'AuthController@logout');
});

Route::group([
    'middleware' => 'jwt.verify',
], function ($router) {
	Route::get('users', 'UserController@index');
	Route::post('users', 'UserController@store');
	Route::put('users/{id}', 'UserController@update');
	Route::delete('users/{id}', 'UserController@delete');

    Route::post('activate_wallet', 'UserController@activateWallet');
    Route::post('add_payment_method', 'UserController@addPaymentMethod');
    Route::post('verify_payment', 'UserController@verifyPayment');
});

Route::group([], function ($router) {
    Route::get('get_payment_info', 'UserController@getPaymentInfo');
});

Route::group([], function ($router) {
    Route::get('products', 'ProductController@index');
});

Route::group([], function ($router) {
    Route::get('favourites', 'LikeController@index');
    Route::post('likes', 'LikeController@store');
});

Route::group([
    'middleware' => 'jwt.verify',
], function ($router) {
    Route::post('products', 'ProductController@store');
    Route::put('products/{id}', 'ProductController@update');
    Route::delete('products/{id}', 'ProductController@delete');
});

Route::group([], function ($router) {
    Route::get('comments', 'CommentController@index');
    Route::post('comments', 'CommentController@store');
});

Route::group([], function ($router) {
    Route::get('contacts', 'ContactController@index');
    Route::post('contacts', 'ContactController@store');
});

Route::group([], function ($router) {
    Route::get('notifications', 'NotificationController@index');
});

Route::group([], function ($router) {
    Route::get('messages', 'MessageController@index');
    Route::post('messages', 'MessageController@store');
});

Route::group([], function ($router) {
    Route::get('profile', 'ProfileController@index');
});

Route::group([
    'middleware' => 'jwt.verify',
], function ($router) {
    Route::post('charge', 'BalanceController@charge');
    Route::post('checkout', 'BalanceController@checkOut');
    Route::post('confirm_purchase', 'BalanceController@confirmPurchase');
    Route::post('withdraw', 'BalanceController@withdraw');
    Route::post('refund', 'BalanceController@refund');
});

Route::group([], function ($router) {
    Route::get('purchase', 'BalanceController@purchase');
    Route::get('available_balance', 'BalanceController@availableBalance');
});

Route::group([
    'middleware' => 'jwt.verify',
], function ($router) {	
	Route::post('profile', 'ProfileController@update');
});
