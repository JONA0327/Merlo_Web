@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-black/10 bg-[#FFFBF6] focus:border-[#8C1D2B] focus:ring-[#8C1D2B] rounded-xl shadow-sm']) }}>
