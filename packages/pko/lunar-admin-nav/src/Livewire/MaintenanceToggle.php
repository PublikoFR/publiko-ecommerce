<?php

declare(strict_types=1);

namespace Pko\AdminNav\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Pko\StorefrontCms\Models\Setting;

class MaintenanceToggle extends Component
{
    public bool $active = false;

    public function mount(): void
    {
        $this->active = (bool) Setting::get('storefront.maintenance', false);
    }

    public function toggle(): void
    {
        $this->active = ! $this->active;
        Setting::set('storefront.maintenance', $this->active);
    }

    public function render(): View
    {
        return view('admin-nav::livewire.maintenance-toggle');
    }
}
