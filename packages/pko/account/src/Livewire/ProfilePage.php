<?php

declare(strict_types=1);

namespace Pko\Account\Livewire;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Pko\Account\Support\AccountContext;

class ProfilePage extends Component
{
    public string $name = '';

    public string $email = '';

    public ?string $currentPassword = null;

    public ?string $newPassword = null;

    public ?string $newPasswordConfirmation = null;

    public ?string $saved = null;

    public function mount(): void
    {
        $user = AccountContext::user();
        $this->name = (string) ($user?->name ?? '');
        $this->email = (string) ($user?->email ?? '');
    }

    public function save(): void
    {
        $user = AccountContext::user();
        abort_unless($user, 403);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->forceFill($validated)->save();

        $this->saved = 'Profil mis à jour.';
    }

    public function changePassword(): void
    {
        $user = AccountContext::user();
        abort_unless($user, 403);

        $this->validate([
            'currentPassword' => ['required'],
            'newPassword' => ['required', 'string', 'min:8', 'confirmed:newPasswordConfirmation'],
        ]);

        if (! Hash::check((string) $this->currentPassword, (string) $user->password)) {
            throw ValidationException::withMessages(['currentPassword' => 'Mot de passe actuel incorrect.']);
        }

        $user->forceFill(['password' => Hash::make((string) $this->newPassword)])->save();

        $this->reset(['currentPassword', 'newPassword', 'newPasswordConfirmation']);
        $this->saved = 'Mot de passe modifié.';
    }

    #[Layout('account::layouts.account')]
    public function render(): View
    {
        return view('account::livewire.profile-page');
    }
}
