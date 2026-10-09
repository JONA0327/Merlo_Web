<x-admin-layout active="agenda" title="Agenda de clientes">
    <div class="mb-6">
        <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Agenda de clientes</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">Nombre y teléfono guardados para reusar en futuras asignaciones de asientos sin volver a escribirlos. El correo es opcional. Se agregan solos cada vez que apartas un asiento, o aquí manualmente.</p>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>
    @endif

    <div class="mb-6 rounded-3xl bg-white p-6 ring-1 ring-black/5 shadow-sm">
        <h3 class="font-[Poppins] text-base font-bold text-[#2B1113]">Agregar cliente</h3>
        <form method="POST" action="{{ route('admin.agenda.store') }}" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-4">
            @csrf
            <div>
                <x-input-label for="name" value="Nombre" />
                <x-text-input id="name" name="name" type="text" required class="block mt-1.5 w-full" value="{{ old('name') }}" />
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="phone" value="Teléfono" />
                <x-text-input id="phone" name="phone" type="text" required class="block mt-1.5 w-full" value="{{ old('phone') }}" />
                <x-input-error :messages="$errors->get('phone')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="email" value="Correo (opcional)" />
                <x-text-input id="email" name="email" type="email" class="block mt-1.5 w-full" value="{{ old('email') }}" />
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
            </div>
            <div class="flex items-end">
                <x-primary-button type="submit" class="w-full justify-center">Agregar</x-primary-button>
            </div>
        </form>
    </div>

    <div class="rounded-3xl bg-white ring-1 ring-black/5 shadow-sm overflow-hidden">
        @if ($customers->isEmpty())
            <div class="p-10 text-center">
                <p class="text-sm text-[#2B1113]/60">Aún no hay clientes en la agenda.</p>
            </div>
        @else
            <table class="min-w-full divide-y divide-black/5 text-sm">
                <thead class="bg-[#FFFBF6] text-left text-xs font-bold uppercase tracking-wider text-[#2B1113]/60">
                    <tr>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Teléfono</th>
                        <th class="px-4 py-3">Correo</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5 bg-white">
                    @foreach ($customers as $c)
                        <tr class="hover:bg-[#FFFBF6]/60 transition-colors">
                            <td class="px-4 py-3 font-[Poppins] font-bold text-[#2B1113]">{{ $c->name }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $c->phone }}</td>
                            <td class="px-4 py-3 text-xs text-[#2B1113]/70">{{ $c->email ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button" class="agenda-edit-toggle rounded-lg bg-[#FFFBF6] px-2.5 py-1 text-[11px] font-bold text-[#8C1D2B] ring-1 ring-black/10 hover:bg-[#8C1D2B]/5" data-target="agenda-edit-{{ $c->id }}">Editar</button>
                                    <form method="POST" action="{{ route('admin.agenda.destroy', $c) }}" data-confirm-delete="Vas a BORRAR este cliente de la agenda.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg bg-red-50 px-2.5 py-1 text-[11px] font-bold text-red-700 ring-1 ring-red-200 hover:bg-red-100">Borrar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <tr id="agenda-edit-{{ $c->id }}" class="hidden">
                            <td colspan="4" class="bg-[#FFFBF6] px-4 py-3">
                                <form method="POST" action="{{ route('admin.agenda.update', $c) }}" class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                                    @csrf
                                    @method('PUT')
                                    <x-text-input name="name" type="text" required class="block w-full" value="{{ $c->name }}" />
                                    <x-text-input name="phone" type="text" required class="block w-full" value="{{ $c->phone }}" />
                                    <x-text-input name="email" type="email" class="block w-full" value="{{ $c->email }}" />
                                    <x-primary-button type="submit" class="justify-center">Guardar</x-primary-button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <script>
        document.querySelectorAll('.agenda-edit-toggle').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.getElementById(btn.getAttribute('data-target'))?.classList.toggle('hidden');
            });
        });
    </script>
</x-admin-layout>
