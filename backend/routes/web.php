<?php

use Illuminate\Support\Facades\Route;

// O Kairo é API-first: o front em Vue consome apenas as rotas de /api.
Route::get('/', fn () => response()->json(['app' => config('app.name')]));
