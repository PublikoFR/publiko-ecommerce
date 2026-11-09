<?php

declare(strict_types=1);

namespace Pko\QuoteRequests\Filament\Resources\QuoteRequestResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Pko\QuoteRequests\Enums\QuoteRequestStatus;
use Pko\QuoteRequests\Filament\Resources\QuoteRequestResource;
use Pko\QuoteRequests\Models\QuoteRequest;

/**
 * @property QuoteRequest $record
 */
class ViewQuoteRequest extends ViewRecord
{
    protected static string $resource = QuoteRequestResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Demande '.$this->record->number();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->record->mode->label();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('changeStatus')
                ->label('Changer le statut')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => static::getResource()::canEdit($this->record))
                ->fillForm(fn (): array => [
                    'status' => $this->record->status->value,
                    'admin_notes' => $this->record->admin_notes,
                ])
                ->form([
                    Select::make('status')
                        ->label('Statut')
                        ->options(QuoteRequestStatus::options())
                        ->required(),
                    Textarea::make('admin_notes')->label('Notes internes')->rows(4)->maxLength(5000),
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'status' => QuoteRequestStatus::from((string) $data['status']),
                        'admin_notes' => $data['admin_notes'] ?? null,
                    ]);
                })
                ->successNotificationTitle('Demande mise à jour'),
            Action::make('reply')
                ->label('Répondre par e-mail')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->url(fn (): string => 'mailto:'.$this->record->email.'?subject='.rawurlencode('Votre demande '.$this->record->number())),
        ];
    }
}
