@props([
    'optionsRoute' => 'passkey.login-options',
    'submitRoute' => 'passkey.login',
    'label' => 'Masuk dengan passkey',
    'labelProses' => 'Memeriksa passkey...',
    'pemisah' => 'atau',
    'tujuan' => null,
])

@php
    // Skrip di bawah mengikat satu instance saja, jadi tiap pemakaian butuh id
    // sendiri kalau kelak ada dua tombol passkey dalam satu halaman.
    $id = 'passkey-'.\Illuminate\Support\Str::random(6);
@endphp

{{-- Layout auth ini tanpa Livewire/Alpine, jadi tombol passkey ditulis dengan
     JS biasa. Disembunyikan sampai peramban terbukti mendukung passkey. --}}
<div id="{{ $id }}" hidden>
    <div class="relative my-5">
        <div class="absolute inset-0 flex items-center" aria-hidden="true">
            <div class="w-full border-t border-zinc-200"></div>
        </div>
        <div class="relative flex justify-center">
            <span class="bg-white px-2 text-xs uppercase tracking-wide text-zinc-500">{{ $pemisah }}</span>
        </div>
    </div>

    <button type="button"
            data-passkey-tombol
            class="flex w-full items-center justify-center gap-2 rounded-md border border-zinc-300 px-4 py-2.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 disabled:opacity-60">
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7.864 4.243A7.5 7.5 0 0 1 19.5 10.5c0 2.92-.556 5.709-1.568 8.268M5.742 6.364A7.465 7.465 0 0 0 4.5 10.5a7.464 7.464 0 0 1-1.15 3.993m1.989 3.559A11.209 11.209 0 0 0 8.25 10.5a3.75 3.75 0 1 1 7.5 0c0 .527-.021 1.049-.064 1.565M12 10.5a14.94 14.94 0 0 1-3.6 9.75m6.633-4.596a18.666 18.666 0 0 1-2.485 5.33" />
        </svg>
        <span data-passkey-label>{{ $label }}</span>
    </button>

    <p data-passkey-galat hidden class="mt-2 text-center text-xs text-red-600"></p>
</div>

@once
    @push('kepala')
        @vite('resources/js/passkeys.js')
    @endpush
@endonce

@push('skrip')
    <script>
        (function () {
            var wadah = document.getElementById('{{ $id }}');
            var tombol = wadah.querySelector('[data-passkey-tombol]');
            var label = wadah.querySelector('[data-passkey-label]');
            var galat = wadah.querySelector('[data-passkey-galat]');

            function periksaDukungan() {
                if (window.Passkeys && window.Passkeys.isSupported()) {
                    wadah.hidden = false;
                }
            }

            // passkeys.js dimuat sebagai type=module, jadi bisa jadi belum
            // jalan saat skrip ini dieksekusi: periksa dulu, lalu tunggu.
            periksaDukungan();
            window.addEventListener('passkeys:ready', periksaDukungan, { once: true });

            tombol.addEventListener('click', function () {
                tombol.disabled = true;
                label.textContent = @js($labelProses);
                galat.hidden = true;

                window.Passkeys.verify({
                    routes: {
                        options: '{{ route($optionsRoute) }}',
                        submit: '{{ route($submitRoute) }}',
                    },
                }).then(function (respons) {
                    window.location.href = respons.redirect || @js($tujuan ?? route('dashboard'));
                }).catch(function (e) {
                    if (e.constructor && e.constructor.name !== 'UserCancelledError') {
                        galat.textContent = e.message;
                        galat.hidden = false;
                    }

                    tombol.disabled = false;
                    label.textContent = @js($label);
                });
            });
        })();
    </script>
@endpush
