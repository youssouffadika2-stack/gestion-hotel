<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Api\BridgeController;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Appel interne de l'administration générale (admin_hotel) : e-mail d'activation envoyé à l'hôtel.
Route::post('/internal/hotel-access', [BridgeController::class, 'access'])->middleware('throttle:20,1');
