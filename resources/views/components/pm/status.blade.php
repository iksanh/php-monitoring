@props(['status' => null])

{{-- Pesan status dari session (mis. "tautan setel ulang sudah dikirim").
     Warnanya mengikuti kotak sukses di partials/pesan. --}}
@if (filled($status))
    <div {{ $attributes->class('mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800') }}>
        {{ $status }}
    </div>
@endif
