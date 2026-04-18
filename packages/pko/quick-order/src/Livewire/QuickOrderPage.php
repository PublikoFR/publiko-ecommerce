<?php

declare(strict_types=1);

namespace Pko\QuickOrder\Livewire;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Lunar\Facades\CartSession;
use Pko\QuickOrder\Services\SkuResolver;

class QuickOrderPage extends Component
{
    /** @var array<int, array{sku: string, quantity: int}> */
    public array $rows = [];

    public ?string $pasteInput = null;

    public ?string $lastResult = null;

    public function mount(): void
    {
        $this->rows = array_fill(0, 6, ['sku' => '', 'quantity' => 1]);
    }

    public function addRow(): void
    {
        $this->rows[] = ['sku' => '', 'quantity' => 1];
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
        if ($this->rows === []) {
            $this->rows[] = ['sku' => '', 'quantity' => 1];
        }
    }

    public function parsePaste(): void
    {
        $lines = preg_split('/\R/', (string) $this->pasteInput) ?: [];
        $parsed = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/[;,\t]\s*/', $line, 2) ?: [$line];
            $sku = trim($parts[0] ?? '');
            $qty = max(1, (int) ($parts[1] ?? 1));
            if ($sku !== '') {
                $parsed[] = ['sku' => $sku, 'quantity' => $qty];
            }
        }
        if ($parsed !== []) {
            $this->rows = $parsed;
        }
        $this->pasteInput = null;
    }

    public function submit(SkuResolver $resolver): void
    {
        $result = $resolver->resolve($this->rows);

        $manager = CartSession::manager();
        $added = 0;
        foreach ($result['resolved'] as $item) {
            $manager->add($item['variant'], $item['quantity']);
            $added++;
        }

        $errs = count($result['errors']);
        $msg = $added > 0 ? $added.' article'.($added > 1 ? 's ajoutés' : ' ajouté').' au panier.' : '';
        if ($errs > 0) {
            $msg .= ($msg ? ' ' : '').$errs.' référence'.($errs > 1 ? 's introuvables' : ' introuvable').'.';
        }
        $this->lastResult = $msg ?: 'Aucun article valide.';

        if ($added > 0) {
            $this->dispatch('cartUpdated');
        }
    }

    #[Layout('account::layouts.account')]
    public function render(): View
    {
        $resolver = app(SkuResolver::class);
        $resolution = $resolver->resolve($this->rows);

        return view('quick-order::livewire.page', [
            'resolved' => $resolution['resolved'],
            'errors' => $resolution['errors'],
        ]);
    }
}
