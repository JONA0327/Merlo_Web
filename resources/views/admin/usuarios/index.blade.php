<x-admin-layout active="usuarios" title="Usuarios">
    <div class="mb-8 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="font-[Poppins] text-2xl font-bold text-[#2B1113]">Usuarios</h2>
            <p class="mt-1 text-sm text-[#2B1113]/60">Clientes que se registraron desde el sitio y cuentas internas de Administración / Paquetería.</p>
        </div>
        <a href="{{ route('admin.usuarios.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-[#8C1D2B] px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#8C1D2B]/20 hover:bg-[#6F1622] transition-colors">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
            Crear cuenta interna
        </a>
    </div>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.usuarios.index') }}" class="mb-4 flex flex-wrap items-end gap-2">
        <label class="block">
            <span class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/60">Buscar</span>
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Nombre o correo" class="mt-1 w-64 rounded-xl border border-black/10 bg-white px-3 py-2 text-sm focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
        </label>
        <label class="block">
            <span class="text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/60">Rol</span>
            <select name="role" class="mt-1 rounded-xl border border-black/10 bg-white px-3 py-2 text-sm focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none">
                <option value="">Todos</option>
                @foreach (['cliente' => 'Cliente', 'superadmin' => 'Superadmin', 'administracion' => 'Administración', 'paqueteria' => 'Paquetería'] as $key => $label)
                    <option value="{{ $key }}" @selected($filters['role'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="rounded-xl bg-[#8C1D2B] px-4 py-2 text-xs font-bold text-white hover:bg-[#6F1622]">Filtrar</button>
        @if ($filters['role'] || $filters['q'])
            <a href="{{ route('admin.usuarios.index') }}" class="rounded-xl bg-white px-4 py-2 text-xs font-bold text-[#2B1113]/60 ring-1 ring-black/10 hover:bg-black/5">Limpiar</a>
        @endif
    </form>

    {{-- Table --}}
    <div class="overflow-x-auto rounded-3xl bg-white ring-1 ring-black/5 shadow-sm">
        <table class="min-w-full divide-y divide-black/5">
            <thead class="bg-[#FFFBF6]">
                <tr>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/50">Nombre</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/50">Correo</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/50">Rol</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/50">Verificado</th>
                    <th class="px-4 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/50">Boletos</th>
                    <th class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-wider text-[#2B1113]/50">Registrado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-black/5">
                @forelse ($users as $user)
                    <tr class="hover:bg-[#FFFBF6]/60">
                        <td class="px-4 py-3 text-sm">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#8C1D2B]/10 text-[11px] font-bold text-[#8C1D2B]">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </span>
                                <span class="font-bold text-[#2B1113]">{{ $user->name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-[#2B1113]/70">
                            {{ $user->email }}
                            @if ($user->phone)
                                <p class="text-xs text-[#2B1113]/40">{{ $user->phone }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @php
                                $roleColors = [
                                    'cliente' => 'bg-blue-50 text-blue-700 ring-blue-200',
                                    'superadmin' => 'bg-[#8C1D2B]/10 text-[#8C1D2B] ring-[#8C1D2B]/20',
                                    'administracion' => 'bg-amber-50 text-amber-700 ring-amber-200',
                                    'paqueteria' => 'bg-purple-50 text-purple-700 ring-purple-200',
                                ];
                                $roleLabels = [
                                    'cliente' => 'Cliente',
                                    'superadmin' => 'Superadmin',
                                    'administracion' => 'Administración',
                                    'paqueteria' => 'Paquetería',
                                ];
                            @endphp
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold ring-1 {{ $roleColors[$user->role] ?? 'bg-zinc-50 text-zinc-700 ring-zinc-200' }}">
                                {{ $roleLabels[$user->role] ?? $user->role }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @if ($user->email_verified_at)
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700 ring-1 ring-emerald-200">Sí</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-zinc-50 px-2.5 py-0.5 text-[11px] font-bold text-zinc-600 ring-1 ring-zinc-200">No</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-bold text-[#2B1113]">
                            {{ $user->seat_reservations_count }}
                        </td>
                        <td class="px-4 py-3 text-xs text-[#2B1113]/60">
                            {{ $user->created_at->format('d/m/Y H:i') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-sm text-[#2B1113]/50">No hay usuarios que coincidan con los filtros.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
</x-admin-layout>
