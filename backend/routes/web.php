<?php

use Illuminate\Support\Facades\Route;

// Serve the Vue SPA for every non-API path (client-side router takes over).
Route::get('/{any?}', fn () => view('spa'))
    ->where('any', '^(?!api).*$')
    ->name('spa');
