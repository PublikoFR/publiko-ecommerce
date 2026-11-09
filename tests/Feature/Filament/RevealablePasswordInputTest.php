<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Livewire\Component;
use Tests\TestCase;

/**
 * Le TextInput::configureUsing() de AppServiceProvider rend revealable tout
 * champ ->password() de l'admin, y compris ceux déclarés dans vendor/.
 */
class RevealablePasswordInputTest extends TestCase
{
    public function test_password_text_input_is_revealable_by_default(): void
    {
        $input = $this->mount(TextInput::make('password')->password());

        $this->assertTrue($input->isPasswordRevealable());
    }

    public function test_plain_text_input_is_not_revealable_and_does_not_throw(): void
    {
        $input = $this->mount(TextInput::make('name'));

        $this->assertFalse($input->isPasswordRevealable());
    }

    public function test_explicit_revealable_false_still_wins(): void
    {
        $input = $this->mount(TextInput::make('password')->password()->revealable(false));

        $this->assertFalse($input->isPasswordRevealable());
    }

    private function mount(TextInput $input): TextInput
    {
        $livewire = new class extends Component implements HasForms
        {
            use InteractsWithForms;

            public function render(): string
            {
                return '<div></div>';
            }
        };

        ComponentContainer::make($livewire)->components([$input])->getComponents();

        return $input;
    }
}
