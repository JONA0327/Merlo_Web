<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackageClient;
use App\Models\PackageFolioSequence;
use App\Models\PackageGroup;
use App\Models\PackagePhoto;
use App\Services\EvolutionWhatsAppService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class AdminPackageController extends Controller
{
    /** Portada: tarjetas hacia cada sección, con un par de contadores del día. */
    public function index(): View
    {
        return view('admin.paqueteria', [
            'recolectadosHoy' => Package::status(Package::STATUS_RECOLECTADO)->whereDate('collected_at', today())->count(),
            'entregadosHoy' => Package::status(Package::STATUS_ENTREGADO)->whereDate('delivered_at', today())->count(),
        ]);
    }

    /**
     * Paquetes reales (recolectado/entregado) agrupados por día de
     * recolección — los folios sin_asignar (etiquetas impresas sin usar)
     * son un detalle interno de "Generar folios" y nunca se listan aquí.
     */
    public function packagesIndex(Request $request): View
    {
        $date = \Illuminate\Support\Carbon::parse($request->query('fecha', today()->toDateString()))->startOfDay();

        $packages = Package::query()
            ->whereIn('status', [Package::STATUS_RECOLECTADO, Package::STATUS_ENTREGADO])
            ->whereDate('collected_at', $date)
            ->orderBy('folio_number')
            ->get();

        return view('admin.paqueteria.paquetes', [
            'date' => $date,
            'packages' => $packages,
        ]);
    }

    /** Registro permanente de lo entregado, con el cliente de cada envío. */
    public function historialIndex(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $folio = $request->query('folio');
        $folio = in_array($folio, ['con', 'sin'], true) ? $folio : 'todos';

        $packages = Package::query()
            ->status(Package::STATUS_ENTREGADO)
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->where('client_name', 'like', "%{$search}%")
                ->orWhereHas('group', fn ($g) => $g->where('client_name', 'like', "%{$search}%"))))
            ->when($folio === 'con', fn ($q) => $q->whereNotNull('folio_number'))
            ->when($folio === 'sin', fn ($q) => $q->whereNull('folio_number'))
            ->latest('delivered_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.paqueteria.historial', [
            'packages' => $packages,
            'search' => $search,
            'folio' => $folio,
        ]);
    }

    /**
     * The one QR that ever needs printing — always the same fixed URL, so
     * it never needs regenerating. Staff scan it with their own phone to
     * jump straight into registerCreate() instead of navigating menus.
     */
    public function qrUniversal(): View
    {
        $url = route('admin.paqueteria.registrar.create');

        $svg = (new Builder(
            writer: new SvgWriter,
            data: $url,
            size: 280,
            margin: 10,
        ))->build()->getString();

        return view('admin.paqueteria.qr-universal', ['svg' => $svg, 'url' => $url]);
    }

    /**
     * Opened by the universal QR. The people who scan it are not users, so
     * this page needs NO login: a visitor gets a stripped-down form (no list
     * of known clients); staff who are logged in keep the full one. Neither
     * sets a price here — that's captured later, at delivery.
     */
    public function registerCreate(Request $request): View
    {
        if (! $request->user()?->canAccessPaqueteria()) {
            return view('admin.paqueteria.registrar-publico');
        }

        return view('admin.paqueteria.registrar', [
            'clients' => PackageClient::orderBy('name')->get(['name', 'phone']),
        ]);
    }

    /**
     * Paquetes que ya se entregaron pero nunca se capturaron en el sistema:
     * sin folio, teléfono ni fotos. Solo nombre, costo y lo que quedó a deber
     * (ese saldo aparece en Clientes para cobrarlo después).
     */
    public function legacyCreate(): View
    {
        return view('admin.paqueteria.sin-folio');
    }

    public function legacyStore(Request $request): RedirectResponse
    {
        // Gratis: el costo y el saldo no se capturan; quedan en $0.
        if ($request->input('payment_method') === 'gratis') {
            $request->merge(['price' => 0, 'owed' => 0]);
        }

        $data = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'price' => ['required_unless:payment_method,gratis', 'nullable', 'numeric', 'min:0'],
            'owed' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'package_count' => ['required', 'integer', 'min:1', 'max:1000'],
            'payment_method' => ['required', 'in:efectivo,transferencia,tarjeta,gratis'],
            'delivered_on' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        // Gratis: no se cobra, queda en $0 y sin saldo pendiente.
        $free = $data['payment_method'] === 'gratis';
        $price = $free ? 0.0 : (float) $data['price'];
        $owed = $free ? 0.0 : (float) ($data['owed'] ?? 0);
        $deliveredAt = isset($data['delivered_on']) ? \Illuminate\Support\Carbon::parse($data['delivered_on'])->endOfDay()->min(now()) : now();

        // No se da de alta en Clientes (no hay teléfono ni folio): queda solo
        // con el nombre. Lo pagado se registra como cobro en la fecha de
        // entrega para que salga en el corte de ese día; lo que debe sale
        // en la lista de pendientes del corte.
        $package = DB::transaction(function () use ($data, $price, $owed, $free, $deliveredAt, $request) {
            $package = Package::create([
                'tracking_code' => Package::generateTrackingCode(),
                'status' => Package::STATUS_ENTREGADO,
                'client_name' => trim($data['client_name']),
                'price' => $price,
                'package_count' => (int) $data['package_count'],
                'amount_paid' => round($price - $owed, 2),
                'collected_by' => $request->user()->id,
                'collected_at' => $deliveredAt,
                'delivered_at' => $deliveredAt,
                'delivered_by' => $request->user()->id,
            ]);

            if ($free || $price - $owed > 0) {
                $package->payments()->create(['amount' => round($price - $owed, 2), 'method' => $data['payment_method'], 'user_id' => $request->user()->id, 'paid_at' => $deliveredAt]);
            }

            return $package;
        });

        return redirect()->route('admin.paqueteria.sin-folio.create')
            ->with('success', "Registrado: {$package->client_name}".($owed > 0 ? ' — debe $'.number_format($owed, 2).'.' : ' — pagado.'));
    }

    /** Thank-you page for someone who registered a package without logging in. */
    public function registerDone(Request $request)
    {
        $message = $request->session()->get('registered');

        return $message
            ? view('admin.paqueteria.registrado', ['message' => $message])
            : redirect()->route('admin.paqueteria.registrar.create');
    }

    /**
     * The core of the new flow: scanning the universal QR lands here.
     * Claims whatever folios were already pre-printed and unclaimed (see
     * folioGuideStore()) before minting fresh ones from the same global
     * series — either way the resulting range is always contiguous. 1
     * package updates its own row directly; 2+ bundle into a PackageGroup,
     * same branching assign() used to do.
     */
    public function registerStore(Request $request, EvolutionWhatsAppService $whatsapp): RedirectResponse
    {
        $isStaff = (bool) $request->user()?->canAccessPaqueteria();

        // Anyone with the QR can reach this: a bot that fills the hidden trap
        // field gets a normal-looking "done" page and nothing is created.
        if (! $isStaff && $request->filled('website')) {
            return redirect()->route('admin.paqueteria.registrar.done')->with('registered', 'Listo.');
        }

        $validated = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'qty' => ['required', 'integer', 'min:1', 'max:50'],
            'phone' => ['required', 'string', 'max:20'],
            // One or several evidence photos: `photos[]` (new form) or the
            // single `photo` field (kept so older clients keep working).
            'photo' => ['required_without:photos', 'nullable', 'image', 'max:8192'],
            'photos' => ['required_without:photo', 'nullable', 'array', 'min:1', 'max:10'],
            'photos.*' => ['image', 'max:8192'],
        ]);

        $qty = (int) $validated['qty'];
        $paths = collect([$request->file('photo')])->merge($request->file('photos', []))->filter()
            ->map(fn (UploadedFile $file) => $this->storeEvidencePhoto($file))->values();
        // The first photo stays in photo_path (what everything already reads);
        // the rest go to package_photos, attached to the package or its group.
        $photoPath = $paths->first();
        $extraPaths = $paths->slice(1);

        $client = PackageClient::forRegistration($validated['client_name'], $validated['phone']);
        $collectedBy = $request->user()?->id; // null when registered from the public QR form
        $collectedAt = now();
        $isGroup = false;
        $root = null;

        DB::transaction(function () use ($qty, $validated, $photoPath, $extraPaths, $client, $collectedBy, $collectedAt, &$isGroup, &$root) {
            $packages = $this->claimOrMintFolios($qty, $collectedBy);

            if ($qty === 1) {
                $package = $packages->first();
                $package->update([
                    'client_name' => $validated['client_name'],
                    'phone' => $validated['phone'],
                    'photo_path' => $photoPath,
                    'package_client_id' => $client->id,
                    'status' => Package::STATUS_RECOLECTADO,
                    'collected_by' => $collectedBy,
                    'collected_at' => $collectedAt,
                ]);

                $extraPaths->each(fn (string $path) => $package->extraPhotos()->create(['path' => $path]));
                $root = $package;

                return;
            }

            $group = PackageGroup::create([
                'client_name' => $validated['client_name'],
                'phone' => $validated['phone'],
                'photo_path' => $photoPath,
                'tracking_code' => PackageGroup::generateTrackingCode(),
                'package_client_id' => $client->id,
                'created_by' => $collectedBy,
            ]);

            $extraPaths->each(fn (string $path) => $group->extraPhotos()->create(['path' => $path]));

            $packages->each(fn (Package $package) => $package->update([
                'package_group_id' => $group->id,
                'client_name' => null,
                'phone' => null,
                'price' => null,
                'photo_path' => null,
                'status' => Package::STATUS_RECOLECTADO,
                'collected_by' => $collectedBy,
                'collected_at' => $collectedAt,
            ]));

            $isGroup = true;
            $root = $packages->sortBy('folio_number')->first();
        });

        $members = $isGroup ? $root->group->packages()->orderBy('folio_number')->get() : collect([$root]);
        $folioRange = $this->folioRangeLabel($members);

        $whatsappSent = true;

        try {
            $whatsapp->sendPackageRegistration(
                $validated['phone'],
                $validated['client_name'],
                $folioRange,
                $qty,
            );
        } catch (Throwable $e) {
            $whatsappSent = false;
            Log::warning('Package registration WhatsApp notice failed', [
                'folio_range' => $folioRange,
                'phone' => $validated['phone'],
                'error' => $e->getMessage(),
            ]);
        }

        $message = $whatsappSent
            ? "Paquete{$this->plural($qty)} registrado{$this->plural($qty)} (folio".($qty > 1 ? 's' : '')." {$folioRange}) — avisamos por WhatsApp a {$validated['client_name']}."
            : "Paquete{$this->plural($qty)} registrado{$this->plural($qty)} (folio".($qty > 1 ? 's' : '')." {$folioRange}), pero no pudimos enviar el WhatsApp. Comparte el folio manualmente.";

        if (! $isStaff) {
            $guestMessage = $whatsappSent
                ? "Registramos {$qty} paquete{$this->plural($qty)}. Tu folio: {$folioRange}. Te avisamos por WhatsApp al {$validated['phone']}."
                : "Registramos {$qty} paquete{$this->plural($qty)}. Tu folio: {$folioRange}. No pudimos enviarte el WhatsApp: anota tu folio.";

            return redirect()->route('admin.paqueteria.registrar.done')->with('registered', $guestMessage);
        }

        return redirect()->route('admin.paqueteria.paquetes.show', $root)->with('success', $message);
    }

    private function plural(int $qty): string
    {
        return $qty > 1 ? 's' : '';
    }

    /**
     * Claims the oldest already-reserved-but-unclaimed folios (from a
     * printed "guía de folios", see folioGuideStore()) first, then mints
     * whatever's left from the same global series — so a single call's
     * result is always a contiguous run of folio numbers, however many of
     * them happen to already exist as blank rows.
     *
     * @return Collection<int, Package>
     */
    private function claimOrMintFolios(int $qty, ?int $generatedBy): Collection
    {
        $claimed = Package::query()
            ->where('status', Package::STATUS_SIN_ASIGNAR)
            ->whereNotNull('folio_number')
            ->orderBy('folio_number')
            ->lockForUpdate()
            ->limit($qty)
            ->get();

        $remaining = $qty - $claimed->count();

        if ($remaining <= 0) {
            return $claimed;
        }

        [$first, $last] = PackageFolioSequence::reserve($remaining);

        $minted = collect(range($first, $last))->map(fn (int $folio) => Package::create([
            'tracking_code' => Package::generateTrackingCode(),
            'folio_number' => $folio,
            'status' => Package::STATUS_SIN_ASIGNAR,
            'generated_by' => $generatedBy,
        ]));

        return $claimed->concat($minted);
    }

    private function folioRangeLabel(Collection $packages): string
    {
        $numbers = $packages->pluck('folio_number')->filter()->sort()->values();

        if ($numbers->isEmpty()) {
            return '—';
        }

        $first = PackageFolioSequence::formatFolio($numbers->first());

        return $numbers->count() === 1 ? $first : $first.' a '.PackageFolioSequence::formatFolio($numbers->last());
    }

    /**
     * Pre-prints $qty blank folio labels, each with its own folio number
     * and a QR linking straight to that package's page, to stick on
     * physical packages ahead of time. Reserves the range immediately so
     * two batches can never collide; never expires, so leftovers from one
     * print run are just as valid the next time registerStore() needs
     * folios.
     */
    public function folioGuideCreate(): View
    {
        return view('admin.paqueteria.folios-crear');
    }

    /**
     * Mints $qty folios for printing. Unlike registerStore() (which always
     * claims any sin_asignar leftovers first, since that's just recording
     * data — not reprinting paper), reprinting the same folio numbers on a
     * new physical sheet risks ending up with two paper labels for the same
     * number. So if there are leftovers, we stop and ask the user whether
     * to include them in this batch instead of silently reusing them.
     */
    public function folioGuideStore(Request $request): View
    {
        $validated = $request->validate([
            'qty' => ['required', 'integer', 'min:1', 'max:200'],
            'confirmed' => ['nullable', 'boolean'],
            'include_leftovers' => ['nullable', 'boolean'],
        ]);

        $qty = (int) $validated['qty'];

        $leftovers = Package::query()
            ->where('status', Package::STATUS_SIN_ASIGNAR)
            ->whereNotNull('folio_number')
            ->orderBy('folio_number')
            ->get();

        if ($leftovers->isNotEmpty() && ! $request->boolean('confirmed')) {
            return view('admin.paqueteria.folios-sobrantes', [
                'qty' => $qty,
                'leftoverCount' => $leftovers->count(),
                'leftoverRange' => $this->folioRangeLabel($leftovers),
            ]);
        }

        $includeLeftovers = $request->boolean('include_leftovers');

        $packages = DB::transaction(fn () => $includeLeftovers
            ? $this->claimOrMintFolios($qty, $request->user()->id)
            : $this->mintFreshFolios($qty, $request->user()->id));
        $packages = $packages->sortBy('folio_number')->values();
        $packages->each(fn (Package $p) => $p->setAttribute('qr_svg', $this->packageQrSvg($p)));

        return view('admin.paqueteria.folios-lote', ['packages' => $packages]);
    }

    /**
     * The QR on a printed folio label: opens that exact package's page
     * (behind login) so staff can see its details and mark it delivered
     * straight from a phone, without typing the folio in by hand.
     */
    private function packageQrSvg(Package $package): string
    {
        return (new Builder(
            writer: new SvgWriter,
            data: route('admin.paqueteria.paquetes.show', $package),
            size: 130,
            margin: 4,
        ))->build()->getString();
    }

    /**
     * Mints $qty brand-new folios, ignoring any sin_asignar leftovers —
     * used when the user explicitly chose not to reprint them.
     *
     * @return Collection<int, Package>
     */
    private function mintFreshFolios(int $qty, ?int $generatedBy): Collection
    {
        [$first, $last] = PackageFolioSequence::reserve($qty);

        return collect(range($first, $last))->map(fn (int $folio) => Package::create([
            'tracking_code' => Package::generateTrackingCode(),
            'folio_number' => $folio,
            'status' => Package::STATUS_SIN_ASIGNAR,
            'generated_by' => $generatedBy,
        ]));
    }

    public function lookup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $term = strtoupper(trim($validated['code']));
        $digits = preg_replace('/\D/', '', $term);

        $package = $digits !== '' ? Package::where('folio_number', (int) $digits)->first() : null;

        // Legacy tracking_code lookup, kept for any pre-folio stock still
        // floating around (MP-XXXXXXXX) — not generated anymore, but
        // still a valid exact-match route-key if someone types one in.
        $package ??= Package::where('tracking_code', $term)->first();

        if (! $package) {
            return back()->withErrors(['code' => 'No encontramos ese folio.']);
        }

        return redirect()->route('admin.paqueteria.paquetes.show', $package);
    }

    public function show(Package $package): View
    {
        return view('admin.paqueteria.paquete', [
            'package' => $package->load('deliveredBy', 'group.packages.deliveredBy'),
            'unit' => $package->billingUnit()->load('payments.user'),
        ]);
    }

    /**
     * Deletes one unused folio label at a time. Anything already
     * registered (recolectado, entregado) is permanent history and can
     * never be deleted here.
     */
    public function destroy(Package $package): RedirectResponse
    {
        abort_if($package->status !== Package::STATUS_SIN_ASIGNAR, 409, 'Solo se pueden eliminar folios sin asignar.');

        $package->delete();

        return redirect()->route('admin.paqueteria')->with('success', 'Folio '.($package->folio_label ?? $package->tracking_code).' eliminado.');
    }

    /**
     * One evidence photo for the whole registration (not per package) —
     * lives on the Package row when it's a lone package, or on the
     * PackageGroup when registerStore() bundled several.
     */
    private function storeEvidencePhoto(UploadedFile $file): string
    {
        $path = 'paqueteria/evidencias/'.now()->format('Y/m').'/'.now()->timestamp.'-'.uniqid().'.'.$file->extension();
        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    /** One of the additional evidence photos of this package's registration. */
    public function extraPhoto(Package $package, PackagePhoto $photo)
    {
        $belongs = $package->isGrouped()
            ? $photo->package_group_id === $package->package_group_id
            : $photo->package_id === $package->id;
        abort_unless($belongs && Storage::disk('local')->exists($photo->path), 404);

        return Storage::disk('local')->response($photo->path);
    }

    public function photo(Package $package)
    {
        $path = $package->displayPhotoPath();
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    public function deliveryPhoto(Package $package)
    {
        abort_unless($package->delivery_photo_path && Storage::disk('local')->exists($package->delivery_photo_path), 404);

        return Storage::disk('local')->response($package->delivery_photo_path);
    }
}
