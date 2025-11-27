<?php

use Illuminate\Support\Facades\Route;

// OAuth redirect and callback (uses Laravel Socialite)
Route::get('/auth/redirect/{provider}', 'AuthController@redirectToProvider');
Route::get('/auth/callback/{provider}', 'AuthController@handleProviderCallback');

// Optional: a simple route to return a small HTML snippet for OAuth success (SPA handles tokens)
Route::get('/auth/success', function () {
    return view('oauth-success');
});
