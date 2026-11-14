<?php

use App\Http\Controllers\BookingTrackingController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\VitrineController;
use App\Livewire\Arrivals;
use App\Livewire\Closing;
use App\Livewire\Expenses;
use App\Livewire\Guests;
use App\Livewire\Invoices;
use App\Livewire\OnlineRequests;
use App\Livewire\Payments;
use App\Livewire\Platform;
use App\Livewire\Reports;
use App\Livewire\Reservations;
use App\Livewire\Rooms;
use App\Livewire\Sales;
use App\Livewire\Services;
use App\Livewire\Settings;
use App\Livewire\Staff;
use App\Livewire\Stock;
use Illuminate\Support\Facades\Route;

// Page d'accueil publique : publicité des hôtels (gérée par l'administration générale) + réservation client
Route::get('/', [VitrineController::class, 'index'])->name('welcome');
Route::get('/hotels/{id}', [VitrineController::class, 'show'])->whereNumber('id')->name('vitrine.show');
Route::post('/hotels/{id}/reserver', [VitrineController::class, 'reserve'])->whereNumber('id')->middleware('throttle:booking')->name('vitrine.reserve');
Route::get('/hotels/{id}/disponibilite', [VitrineController::class, 'availability'])->whereNumber('id')->middleware('throttle:60,1')->name('vitrine.availability');
// Interrogé régulièrement par les pages de gestion pour afficher tout de suite un message de suspension
Route::get('/hotel-status', function (\Illuminate\Http\Request $request) {
    $user = $request->user();
    abort_unless($user, 401);
    $access = app(\App\Services\VitrineAccess::class);
    $hotels = $user->hotels()->get(['hotels.id', 'hotels.name', 'hotels.status', 'hotels.vitrine_id']);
    $blocked = $hotels->isNotEmpty() && $hotels->every(fn ($h) => $h->status !== \App\Models\Hotel::ACTIVE) ? $hotels->first() : null;

    return response()->json([
        'blocked' => (bool) $blocked,
        'hotel' => $blocked?->name,
        'reason' => $blocked ? $access->reasonFor($blocked) : null,
        'support' => $access->support(),
    ]);
})->middleware('auth')->name('hotel.status');
Route::get('/suivi', [BookingTrackingController::class, 'form'])->name('vitrine.track');
Route::post('/suivi', [BookingTrackingController::class, 'lookup'])->middleware('throttle:tracking')->name('vitrine.track.lookup');
Route::post('/suivi/annuler', [BookingTrackingController::class, 'cancel'])->middleware('throttle:tracking')->name('vitrine.track.cancel');
Route::get('/inscrire-mon-hotel', [VitrineController::class, 'registerForm'])->name('vitrine.register');
Route::post('/inscrire-mon-hotel', [VitrineController::class, 'register'])->name('vitrine.register.store');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::view('/dashboard', 'dashboard')
        ->middleware('hotel.selected')
        ->name('dashboard');

    // Modules HOTELPRO : l'hôtel actif est exigé ; les droits fins sont revérifiés dans chaque composant.
    Route::middleware('hotel.selected')->group(function () {
        Route::get('/point-du-jour', Closing\Index::class)->name('closing.index');
        Route::get('/ventes', Sales\Index::class)->name('sales.index');
        Route::get('/reservations', Reservations\Index::class)->name('reservations.index');
        Route::get('/demandes-en-ligne', OnlineRequests\Index::class)->name('online.index');
        Route::get('/chambres', Rooms\Index::class)->name('rooms.index');
        Route::get('/clients', Guests\Index::class)->name('guests.index');
        Route::get('/arrivees-departs', Arrivals\Index::class)->name('arrivals.index');
        Route::get('/facturation', Invoices\Index::class)->name('invoices.index');
        Route::get('/facturation/{reservation}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('/paiements', Payments\Index::class)->name('payments.index');
        Route::get('/services', Services\Index::class)->name('services.index');
        Route::get('/personnel', Staff\Index::class)->name('staff.index');
        Route::get('/stocks', Stock\Index::class)->name('stock.index');
        Route::get('/depenses', Expenses\Index::class)->name('expenses.index');
        Route::get('/rapports', Reports\Index::class)->name('reports.index');
        Route::get('/rapports/export', ReportExportController::class)->name('reports.export');
        Route::get('/parametres', Settings\Index::class)->name('settings.index');
    });
});

// Espace de l'administrateur général HOTELPRO : activation des hôtels et remise des accès.
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'platform.admin',
])->prefix('plateforme')->group(function () {
    Route::redirect('/', '/plateforme/hotels');
    Route::get('/hotels', Platform\Hotels::class)->name('platform.hotels');
});
