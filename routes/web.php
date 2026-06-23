<?php

use Illuminate\Support\Facades\Route;

// Todas las rutas web sirven el SPA de React (excepto /up que maneja Laravel)
Route::get('/{any?}', fn() => view('app'))->where('any', '.*');
