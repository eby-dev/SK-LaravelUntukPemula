<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


//route resource
//
// Read-only endpoints stay public; anything that writes is gated behind
// authentication so a visitor cannot create, edit or delete posts.
//
// This app ships no login scaffolding yet. Install one (e.g. `composer require
// laravel/breeze --dev && php artisan breeze:install`) so the 'auth' middleware
// below has a login route to redirect to.
Route::resource('/posts', \App\Http\Controllers\PostController::class)
    ->only(['index', 'show']);

Route::resource('/posts', \App\Http\Controllers\PostController::class)
    ->except(['index', 'show'])
    ->middleware('auth');
