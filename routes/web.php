<?php

use App\Http\Controllers\Admin\AdminBusUnitController;
use App\Http\Controllers\Admin\AdminBusUnitSeatController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminCustomerController;
use App\Http\Controllers\Admin\AdminDestinationController;
use App\Http\Controllers\Admin\AdminLandingRouteController;
use App\Http\Controllers\Admin\AdminPackageController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminPaymentMethodController;
use App\Http\Controllers\Admin\AdminSeatReservationController;
use App\Http\Controllers\Admin\AdminSettingController;
use App\Http\Controllers\Admin\AdminTripCheckinController;
use App\Http\Controllers\Admin\AdminTripGuideController;
use App\Http\Controllers\Admin\AdminTripTicketPriceController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminWhatsAppController;
use App\Http\Controllers\ClientDashboardController;
use App\Http\Controllers\PackageTrackingController;
use App\Http\Controllers\GuestTicketLookupController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SeatHoldController;
use App\Http\Controllers\SeatPickerController;
use App\Models\LandingRoute;
use App\Models\Setting;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Only real, admin-configured origin/destination pairs are offered in
    // the search box — the company doesn't cover "all of Mexico", so
    // free-text city entry would let visitors search for routes that
    // don't exist.
    $routePairs = LandingRoute::query()
        ->where('is_active', true)
        ->select('from', 'to')
        ->distinct()
        ->orderBy('from')
        ->orderBy('to')
        ->get();

    return view('welcome', [
        'setting' => Setting::current(),
        'routePairs' => $routePairs,
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

// Guest ticket lookup — the "I bought without an account, where are my
// tickets?" flow. Public (gates by the customer_name + customer_phone
// pair instead of auth, see GuestTicketLookupController for the
// privacy/scope reasoning).
Route::get('/mis-boletos', [GuestTicketLookupController::class, 'index'])->name('guest.tickets.lookup');
Route::post('/mis-boletos', [GuestTicketLookupController::class, 'lookup'])->name('guest.tickets.lookup.search');

// Online seat-picker flow: open to guests too. The form requires
// customer_name + customer_phone when there's no logged-in user (see
// SeatPickerController::store), but no auth middleware is needed for
// browsing or buying — guests can complete the whole checkout with
// just name + phone. Holds from this flow DO still require auth (the
// 10-minute soft-lock while the form is being filled), so they're
// kept in their own sub-group below.
Route::get('/viajes/{landingRoute}/asientos', [SeatPickerController::class, 'show'])->name('travel.seats');
Route::post('/viajes/{landingRoute}/asientos', [SeatPickerController::class, 'store'])->name('travel.seats.store');

// Payment result pages. The store() redirect lands here after the
// store() finishes, so the customer always sees a server-rendered
// receipt / barcode / error. Intentionally public for the same
// guest-buy reason as above.
Route::get('/pago/{reservation}/exitoso', [SeatPickerController::class, 'success'])->name('travel.payment.success');
Route::get('/pago/{reservation}/pendiente', [SeatPickerController::class, 'pending'])->name('travel.payment.pending');
Route::get('/pago/{reservation}/error', [SeatPickerController::class, 'error'])->name('travel.payment.error');
Route::post('/pago/{reservation}/comprobante', [SeatPickerController::class, 'uploadTransferProof'])->name('travel.payment.transfer.proof');

// Per-seat "soft lock" while someone fills the form — now open to
// guests too. Their hold is keyed by the Laravel session id (every
// browser already has the cookie), so two guests on the same seat
// still see a conflict and only one of them passes the transactional
// check on submit. The transactional guard in
// SeatPickerController::store() remains the real authority — the
// hold is just a UX hint so the second buyer knows to pick something
// else instead of waiting 10 min for nothing.
Route::post('/viajes/{landingRoute}/asientos/{busUnitSeat}/hold', [SeatHoldController::class, 'store'])->name('travel.seats.hold');
Route::delete('/viajes/{landingRoute}/asientos/{busUnitSeat}/hold', [SeatHoldController::class, 'destroy'])->name('travel.seats.hold.destroy');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [ClientDashboardController::class, 'index'])->name('dashboard');

    Route::prefix('dashboard')->name('cliente.')->group(function () {
        Route::get('/compras', [ClientDashboardController::class, 'compras'])->name('compras');
        Route::get('/paquetes', [ClientDashboardController::class, 'paquetes'])->name('paquetes');
        Route::get('/boletos', [ClientDashboardController::class, 'boletos'])->name('boletos');
        Route::get('/boletos/{reservation}', [ClientDashboardController::class, 'verBoleto'])->name('boletos.ver');
        Route::post('/boletos/{reservation}/regreso/solicitar', [ClientDashboardController::class, 'requestReturnChange'])->name('boletos.return-change.request');
        Route::get('/boletos/{reservation}/regreso/elegir', [ClientDashboardController::class, 'chooseReturnDate'])->name('boletos.return-change.choose');
        Route::post('/boletos/{reservation}/regreso/confirmar', [ClientDashboardController::class, 'confirmReturnChange'])->name('boletos.return-change.confirm');
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
    Route::get('/pagos', [AdminPaymentController::class, 'index'])->name('pagos.index');
    Route::get('/pagos/{reservation}', [AdminPaymentController::class, 'show'])->name('pagos.show');
    Route::post('/pagos/{reservation}/reembolsar', [AdminPaymentController::class, 'refund'])->name('pagos.refund');
    Route::post('/pagos/{reservation}/liberar-regreso', [AdminPaymentController::class, 'releaseReturn'])->name('pagos.release-return');
    Route::get('/pagos/{reservation}/comprobante', [AdminPaymentController::class, 'transferProof'])->name('pagos.transfer-proof');
    Route::post('/pagos/{reservation}/validar-transferencia', [AdminPaymentController::class, 'validateTransfer'])->name('pagos.validate-transfer');
    Route::post('/pagos/{reservation}/rechazar-transferencia', [AdminPaymentController::class, 'rejectTransfer'])->name('pagos.reject-transfer');
    // Cash-payment activation at the ticket window: the staff confirms
    // they received the cash, the QR + ticket are generated, and the
    // server redirects to a printable view so they can hand the customer
    // the physical ticket on the spot.
    Route::post('/pagos/{reservation}/confirmar-efectivo', [AdminPaymentController::class, 'confirmCash'])->name('pagos.confirm-cash');
    Route::get('/pagos/{reservation}/boleto-efectivo', [AdminPaymentController::class, 'cashTicket'])->name('pagos.cash-ticket');
    Route::get('/pagos/{reservation}/boleto-efectivo/imagen', [AdminPaymentController::class, 'cashTicketImage'])->name('pagos.cash-ticket-image');
    Route::get('/asientos', [AdminSeatReservationController::class, 'index'])->name('asientos.index');
    Route::get('/asientos/{landingRoute}', [AdminSeatReservationController::class, 'show'])->name('asientos.show');
    Route::post('/asientos/{landingRoute}', [AdminSeatReservationController::class, 'store'])->name('asientos.store');
    Route::post('/asientos/{landingRoute}/reservas/{reservation}/enviar', [AdminSeatReservationController::class, 'sendTicket'])->name('asientos.send');
    Route::put('/asientos/{landingRoute}/reservas/{reservation}/categoria', [AdminSeatReservationController::class, 'updateCategory'])->name('asientos.update-category');
    Route::delete('/asientos/{landingRoute}/reservas/{reservation}', [AdminSeatReservationController::class, 'destroy'])->name('asientos.destroy');
    Route::delete('/asientos/{landingRoute}/reservas/{reservation}/asiento', [AdminSeatReservationController::class, 'removeSeat'])->name('asientos.remove-seat');
    Route::get('/asientos/{landingRoute}/lista', [AdminSeatReservationController::class, 'manifest'])->name('asientos.manifest');
    Route::get('/asientos/{landingRoute}/disponibilidad', [AdminSeatReservationController::class, 'availability'])->name('asientos.availability');
    Route::put('/asientos/{landingRoute}/disponibilidad', [AdminSeatReservationController::class, 'updateAvailability'])->name('asientos.availability.update');
    Route::get('/precios', [AdminTripTicketPriceController::class, 'index'])->name('precios.index');
    Route::post('/precios', [AdminTripTicketPriceController::class, 'update'])->name('precios.update');
    Route::get('/guias', [AdminTripGuideController::class, 'index'])->name('guias.index');

    // Master list of cities the company serves — powers the from/to
    // <select> in the trip create/edit form so the operator doesn't
    // re-type the same place name in three slightly different ways.
    Route::resource('destinations', AdminDestinationController::class)->except(['show']);
    Route::resource('agenda', AdminCustomerController::class)->only(['index', 'store', 'update', 'destroy'])->parameters(['agenda' => 'customer']);
    Route::get('/guias/crear', [AdminTripGuideController::class, 'create'])->name('guias.create');
    Route::post('/guias', [AdminTripGuideController::class, 'store'])->name('guias.store');
    Route::get('/guias/{guide}', [AdminTripGuideController::class, 'show'])->name('guias.show');
    Route::get('/guias/{guide}/editar', [AdminTripGuideController::class, 'edit'])->name('guias.edit');
    Route::put('/guias/{guide}', [AdminTripGuideController::class, 'update'])->name('guias.update');
    Route::delete('/guias/{guide}', [AdminTripGuideController::class, 'destroy'])->name('guias.destroy');
    Route::post('/guias/{guide}/reservas', [AdminTripGuideController::class, 'storeReservation'])->name('guias.reservations.store');
    Route::delete('/guias/{guide}/reservas/{reservation}', [AdminTripGuideController::class, 'destroyReservation'])->name('guias.reservations.destroy');
    // Operator-side QR check-in. The {code?} part is optional so
    // /admin/checkin (no code) lands on the search form, and
    // /admin/checkin/{code} (the QR target) lands straight on the
    // ticket detail.
    Route::get('/checkin', [AdminTripCheckinController::class, 'index'])->name('checkin.index');
    Route::post('/checkin', [AdminTripCheckinController::class, 'lookup'])->name('checkin.lookup');
    Route::get('/checkin/{code}', [AdminTripCheckinController::class, 'lookup'])->name('checkin.scan');
    Route::post('/checkin/{reservation}/outbound', [AdminTripCheckinController::class, 'verifyOutbound'])->name('checkin.outbound');
    Route::post('/checkin/{reservation}/return', [AdminTripCheckinController::class, 'verifyReturn'])->name('checkin.return');
    Route::post('/checkin/{reservation}/reprogramar-regreso', [AdminTripCheckinController::class, 'rescheduleReturn'])->name('checkin.reschedule-return');
    Route::get('/usuarios', [AdminUserController::class, 'index'])->name('usuarios.index');
    Route::get('/usuarios/crear', [AdminUserController::class, 'create'])->name('usuarios.create');
    Route::post('/usuarios', [AdminUserController::class, 'store'])->name('usuarios.store');
    Route::delete('/usuarios/{user}', [AdminUserController::class, 'destroy'])->name('usuarios.destroy');
    Route::get('/configuraciones', [AdminSettingController::class, 'edit'])->name('configuraciones');
    Route::put('/configuraciones', [AdminSettingController::class, 'update'])->name('configuraciones.update');

    Route::prefix('metodos-pago')->name('payment-methods.')->group(function () {
        Route::get('/', [AdminPaymentMethodController::class, 'index'])->name('index');
        Route::post('/', [AdminPaymentMethodController::class, 'store'])->name('store');
        Route::put('/{paymentMethod}', [AdminPaymentMethodController::class, 'update'])->name('update');
        Route::delete('/{paymentMethod}', [AdminPaymentMethodController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('whatsapp')->name('whatsapp.')->group(function () {
        Route::get('/', [AdminWhatsAppController::class, 'edit'])->name('edit');
        Route::put('/', [AdminWhatsAppController::class, 'update'])->name('update');
        Route::get('/qr', [AdminWhatsAppController::class, 'qrCode'])->name('qr');
        Route::get('/status', [AdminWhatsAppController::class, 'status'])->name('status');
        Route::post('/desconectar', [AdminWhatsAppController::class, 'disconnect'])->name('disconnect');
    });
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

// OpenPay webhook route removed: OpenPay is disabled for now, so
// there's no public endpoint OpenPay would POST to. Re-add
// `webhooks/openpay` (pointing at OpenPayWebhookController::handle)
// here if/when the gateway comes back.

require __DIR__.'/auth.php';
