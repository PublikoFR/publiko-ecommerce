<?php

declare(strict_types=1);

namespace Pko\Pennylane\Filament\Resources\PennylaneInvoiceResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Pko\Pennylane\Filament\Resources\PennylaneInvoiceResource;

class ListPennylaneInvoices extends ListRecords
{
    protected static string $resource = PennylaneInvoiceResource::class;

    /**
     * Le cluster ne contient que cette ressource : sa sous-navigation latérale
     * n'afficherait qu'un lien vers la page courante.
     */
    public function getSubNavigation(): array
    {
        return [];
    }
}
