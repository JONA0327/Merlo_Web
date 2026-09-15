<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPaymentMethodController extends Controller
{
    public function index(): View
    {
        return view('admin.payment-methods', [
            'paymentMethods' => PaymentMethod::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        PaymentMethod::create($validated + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('admin.payment-methods.index')->with('success', 'Cuenta agregada correctamente.');
    }

    public function update(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $validated = $this->validated($request);

        $paymentMethod->update($validated + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('admin.payment-methods.index')->with('success', 'Cuenta actualizada correctamente.');
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        $paymentMethod->delete();

        return redirect()->route('admin.payment-methods.index')->with('success', 'Cuenta eliminada.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'beneficiary_name' => ['required', 'string', 'max:150'],
            'clabe' => ['nullable', 'string', 'max:18'],
            'card_number' => ['nullable', 'string', 'max:19'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
