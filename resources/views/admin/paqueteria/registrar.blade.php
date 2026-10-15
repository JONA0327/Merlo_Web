<x-admin-layout active="paqueteria" title="Registrar paquete · Paquetería">
    <div class="mb-6">
        <a href="{{ route('admin.paqueteria') }}" class="text-sm font-semibold text-[#8C1D2B] hover:underline">&larr; Volver a Paquetería</a>
        <h2 class="mt-2 font-[Poppins] text-2xl font-bold text-[#2B1113]">Registrar paquete</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">Captura los datos del cliente — se le asigna un folio por cada paquete (contiguo, ej. del F-000101 al F-000105) y se le avisa por WhatsApp.</p>
    </div>

    @include('admin.paqueteria._registrar-form', ['isStaff' => true])
</x-admin-layout>
