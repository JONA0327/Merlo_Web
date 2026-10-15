<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackageClient;
use App\Models\PackageGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPackageClientController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $clients = PackageClient::query()
            ->withCount(['packages', 'groups'])
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                // el teléfono solo se busca si lo escrito trae dígitos (si no, '%%' coincidiría con todos)
                ->when(preg_replace('/\D/', '', $search) !== '', fn ($w) => $w->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $search).'%'))))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $clients->getCollection()->each(fn (PackageClient $c) => $c->setAttribute('pending', $c->pendingAmount()));

        return view('admin.paqueteria.clientes', ['clients' => $clients, 'search' => $search]);
    }

    public function show(PackageClient $client): View
    {
        // Historial: cada paquete que ha llevado (sueltos y los de sus envíos agrupados).
        $packages = Package::query()
            ->with('group')
            ->where(fn ($q) => $q->where('package_client_id', $client->id)
                ->orWhereIn('package_group_id', $client->groups()->select('id')))
            ->where('status', '!=', Package::STATUS_SIN_ASIGNAR)
            ->orderByDesc('collected_at')
            ->get();

        return view('admin.paqueteria.cliente', [
            'client' => $client,
            'packages' => $packages,
            'pendingUnits' => $client->pendingUnits(),
        ]);
    }

    /** Cobra (total o parcial) un saldo pendiente de un envío ya entregado. */
    public function pay(Request $request, PackageClient $client): RedirectResponse
    {
        $data = $request->validate([
            'unit' => ['required', 'regex:/^(package|group):\d+$/'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        [$type, $id] = explode(':', $data['unit']);
        $unit = $client->pendingUnits()->first(fn ($u) => $u->getKey() === (int) $id && ($u instanceof PackageGroup) === ($type === 'group'));

        if (! $unit) {
            return back()->withErrors(['amount' => 'Ese envío ya no tiene saldo pendiente.']);
        }
        if ((float) $data['amount'] > $unit->pendingAmount() + 0.001) {
            return back()->withErrors(['amount' => 'El pago no puede ser mayor al saldo ($'.number_format($unit->pendingAmount(), 2).').']);
        }

        $unit->registerPayment((float) $data['amount'], $request->user()->id);

        return back()->with('success', 'Pago registrado.');
    }
}
