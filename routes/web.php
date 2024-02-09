<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    \Log::info('Test log');
    return view('welcome');
});
