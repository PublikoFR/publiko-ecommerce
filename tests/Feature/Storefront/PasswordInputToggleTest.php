<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PasswordInputToggleTest extends TestCase
{
    public function test_password_input_renders_reveal_toggle(): void
    {
        $html = Blade::render('<x-ui.input type="password" name="password" label="Mot de passe" />');

        $this->assertStringContainsString('data-password-toggle', $html);
        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringContainsString('aria-label="Afficher le mot de passe"', $html);
        $this->assertStringContainsString('aria-pressed="false"', $html);
        $this->assertStringContainsString('x-data="{ revealed: false }"', $html);
        // Type initial rendu côté serveur : le champ reste masqué sans JS.
        $this->assertMatchesRegularExpression('/<input[^>]*type="password"/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*x-bind:type="revealed \? &#039;text&#039; : &#039;password&#039;"/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*class="[^"]*\bpr-10\b/', $html);
    }

    public function test_text_input_has_no_reveal_toggle(): void
    {
        $html = Blade::render('<x-ui.input type="text" name="email" label="E-mail" />');

        $this->assertStringNotContainsString('data-password-toggle', $html);
        $this->assertStringNotContainsString('revealed', $html);
    }

    public function test_password_input_with_trailing_slot_keeps_both_side_by_side(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.input type="password" name="password">
                <x-slot:trailing><span data-trailing>OK</span></x-slot:trailing>
            </x-ui.input>
            BLADE);

        $this->assertStringContainsString('data-trailing', $html);
        $this->assertStringContainsString('data-password-toggle', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*class="[^"]*\bpr-20\b/', $html);
    }
}
