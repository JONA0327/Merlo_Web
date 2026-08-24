<x-admin-layout active="paqueteria" title="Lotes generados · Paquetería">
    <div class="mb-6">
        <a href="{{ route('admin.paqueteria.qr.create') }}" class="text-sm font-semibold text-[#8C1D2B] hover:underline">&larr; Volver a Generar QR</a>
        <h2 class="mt-2 font-[Poppins] text-2xl font-bold text-[#2B1113]">Lotes generados</h2>
        <p class="mt-1 text-sm text-[#2B1113]/60">Cada vez que generas un lote de QR, se guarda automáticamente el PDF aquí, organizado por fecha.</p>
    </div>

    @if ($batchesByDate->isEmpty())
        <x-empty-state
            title="Aún no hay lotes guardados"
            description="Cuando generes un lote de códigos QR, aparecerá aquí listo para descargar."
        >
            <x-slot name="icon">
                <svg class="h-7 w-7" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
            </x-slot>
        </x-empty-state>
    @else
        <div class="space-y-8">
            @foreach ($batchesByDate as $date => $batches)
                <div>
                    <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-[#2B1113]/40">{{ $date }}</h3>
                    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-black/5">
                        <table class="min-w-full divide-y divide-black/5 text-sm">
                            <tbody class="divide-y divide-black/5">
                                @foreach ($batches as $batch)
                                    <tr>
                                        <td class="px-5 py-3 text-[#2B1113]/60">{{ $batch->created_at->format('H:i') }}</td>
                                        <td class="px-5 py-3 font-semibold text-[#2B1113]">{{ $batch->qty }} código{{ $batch->qty === 1 ? '' : 's' }}</td>
                                        <td class="px-5 py-3 text-[#2B1113]/60">{{ $batch->generatedBy?->name ?? '—' }}</td>
                                        <td class="px-5 py-3 text-right">
                                            <a href="{{ route('admin.paqueteria.qr.batches.download', $batch) }}" class="text-xs font-bold text-[#8C1D2B] hover:underline">Descargar PDF</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-admin-layout>
