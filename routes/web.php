<?php

use App\Http\Controllers\Admin\AdminBusUnitController;
use App\Http\Controllers\Admin\AdminBusUnitSeatController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminLandingRouteController;
use App\Http\Controllers\Admin\AdminPackageController;
use App\Http\Controllers\Admin\AdminSettingController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\ClientDashboardController;
use App\Http\Controllers\PackageTrackingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SeatHoldController;
use App\Http\Controllers\SeatPickerController;
use App\Models\LandingRoute;
use App\Models\Setting;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome', [
        'setting' => Setting::current(),
    ]);
});

Route::get('/viajes/buscar', function () {
    $from = request('from');
    $to = request('to');
    $date = request('date');
    $returnDate = request('return_date');

    $trips = LandingRoute::query()
        ->where('is_active', true)
        ->where('available_seats', '>', 0)
        ->when($from, fn ($query, $value) => $query->where('from', 'like', "%{$value}%"))
        ->when($to, fn ($query, $value) => $query->where('to', 'like', "%{$value}%"))
        ->when($date, fn ($query, $value) => $query->whereDate('day', '>=', $value))
        ->when($returnDate, fn ($query, $value) => $query->where(
            fn ($query) => $query->whereNull('return_date')->orWhereDate('return_date', '>=', $value)
        ))
        ->orderBy('day')
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get();

    return view('travel-results', [
        'trips' => $trips,
        'from' => $from,
        'to' => $to,
        'date' => $date,
        'returnDate' => $returnDate,
    ]);
})->name('travel.search');

Route::get('/rastreo', [PackageTrackingController::class, 'show'])->name('paqueteria.rastreo');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [ClientDashboardController::class, 'index'])->name('dashboard');

    Route::get('/viajes/{landingRoute}/asientos', [SeatPickerController::class, 'show'])->name('travel.seats');
    Route::post('/viajes/{landingRoute}/asientos', [SeatPickerController::class, 'store'])->name('travel.seats.store');
    Route::post('/viajes/{landingRoute}/asientos/{busUnitSeat}/hold', [SeatHoldController::class, 'store'])->name('travel.seats.hold');
    Route::delete('/viajes/{landingRoute}/asientos/{busUnitSeat}/hold', [SeatHoldController::class, 'destroy'])->name('travel.seats.hold.destroy');

    Route::prefix('dashboard')->name('cliente.')->group(function () {
        Route::get('/carrito', [ClientDashboardController::class, 'carrito'])->name('carrito');
        Route::get('/compras', [ClientDashboardController::class, 'compras'])->name('compras');
        Route::get('/paquetes', [ClientDashboardController::class, 'paquetes'])->name('paquetes');
        Route::get('/boletos', [ClientDashboardController::class, 'boletos'])->name('boletos');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified', 'superadmin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/viajes', [AdminController::class, 'viajes'])->name('viajes');
    Route::post('/viajes', [AdminLandingRouteController::class, 'store'])->name('viajes.store');
    Route::get('/viajes/{landingRoute}/edit', [AdminLandingRouteController::class, 'edit'])->name('viajes.edit');
    Route::put('/viajes/{landingRoute}', [AdminLandingRouteController::class, 'update'])->name('viajes.update');
    Route::patch('/viajes/{landingRoute}/toggle-featured', [AdminLandingRouteController::class, 'toggleFeatured'])->name('viajes.toggle-featured');
    Route::delete('/viajes/{landingRoute}', [AdminLandingRouteController::class, 'destroy'])->name('viajes.destroy');
    Route::get('/unidades', [AdminBusUnitController::class, 'index'])->name('unidades');
    Route::post('/unidades', [AdminBusUnitController::class, 'store'])->name('unidades.store');
    Route::get('/unidades/{busUnit}/editar', [AdminBusUnitController::class, 'edit'])->name('unidades.edit');
    Route::put('/unidades/{busUnit}', [AdminBusUnitController::class, 'update'])->name('unidades.update');
    Route::put('/unidades/{busUnit}/asientos', [AdminBusUnitSeatController::class, 'sync'])->name('unidades.seats.sync');
    Route::delete('/unidades/{busUnit}', [AdminBusUnitController::class, 'destroy'])->name('unidades.destroy');
    Route::get('/ventas', [AdminController::class, 'ventas'])->name('ventas');
    Route::get('/usuarios/crear', [AdminUserController::class, 'create'])->name('usuarios.create');
    Route::post('/usuarios', [AdminUserController::class, 'store'])->name('usuarios.store');
    Route::get('/configuraciones', [AdminSettingController::class, 'edit'])->name('configuraciones');
    Route::put('/configuraciones', [AdminSettingController::class, 'update'])->name('configuraciones.update');
});

Route::middleware(['auth', 'verified', 'paqueteria.access'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/paqueteria', [AdminPackageController::class, 'index'])->name('paqueteria');
    Route::get('/paqueteria/qr/crear', [AdminPackageController::class, 'qrCreate'])->name('paqueteria.qr.create');
    Route::post('/paqueteria/qr', [AdminPackageController::class, 'qrStore'])->name('paqueteria.qr.store');
    Route::get('/paqueteria/qr/lotes', [AdminPackageController::class, 'qrBatches'])->name('paqueteria.qr.batches');
    Route::get('/paqueteria/qr/lotes/{batch}/pdf', [AdminPackageController::class, 'qrBatchDownload'])->name('paqueteria.qr.batches.download');
    Route::post('/paqueteria/buscar', [AdminPackageController::class, 'lookup'])->name('paqueteria.paquetes.lookup');
    Route::get('/paqueteria/paquetes/{package}', [AdminPackageController::class, 'show'])->name('paqueteria.paquetes.show');
    Route::get('/paqueteria/paquetes/{package}/foto', [AdminPackageController::class, 'photo'])->name('paqueteria.paquetes.photo');
    Route::post('/paqueteria/paquetes/{package}/asignar', [AdminPackageController::class, 'assign'])->name('paqueteria.paquetes.assign');
    Route::post('/paqueteria/paquetes/{package}/estado', [AdminPackageController::class, 'updateStatus'])->name('paqueteria.paquetes.update-status');
    Route::delete('/paqueteria/paquetes/{package}', [AdminPackageController::class, 'destroy'])->name('paqueteria.paquetes.destroy');
});

require __DIR__.'/auth.php';
