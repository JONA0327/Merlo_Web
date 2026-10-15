{{-- Confirmación para quien registró un paquete desde el QR sin iniciar sesión. --}}
<x-guest-layout>
    <div class="text-center">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
            <svg class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 5.296a1 1 0 010 1.414l-7.5 7.5a1 1 0 01-1.414 0l-3.5-3.5a1 1 0 111.414-1.414L8.5 12.086l6.793-6.79a1 1 0 011.411 0z" clip-rule="evenodd"/></svg>
        </div>
        <h1 class="mt-4 font-[Poppins] text-xl font-bold text-[#2B1113]">¡Paquete registrado!</h1>
        <p class="mt-2 text-sm text-[#2B1113]/70">{{ $message }}</p>
        <a href="{{ route('admin.paqueteria.registrar.create') }}" class="mt-6 inline-block text-sm font-semibold text-[#8C1D2B] hover:underline">Registrar otro paquete</a>
    </div>
</x-guest-layout>
