@props(['status', 'compact' => false])

@php
    $steps = ['recolectado', 'en_recorrido', 'entregado'];
    $labels = ['recolectado' => 'Recolectado', 'en_recorrido' => 'En recorrido', 'entregado' => 'Entregado'];
    $colors = [
        'recolectado' => 'bg-[#F5B301] text-[#8C6B00]',
        'en_recorrido' => 'bg-blue-500 text-blue-700',
        'entregado' => 'bg-emerald-500 text-emerald-700',
    ];
    $isFailed = $status === 'no_entregado';
    $currentIndex = array_search($status, $steps, true);
@endphp

@if ($isFailed)
    <div class="flex items-center gap-3 rounded-xl bg-red-50 px-4 py-3">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-red-500 text-white">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
        </span>
        <div>
            <p class="text-sm font-bold text-red-700">No entregado</p>
            <p class="text-xs text-red-600/70">No fue posible entregar el paquete.</p>
        </div>
    </div>
@else
    <div class="flex items-{{ $compact ? 'center' : 'start' }} w-full">
        @foreach ($steps as $i => $step)
            @php
                $reached = $currentIndex !== false && $i <= $currentIndex;
                [$fillClass, $textClass] = explode(' ', $colors[$step]);
                $lineReached = $currentIndex !== false && $i < $currentIndex;
            @endphp
            <div class="flex flex-col items-center {{ $compact ? '' : 'gap-2' }}">
                <span class="{{ $compact ? 'h-2.5 w-2.5' : 'flex h-8 w-8 items-center justify-center' }} shrink-0 rounded-full {{ $reached ? $fillClass : 'bg-black/10' }}">
                    @if (! $compact && $reached)
                        <svg class="h-4 w-4 text-white" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                    @endif
                </span>
                @unless ($compact)
                    <span class="text-[11px] font-bold whitespace-nowrap {{ $reached ? $textClass : 'text-[#2B1113]/30' }}">{{ $labels[$step] }}</span>
                @endunless
            </div>
            @if (! $loop->last)
                <div class="flex-1 h-1 {{ $compact ? '' : 'mt-4' }} mx-2 rounded-full {{ $lineReached ? $fillClass : 'bg-black/10' }}"></div>
            @endif
        @endforeach
    </div>
@endif
