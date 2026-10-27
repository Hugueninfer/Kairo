<?php

use App\Http\Controllers\Dev\FakeCheckoutController;
use Illuminate\Support\Facades\Route;

// O Kairo é API-first: o front em Vue consome apenas as rotas de /api.
Route::get('/', fn () => response()->json(['app' => config('app.name')]));

// Página de pagamento do gateway simulado, para desenvolvimento (o controller recusa em produção).
Route::get('fake-checkout/{charge}', [FakeCheckoutController::class, 'show'])->name('fake-checkout.show');
Route::post('fake-checkout/{charge}', [FakeCheckoutController::class, 'pay'])->name('fake-checkout.pay');
