<?php

declare(strict_types=1);

namespace Pko\MailTemplates\Filament\Resources\MailTemplateResource\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;
use Pko\MailTemplates\Filament\Resources\MailTemplateResource;
use Pko\MailTemplates\Support\ContentGuard;

class EditMailTemplate extends EditRecord
{
    protected static string $resource = MailTemplateResource::class;

    /**
     * Contrôle de cohérence avant enregistrement (règle dans ContentGuard).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $error = ContentGuard::check(
            (string) $this->record->key,
            (string) ($data['subject'] ?? ''),
            is_array($data['blocks'] ?? null) ? $data['blocks'] : [],
            (bool) ($data['enabled'] ?? false),
        );

        if ($error !== null) {
            Notification::make()->danger()->title($error)->persistent()->send();

            throw ValidationException::withMessages(['data.blocks' => $error]);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
