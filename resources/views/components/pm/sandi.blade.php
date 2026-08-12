@props([
    'label' => 'Kata sandi',
    'name' => 'password',
    'bantu' => null,
    'aksi' => null,
])

{{-- Input kata sandi dengan tombol lihat/sembunyikan. Dipakai seluruh halaman
     auth supaya gaya isiannya sama dengan x-pm.input di form lain. --}}
<div>
    <div class="flex items-baseline justify-between gap-3">
        <label for="{{ $name }}" class="block text-sm font-medium text-zinc-700">{{ $label }}</label>
        {{ $aksi }}
    </div>

    {{-- Tombol ditaruh di dalam kotak input supaya tetap terjangkau ibu jari
         di ponsel. --}}
    <div class="relative mt-1">
        <input type="password"
               name="{{ $name }}"
               id="{{ $name }}"
               {{ $attributes->class([
                   'block w-full rounded-md border py-2 pe-24 ps-3 text-sm shadow-sm focus:border-zinc-500 focus:ring-zinc-500',
                   'border-red-400' => $errors->has($name),
                   'border-zinc-300' => ! $errors->has($name),
               ]) }}>

        <button type="button"
                data-lihat-sandi
                aria-controls="{{ $name }}"
                class="absolute inset-y-0 end-0 px-3 text-xs font-medium text-zinc-500 hover:text-zinc-900">
            Lihat
        </button>
    </div>

    @if ($bantu)
        <p class="mt-1 text-xs text-zinc-500">{{ $bantu }}</p>
    @endif

    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

@once
    @push('skrip')
        <script>
            // Satu skrip untuk semua isian kata sandi di halaman.
            (function () {
                document.querySelectorAll('[data-lihat-sandi]').forEach(function (tombol) {
                    var isian = document.getElementById(tombol.getAttribute('aria-controls'));

                    if (! isian) {
                        return;
                    }

                    tombol.addEventListener('click', function () {
                        var tersembunyi = isian.type === 'password';

                        isian.type = tersembunyi ? 'text' : 'password';
                        tombol.textContent = tersembunyi ? 'Sembunyikan' : 'Lihat';
                    });
                });
            })();
        </script>
    @endpush
@endonce
