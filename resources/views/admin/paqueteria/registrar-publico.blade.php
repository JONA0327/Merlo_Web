{{-- Formulario que abre el QR universal para quien lo escanea (sin cuenta ni login). --}}
<x-guest-layout>
    <h1 class="font-[Poppins] text-xl font-bold text-[#2B1113]">Registra tu paquete</h1>
    <p class="mt-1 mb-5 text-sm text-[#2B1113]/60">Llena tus datos y sube una foto: te asignamos un folio por cada paquete y te lo mandamos por WhatsApp.</p>

    @include('admin.paqueteria._registrar-form', ['isStaff' => false, 'clients' => collect()])
</x-guest-layout>
