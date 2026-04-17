<?php

declare(strict_types=1);

namespace Mde\Account\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mde\Account\Support\AccountContext;

class CompanyPage extends Component
{
    #[Layout('account::layouts.account')]
    public function render(): View
    {
        return view('account::livewire.company-page', [
            'customer' => AccountContext::customer(),
        ]);
    }
}
