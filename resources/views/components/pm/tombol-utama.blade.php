@props(['type' => 'submit'])

{{-- Tombol aksi utama halaman auth. Warnanya sengaja sama dengan pil menu
     aktif di layouts/pemantauan. --}}
<button type="{{ $type }}"
        {{ $attributes->class('w-full rounded-md bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-500 focus:ring-offset-2') }}>
    {{ $slot }}
</button>
