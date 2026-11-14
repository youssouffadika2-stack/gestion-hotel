@props(['kind', 'data', 'height' => 'h-64'])

{{-- wire:ignore : Livewire ne doit pas toucher au canvas ; les données arrivent via l'événement dashboard-updated. --}}
<div wire:ignore
     x-data="hpChart('{{ $kind }}', @js($data))"
     @dashboard-updated.window="refresh($event.detail.charts)"
     class="relative {{ $height }}">
    <canvas x-ref="canvas" role="img" aria-label="Graphique {{ $kind }}"></canvas>
</div>
