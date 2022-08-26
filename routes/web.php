<?php

use Illuminate\Support\Facades\Route;

// 在之前的路由后面配上中间件
Route::get('/', 'PagesController@root')->name('root')->middleware('verified');

// 在之前的路由里加上一个 verify 参数
Auth::routes(['verify' => true]);

//Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
