<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Routes pour la vérification d'abonnement
Route::prefix('subscription')->group(function () {
    Route::get('/check', [\App\Http\Controllers\RestoSubscriptionController::class, 'checkSubscription'])->name('subscription.check');
    Route::get('/status', [\App\Http\Controllers\RestoSubscriptionController::class, 'getSubscriptionStatus'])->name('subscription.status');
    Route::get('/expired', [\App\Http\Controllers\RestoSubscriptionController::class, 'redirectToExpiredPage'])->name('subscription.expired');
});
