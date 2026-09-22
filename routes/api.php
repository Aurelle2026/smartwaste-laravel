<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BinAlertController;
use App\Http\Controllers\Api\BinController;
use App\Http\Controllers\Api\RecyclerProfileController;
use App\Http\Controllers\Api\WasteOfferController;
use Illuminate\Support\Facades\Route;

// Public (dont le visiteur)
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::get('bins', [BinController::class, 'index']);
Route::get('bins/legend', [BinController::class, 'legend']);
Route::get('bins/{bin}', [BinController::class, 'show'])->whereNumber('bin');

// Connecté
Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::patch('bins/{bin}/status', [BinController::class, 'updateStatus'])
        ->middleware('role:recycleur,mairie,admin');

    Route::middleware('role:mairie,admin')->group(function () {
        Route::post('bins', [BinController::class, 'store']);
        Route::put('bins/{bin}', [BinController::class, 'update']);
        Route::delete('bins/{bin}', [BinController::class, 'destroy']);
    });

    // Vente des déchets
    Route::get('recyclers', [RecyclerProfileController::class, 'index']);

    Route::get('waste-offers', [WasteOfferController::class, 'index']);
    Route::post('waste-offers', [WasteOfferController::class, 'store'])->middleware('role:citoyen');
    Route::patch('waste-offers/{wasteOffer}/status', [WasteOfferController::class, 'updateStatus']);

    // Suivi des alertes
    Route::get('bin-alerts', [BinAlertController::class, 'index']);
    Route::get('bin-alerts/summary', [BinAlertController::class, 'summary']);
    Route::post('bin-alerts', [BinAlertController::class, 'store']);
    Route::patch('bin-alerts/{binAlert}/status', [BinAlertController::class, 'updateStatus'])
        ->middleware('role:isacam,admin');
});
