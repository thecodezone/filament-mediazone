<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Livewire\Concerns;

trait HasMediaFilters
{
    public string $search = '';

    public string $filterExt = '';

    public string $filterFolder = '';

    public function updatingSearch(): void
    {
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    public function updatingFilterExt(): void
    {
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    public function updatingFilterFolder(): void
    {
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }
}
