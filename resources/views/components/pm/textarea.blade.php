@props([
    'label',
    'name',
    'value' => null,
    'baris' => 3,
    'wajib' => false,
    'bantu' => null,
])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-zinc-700">
        {{ $label }}@if ($wajib)<span class="text-red-600">*</span>@endif
    </label>

    <textarea name="{{ $name }}"
              id="{{ $name }}"
              rows="{{ $baris }}"
              {{ $attributes->class([
                  'mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:ring-zinc-500',
                  'border-red-400' => $errors->has($name),
                  'border-zinc-300' => ! $errors->has($name),
              ]) }}>{{ old($name, $value) }}</textarea>

    @if ($bantu)
        <p class="mt-1 text-xs text-zinc-500">{{ $bantu }}</p>
    @endif

    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
