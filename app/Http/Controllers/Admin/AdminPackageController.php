<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackageClient;
use App\Models\PackageFolioSequence;
use App\Models\PackageGroup;
use App\Models\PackagePhoto;
use App\Models\PackageQrBatch;
use App\Services\EvolutionWhatsAppService;
use Barryvdh\DomPDF\Facade\Pdf;
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
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'historial' ? 'historial' : 'activos';
        $tabStatuses = $tab === 'historial' ? Package::HISTORY_STATUSES : Package::ACTIVE_STATUSES;

        $status = $request->query('status');
        $status = in_array($status, $tabStatuses, true) ? $status : null;

        $packages = Package::query()
            ->whereIn('status', $tabStatuses)
            ->when($status, fn ($query, $value) => $query->status($value))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.paqueteria', [
            'packages' => $packages,
            'statuses' => $tabStatuses,
            'status' => $status,
            'tab' => $tab,
        ]);
    }

    /**
     * The one QR that ever needs printing — always the same fixed URL, so
     * it never needs regenerating. Staff scan it with their own phone to
     * jump straight into registerCreate() instead of navigating menus.
     */
    public function qrUniversal(): View
    {
        $svg = (new Builder(
            writer: new SvgWriter,
            data: url('/admin/paqueteria/registrar'),
            size: 280,
            margin: 10,
        ))->build()->getString();

        return view('admin.paqueteria.qr-universal', ['svg' => $svg]);
    }

    /**
     * Opened by the universal QR. The people who scan it are not users, so
     * this page needs NO login: a visitor gets a stripped-down form (no list
     * of known clients, no price); staff who are logged in keep the full one.
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
            'price' => ['nullable', 'numeric', 'min:0'],
            // One or several evidence photos: `photos[]` (new form) or the
            // single `photo` field (kept so older clients keep working).
            'photo' => ['required_without:photos', 'nullable', 'image', 'max:8192'],
            'photos' => ['required_without:photo', 'nullable', 'array', 'min:1', 'max:10'],
            'photos.*' => ['image', 'max:8192'],
        ]);

        // A visitor cannot set the price: staff fill that in afterwards.
        if (! $isStaff) {
            $validated['price'] = null;
        }

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
                    'price' => $validated['price'] ?? null,
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
                'total_price' => $validated['price'] ?? null,
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
                isset($validated['price']) ? (float) $validated['price'] : null,
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
     * Pre-prints $qty blank folio labels (no QR anymore — just the
     * number) to stick on physical packages ahead of time. Reserves the
     * range immediately so two batches can never collide; never expires,
     * so leftovers from one print run are just as valid the next time
     * registerStore() needs folios.
     */
    public function folioGuideCreate(): View
    {
        return view('admin.paqueteria.folios-crear');
    }

    public function folioGuideStore(Request $request): View
    {
        $validated = $request->validate([
            'qty' => ['required', 'integer', 'min:1', 'max:200'],
        ]);

        [$first, $last] = PackageFolioSequence::reserve((int) $validated['qty']);

        $packages = collect(range($first, $last))->map(fn (int $folio) => Package::create([
            'tracking_code' => Package::generateTrackingCode(),
            'folio_number' => $folio,
            'status' => Package::STATUS_SIN_ASIGNAR,
            'generated_by' => $request->user()->id,
        ]));

        // The printable on-screen sheet is what actually matters for this
        // request — if PDF rendering/storage fails for any reason, the
        // batch of folios already exists and is already usable, so don't
        // let this turn a successful batch generation into an error page.
        try {
            $this->saveBatchPdf($packages, $request->user()->id);
        } catch (Throwable $e) {
            Log::warning('Folio guide PDF generation failed', ['error' => $e->getMessage()]);
        }

        return view('admin.paqueteria.folios-lote', ['packages' => $packages]);
    }

    /**
     * Renders the same batch as a PDF and stores it on the private disk
     * under a Y/m folder so past batches stay organized by the date they
     * were generated, for reprinting later.
     *
     * @param  Collection<int, Package>  $packages
     */
    private function saveBatchPdf(Collection $packages, ?int $generatedBy): void
    {
        $pdfBytes = Pdf::loadView('admin.paqueteria.folios-lote-pdf', [
            'packages' => $packages,
        ])->output();

        $path = 'paqueteria/folio-lotes/'.now()->format('Y/m').'/'.now()->format('Y-m-d_His').'.pdf';
        Storage::disk('local')->put($path, $pdfBytes);

        PackageQrBatch::create([
            'qty' => $packages->count(),
            'pdf_path' => $path,
            'generated_by' => $generatedBy,
        ]);
    }

    public function qrBatches(): View
    {
        $batches = PackageQrBatch::with('generatedBy')
            ->latest()
            ->limit(100)
            ->get()
            ->groupBy(fn (PackageQrBatch $batch) => $batch->created_at->format('d/m/Y'));

        return view('admin.paqueteria.folios-lotes', [
            'batchesByDate' => $batches,
        ]);
    }

    public function qrBatchDownload(PackageQrBatch $batch)
    {
        abort_unless(Storage::disk('local')->exists($batch->pdf_path), 404);

        return Storage::disk('local')->download($batch->pdf_path, 'paqueteria-folios-'.$batch->created_at->format('Y-m-d_His').'.pdf');
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
            'package' => $package->load('group.packages'),
            'unit' => $package->billingUnit()->load('payments.user'),
        ]);
    }

    /**
     * Deletes one unused folio label at a time. Anything already
     * registered (recolectado, en_recorrido, entregado, no_entregado) is
     * permanent history and can never be deleted here.
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

    public function updateStatus(Request $request, Package $package): RedirectResponse
    {
        // La entrega pasa por su propio formulario (folios, cobro, evidencia y aviso).
        abort_if($request->input('status') === Package::STATUS_ENTREGADO, 409, 'Usa el formulario Entregar.');

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', [
                Package::STATUS_RECOLECTADO,
                Package::STATUS_EN_RECORRIDO,
                Package::STATUS_NO_ENTREGADO,
            ])],
        ]);

        abort_if($package->status === Package::STATUS_SIN_ASIGNAR, 409, 'Este paquete aún no ha sido registrado.');

        $package->update([
            'status' => $validated['status'],
        ]);

        return redirect()->route('admin.paqueteria.paquetes.show', $package)
            ->with('success', 'Estado actualizado.');
    }
}
