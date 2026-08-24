<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PackageTrackingMail;
use App\Models\Package;
use App\Models\PackageGroup;
use App\Models\PackageQrBatch;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

    public function qrCreate(): View
    {
        return view('admin.paqueteria.qr-crear');
    }

    public function qrBatches(): View
    {
        $batches = PackageQrBatch::with('generatedBy')
            ->latest()
            ->limit(100)
            ->get()
            ->groupBy(fn (PackageQrBatch $batch) => $batch->created_at->format('d/m/Y'));

        return view('admin.paqueteria.qr-lotes', [
            'batchesByDate' => $batches,
        ]);
    }

    public function qrBatchDownload(PackageQrBatch $batch)
    {
        abort_unless(Storage::disk('local')->exists($batch->pdf_path), 404);

        return Storage::disk('local')->download($batch->pdf_path, 'paqueteria-qr-'.$batch->created_at->format('Y-m-d_His').'.pdf');
    }

    public function qrStore(Request $request): View
    {
        $validated = $request->validate([
            'qty' => ['required', 'integer', 'min:1', 'max:200'],
        ]);

        $packages = collect();

        for ($i = 0; $i < (int) $validated['qty']; $i++) {
            $packages->push(Package::create([
                'tracking_code' => Package::generateTrackingCode(),
                'status' => Package::STATUS_SIN_ASIGNAR,
                'generated_by' => $request->user()->id,
            ]));
        }

        $svgWriter = new SvgWriter;
        $svgs = [];

        foreach ($packages as $package) {
            $result = (new Builder(
                writer: $svgWriter,
                data: url('/admin/paqueteria/paquetes/'.$package->tracking_code),
                size: 220,
                margin: 8,
            ))->build();

            $svgs[$package->id] = $result->getString();
        }

        // The printable on-screen sheet is what actually matters for this
        // request — if PDF rendering/storage fails for any reason, the
        // batch of packages already exists and is already usable, so don't
        // let this turn a successful batch generation into an error page.
        try {
            $this->saveBatchPdf($packages, $request->user()->id);
        } catch (Throwable $e) {
            Log::warning('QR batch PDF generation failed', ['error' => $e->getMessage()]);
        }

        return view('admin.paqueteria.qr-lote', [
            'packages' => $packages,
            'svgs' => $svgs,
        ]);
    }

    /**
     * Renders the same batch as a PDF (PNG-embedded, plain-CSS template —
     * dompdf's CSS engine can't handle Tailwind's compiled output) and
     * stores it on the private disk under a Y/m folder so past batches stay
     * organized by the date they were generated, for reprinting later.
     *
     * @param  Collection<int, Package>  $packages
     */
    private function saveBatchPdf(Collection $packages, ?int $generatedBy): void
    {
        $pngWriter = new PngWriter;
        $pngs = [];

        foreach ($packages as $package) {
            $result = (new Builder(
                writer: $pngWriter,
                data: url('/admin/paqueteria/paquetes/'.$package->tracking_code),
                size: 220,
                margin: 8,
            ))->build();

            $pngs[$package->id] = $result->getDataUri();
        }

        $pdfBytes = Pdf::loadView('admin.paqueteria.qr-lote-pdf', [
            'packages' => $packages,
            'pngs' => $pngs,
        ])->output();

        $path = 'paqueteria/qr-lotes/'.now()->format('Y/m').'/'.now()->format('Y-m-d_His').'.pdf';
        Storage::disk('local')->put($path, $pdfBytes);

        PackageQrBatch::create([
            'qty' => $packages->count(),
            'pdf_path' => $path,
            'generated_by' => $generatedBy,
        ]);
    }

    public function lookup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $package = Package::where('tracking_code', strtoupper(trim($validated['code'])))->first();

        if (! $package) {
            return back()->withErrors(['code' => 'No encontramos ese código.']);
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
     * Deletes one unused QR label at a time. Anything already registered
     * (recolectado, en_recorrido, entregado, no_entregado) is permanent
     * history and can never be deleted here.
     */
    public function destroy(Package $package): RedirectResponse
    {
        abort_if($package->status !== Package::STATUS_SIN_ASIGNAR, 409, 'Solo se pueden eliminar códigos sin asignar.');

        $package->delete();

        return redirect()->route('admin.paqueteria')->with('success', 'Código '.$package->tracking_code.' eliminado.');
    }

    public function assign(Request $request, Package $package): RedirectResponse
    {
        $validated = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['required', 'email', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'photo' => ['required', 'image', 'max:8192'],
            'codes' => ['nullable', 'array'],
            'codes.*' => ['string'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'max:8192'],
        ]);

        abort_if($package->status !== Package::STATUS_SIN_ASIGNAR, 409, 'Este paquete ya fue registrado.');

        $extraCodes = collect($validated['codes'] ?? [])
            ->map(fn ($code) => strtoupper(trim($code)))
            ->filter(fn ($code) => $code !== $package->tracking_code)
            ->unique()
            ->values();

        $extraPhotos = collect($request->file('photos', []));

        // Every extra package needs its own evidence photo too — the form
        // submits codes[] and photos[] in lockstep (same order, same count),
        // so a mismatch here means the client-side pairing broke somehow.
        abort_if($extraCodes->count() !== $extraPhotos->count(), 422, 'Cada paquete adicional necesita su propia foto.');

        // Photo storage isn't transactional, so save files first — if this
        // throws, nothing in the database has changed yet.
        $photoPathFor = [$package->tracking_code => $this->storeEvidencePhoto($request->file('photo'), $package->tracking_code)];
        foreach ($extraCodes as $index => $code) {
            $photoPathFor[$code] = $this->storeEvidencePhoto($extraPhotos[$index], $code);
        }

        $trackingCodeForEmail = null;
        $isGroup = false;
        $packageCount = 1;

        DB::transaction(function () use ($package, $extraCodes, $validated, $request, $photoPathFor, &$trackingCodeForEmail, &$isGroup, &$packageCount) {
            $isBundle = $extraCodes->isNotEmpty();
            // Collection::prepend() mutates in place, so build a fresh
            // collection here rather than reusing $extraCodes afterward.
            $allCodes = collect([$package->tracking_code])->concat($extraCodes);

            $locked = Package::query()
                ->whereIn('tracking_code', $allCodes)
                ->lockForUpdate()
                ->get()
                ->keyBy('tracking_code');

            foreach ($allCodes as $code) {
                $locked->has($code) or abort(422, "El código {$code} no existe.");
                $locked->get($code)->status === Package::STATUS_SIN_ASIGNAR
                    or abort(422, "El código {$code} ya fue asignado o no está disponible.");
            }

            $collectedAt = now();
            $collectedBy = $request->user()->id;

            if (! $isBundle) {
                $package->update([
                    'client_name' => $validated['client_name'],
                    'client_email' => $validated['client_email'],
                    'price' => $validated['price'],
                    'photo_path' => $photoPathFor[$package->tracking_code],
                    'status' => Package::STATUS_RECOLECTADO,
                    'collected_by' => $collectedBy,
                    'collected_at' => $collectedAt,
                ]);

                $trackingCodeForEmail = $package->tracking_code;
                $packageCount = 1;

                return;
            }

            $group = PackageGroup::create([
                'client_name' => $validated['client_name'],
                'client_email' => $validated['client_email'],
                'total_price' => $validated['price'],
                'tracking_code' => PackageGroup::generateTrackingCode(),
                'created_by' => $collectedBy,
            ]);

            $locked->each(function (Package $locked) use ($group, $collectedBy, $collectedAt, $photoPathFor) {
                $locked->update([
                    'package_group_id' => $group->id,
                    'client_name' => null,
                    'client_email' => null,
                    'price' => null,
                    'photo_path' => $photoPathFor[$locked->tracking_code],
                    'status' => Package::STATUS_RECOLECTADO,
                    'collected_by' => $collectedBy,
                    'collected_at' => $collectedAt,
                ]);
            });

            $trackingCodeForEmail = $group->tracking_code;
            $isGroup = true;
            $packageCount = $locked->count();
        });

        // The package is already saved by this point — a flaky mail server
        // must never turn an already-successful registration into an error
        // for the staff member. Losing the notification email is recoverable
        // (the tracking code is still visible on this same page); losing the
        // registration itself is not.
        $emailSent = true;

        try {
            Mail::to($validated['client_email'])->send(
                new PackageTrackingMail($validated['client_name'], $trackingCodeForEmail, $isGroup, $packageCount)
            );
        } catch (Throwable $e) {
            $emailSent = false;
            Log::warning('Package tracking email failed to send', [
                'tracking_code' => $trackingCodeForEmail,
                'client_email' => $validated['client_email'],
                'error' => $e->getMessage(),
            ]);
        }

        $message = $emailSent
            ? 'Paquete registrado y código de rastreo enviado a '.$validated['client_email'].'.'
            : 'Paquete registrado (código '.$trackingCodeForEmail.'), pero no pudimos enviar el correo. Compártele el código manualmente.';

        return redirect()->route('admin.paqueteria.paquetes.show', $package)->with('success', $message);
    }

    /**
     * Evidence photo for this exact package (never the group's) — proof of
     * what was actually collected, to cut down on lost/mismatched packages.
     */
    private function storeEvidencePhoto(UploadedFile $file, string $trackingCode): string
    {
        $path = 'paqueteria/evidencias/'.now()->format('Y/m').'/'.$trackingCode.'-'.now()->timestamp.'.'.$file->extension();
        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    public function photo(Package $package)
    {
        abort_unless($package->photo_path && Storage::disk('local')->exists($package->photo_path), 404);

        return Storage::disk('local')->response($package->photo_path);
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
