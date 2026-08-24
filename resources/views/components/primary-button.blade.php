<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2.5 bg-[#8C1D2B] border border-transparent rounded-xl font-bold text-sm text-white hover:bg-[#6F1622] focus:bg-[#6F1622] active:bg-[#6F1622] focus:outline-none focus:ring-2 focus:ring-[#8C1D2B] focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
