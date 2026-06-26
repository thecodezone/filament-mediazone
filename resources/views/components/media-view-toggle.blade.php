@props(['activeView' => null, 'wireMode' => false])
{{--
    Alpine mode (default): reads/writes parent x-data `view` via x-bind/x-on.
    Livewire mode: renders active class server-side, dispatches wire:click.
--}}
<div class="mz-picker__view-toggle" style="display:inline-flex;">
    <button
        type="button"
        title="List view"
        @if ($wireMode)
            wire:click="setViewMode('list')"
            class="{{ $activeView === 'list' ? 'mz-picker__view-btn--active' : '' }}"
        @else
            x-on:click="setView('list')"
            x-bind:class="view === 'list' ? 'mz-picker__view-btn--active' : ''"
        @endif
    >
        <x-filament::icon icon="heroicon-m-bars-3" class="w-4 h-4" />
    </button>
    <button
        type="button"
        title="Grid view"
        @if ($wireMode)
            wire:click="setViewMode('grid')"
            class="{{ $activeView === 'grid' ? 'mz-picker__view-btn--active' : '' }}"
        @else
            x-on:click="setView('grid')"
            x-bind:class="view === 'grid' ? 'mz-picker__view-btn--active' : ''"
        @endif
    >
        <x-filament::icon icon="heroicon-m-squares-2x2" class="w-4 h-4" />
    </button>
</div>
