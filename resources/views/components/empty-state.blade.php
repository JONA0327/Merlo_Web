@props(['title', 'description'])

<div class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-black/10 bg-white px-6 py-20 text-center">
    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#8C1D2B]/10 text-[#8C1D2B]">
        {{ $icon }}
    </span>
    <h2 class="mt-5 font-[Poppins] text-lg font-bold text-[#2B1113]">{{ $title }}</h2>
    <p class="mt-2 max-w-sm text-sm text-[#2B1113]/60">{{ $description }}</p>
    <span class="mt-5 inline-flex items-center gap-1.5 rounded-full bg-[#F5B301]/15 px-3 py-1 text-xs font-bold text-[#8C6B00]">
        Próximamente
    </span>
</div>
