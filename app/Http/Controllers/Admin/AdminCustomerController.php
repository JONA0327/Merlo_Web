<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminCustomerController extends Controller
{
    public function index(): View
    {
        return view('admin.agenda.index', [
            'customers' => Customer::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Customer::create($this->validated($request));

        return redirect()->route('admin.agenda.index')->with('success', 'Cliente agregado a la agenda.');
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->validated($request, $customer->id));

        return redirect()->route('admin.agenda.index')->with('success', 'Cliente actualizado.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return redirect()->route('admin.agenda.index')->with('success', 'Cliente eliminado de la agenda.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $uniqueRule = 'unique:customers,phone';
        if ($ignoreId !== null) {
            $uniqueRule .= ",{$ignoreId}";
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30', $uniqueRule],
            'email' => ['nullable', 'email', 'max:160'],
        ], [], [
            'name' => 'nombre',
            'phone' => 'teléfono',
            'email' => 'correo',
        ]);
    }
}
