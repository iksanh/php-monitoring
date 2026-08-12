@props(['status'])

@php
    $warna = match ($status) {
        \App\Enums\StatusBidang::Selesai => 'bg-emerald-100 text-emerald-800',
        \App\Enums\StatusBidang::Diserahkan => 'bg-sky-100 text-sky-800',
        \App\Enums\StatusBidang::Terkendala => 'bg-red-100 text-red-800',
        default => 'bg-amber-100 text-amber-800',
    };
@endphp

<span class="inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium {{ $warna }}">
    {{ $status->label() }}
</span>
