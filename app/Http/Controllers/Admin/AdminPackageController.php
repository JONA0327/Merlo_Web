<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackageFolioSequence;
use App\Models\PackageGroup;
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

    public function registerCreate(): View
    {
        return view('admin.paqueteria.registrar');
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
        $validated = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'qty' => ['required', 'integer', 'min:1', 'max:50'],
            'phone' => ['required', 'string', 'max:20'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'photo' => ['required', 'image', 'max:8192'],
        ]);

        $qty = (int) $validated['qty'];
        $photoPath = $this->storeEvidencePhoto($request->file('photo'));

        $collectedBy = $request->user()->id;
        $collectedAt = now();
        $isGroup = false;
        $root = null;

        DB::transaction(function () use ($qty, $validated, $photoPath, $collectedBy, $collectedAt, &$isGroup, &$root) {
            $packages = $this->claimOrMintFolios($qty, $collectedBy);

            if ($qty === 1) {
                $package = $packages->first();
                $package->update([
                    'client_name' => $validated['client_name'],
                    'phone' => $validated['phone'],
                    'price' => $validated['price'] ?? null,
                    'photo_path' => $photoPath,
                    'status' => Package::STATUS_RECOLECTADO,
                    'collected_by' => $collectedBy,
                    'collected_at' => $collectedAt,
                ]);

                $root = $package;

                return;
            }

            $group = PackageGroup::create([
                'client_name' => $validated['client_name'],
                'phone' => $validated['phone'],
                'total_price' => $validated['price'] ?? null,
                'photo_path' => $photoPath,
                'tracking_code' => PackageGroup::generateTrackingCode(),
                'created_by' => $collectedBy,
            ]);

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

    public function photo(Package $package)
    {
        $path = $package->displayPhotoPath();
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    public function updateStatus(Request $request, Package $package): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', [
                Package::STATUS_RECOLECTADO,
                Package::STATUS_EN_RECORRIDO,
                Package::STATUS_ENTREGADO,
                Package::STATUS_NO_ENTREGADO,
            ])],
        ]);

        abort_if($package->status === Package::STATUS_SIN_ASIGNAR, 409, 'Este paquete aún no ha sido registrado.');

        $package->update([
            'status' => $validated['status'],
            'delivered_at' => $validated['status'] === Package::STATUS_ENTREGADO ? now() : $package->delivered_at,
        ]);

        return redirect()->route('admin.paqueteria.paquetes.show', $package)
            ->with('success', 'Estado actualizado.');
    }
}
