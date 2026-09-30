@props([
    'name' => 'departure_time',
    'value' => null,
])

@php
    $parts = collect(preg_split('/[: ]/', (string) $value))
        ->filter(fn ($part) => $part !== '')
        ->values();

    $selectedHour = old("{$name}_hour", str_pad($parts->get(0, '00'), 2, '0', STR_PAD_LEFT));
    $selectedMinute = old("{$name}_minute", str_pad($parts->get(1, '00'), 2, '0', STR_PAD_LEFT));
    $minutes = range(0, 55, 5);
    if (! in_array((int) $selectedMinute, $minutes, true)) {
        $minutes[] = (int) $selectedMinute;
        sort($minutes);
    }
    $selectClass = 'w-full rounded-xl border border-black/10 bg-[#FFFBF6] px-4 py-3 text-sm text-[#2B1113] focus:border-[#8C1D2B] focus:ring-2 focus:ring-[#8C1D2B]/20 outline-none';
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    <select id="{{ $name }}_hour" name="{{ $name }}_hour" aria-label="Hora" class="{{ $selectClass }}">
        @for ($hour = 0; $hour < 24; $hour++)
            @php $option = str_pad($hour, 2, '0', STR_PAD_LEFT) @endphp
            <option value="{{ $option }}" @selected($selectedHour === $option)>{{ $option }}</option>
        @endfor
    </select>

    <span class="text-sm font-semibold text-[#2B1113]/50">:</span>

    <select id="{{ $name }}_minute" name="{{ $name }}_minute" aria-label="Minuto" class="{{ $selectClass }}">
        @foreach ($minutes as $minute)
            @php $option = str_pad($minute, 2, '0', STR_PAD_LEFT) @endphp
            <option value="{{ $option }}" @selected($selectedMinute === $option)>{{ $option }}</option>
        @endforeach
    </select>
</div>
