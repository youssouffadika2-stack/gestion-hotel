@props(['label', 'name', 'hint' => null])

<div {{ $attributes->only('class') }}>
    <label class="mb-1 block text-xs font-semibold text-slate-600">{{ $label }}</label>
    {{ $slot }}
    @if ($hint)
        <p class="mt-1 text-xs text-slate-400">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>
    @enderror
</div>
