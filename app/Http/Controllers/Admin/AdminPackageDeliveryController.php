<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Services\EvolutionWhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Entrega de un envío: se eligen por folio los paquetes que sí se entregaron
 * (los demás siguen pendientes), se captura el costo si faltaba y cuánto se
 * cobró (lo que falte queda como pendiente del cliente), con foto opcional.
 * Al final se avisa por WhatsApp.
 */
class AdminPackageDeliveryController extends Controller
{
    public function store(Request $request, Package $package, EvolutionWhatsAppService $whatsapp): RedirectResponse
    {
        abort_if($package->status === Package::STATUS_SIN_ASIGNAR, 409, 'Este paquete aún no ha sido registrado.');

        $data = $request->validate([
            'package_ids' => ['required', 'array', 'min:1'],
            'package_ids.*' => ['integer'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'photo' => ['nullable', 'image', 'max:8192'],
        ]);

        $unit = $package->billingUnit();
        $members = $package->isGrouped() ? $package->group->packages : collect([$package]);
        $selected = $members->whereIn('id', $data['package_ids'])->where('status', '!=', Package::STATUS_ENTREGADO);

        if ($selected->isEmpty()) {
            return back()->withErrors(['package_ids' => 'Selecciona al menos un folio que aún no esté entregado.']);
        }

        // El costo solo se captura si al inicio no se puso.
        $priceMissing = ! ($unit->billedAmount() > 0);
        $newPrice = $priceMissing && isset($data['price']) ? (float) $data['price'] : null;
        $billed = $newPrice ?? $unit->billedAmount();
        $paidNow = (float) ($data['amount_paid'] ?? 0);

        if ($paidNow > 0 && ! ($billed > 0)) {
            return back()->withErrors(['price' => 'Captura el costo del envío antes de registrar un cobro.'])->withInput();
        }
        if ($paidNow > round($billed - (float) $unit->amount_paid, 2) + 0.001 && $billed !== null) {
            return back()->withErrors(['amount_paid' => 'El cobro no puede ser mayor al saldo del envío ($'.number_format($billed - (float) $unit->amount_paid, 2).').'])->withInput();
        }

        $photoPath = $request->hasFile('photo') ? $this->storePhoto($request->file('photo')) : null;

        DB::transaction(function () use ($unit, $selected, $newPrice, $paidNow, $photoPath, $request) {
            if ($newPrice !== null) {
                $unit->update([$unit instanceof Package ? 'price' : 'total_price' => $newPrice]);
            }

            foreach ($selected as $p) {
                $p->update([
                    'status' => Package::STATUS_ENTREGADO,
                    'delivered_at' => now(),
                    'delivery_photo_path' => $photoPath,
                ]);
            }

            $unit->registerPayment($paidNow, $request->user()->id);
        });

        $unit->refresh();
        $pending = Package::whereIn('id', $members->pluck('id'))->get()->where('status', '!=', Package::STATUS_ENTREGADO);
        $label = fn ($ps) => $ps->sortBy('folio_number')->map->folio_label->implode(', ');

        $sent = true;
        try {
            $whatsapp->sendPackageDelivery(
                $package->displayPhone() ?? '',
                $package->displayClientName() ?? '',
                $label($selected),
                $label($pending),
                $paidNow,
                $unit->pendingAmount(),
            );
        } catch (Throwable $e) {
            $sent = false;
            Log::warning('Package delivery WhatsApp notice failed', ['package' => $package->id, 'error' => $e->getMessage()]);
        }

        $msg = 'Entrega registrada'.($pending->isNotEmpty() ? ' (quedan pendientes: '.$label($pending).')' : '').'.'
            .($unit->pendingAmount() > 0 ? ' Saldo pendiente: $'.number_format($unit->pendingAmount(), 2).'.' : '')
            .($sent ? ' Se avisó por WhatsApp.' : ' No pudimos enviar el WhatsApp.');

        return redirect()->route('admin.paqueteria.paquetes.show', $package)->with('success', $msg);
    }

    private function storePhoto($file): string
    {
        $path = 'paqueteria/entregas/'.now()->format('Y/m').'/'.now()->timestamp.'-'.uniqid().'.'.$file->extension();
        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }
}
