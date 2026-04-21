<?php

declare(strict_types=1);

namespace Pko\Secrets\Filament\Forms;

use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Get;
use Illuminate\Support\HtmlString;
use Pko\Secrets\Facades\Secrets;

class SecretsFormSchema
{
    /**
     * Build a Filament form section to manage a module's secrets.
     *
     * Exposes inside the $data state path:
     *   - secrets_source (env|db)
     *   - secrets[{key}]   for each declared key
     *
     * @param  array<string, string>  $labels  logical key → human label
     */
    public static function make(string $module, array $labels = [], ?string $heading = null): Section
    {
        $registryKeys = Secrets::registry()->keys($module);

        $secretInputs = [];
        foreach ($registryKeys as $key => $envKey) {
            $secretInputs[] = TextInput::make("secrets.{$key}")
                ->label($labels[$key] ?? $key)
                ->password()
                ->revealable()
                ->autocomplete('off')
                ->disabled(fn (Get $get) => $get('secrets_source') !== 'db')
                ->helperText(new HtmlString(
                    sprintf(
                        'Variable <code>.env</code> : <code>%s</code>. Activez le mode « Base de données » pour saisir la valeur ici.',
                        e($envKey)
                    )
                ));
        }

        return Section::make($heading ?? 'Credentials')
            ->description('Source et valeurs des clés API pour ce module.')
            ->schema([
                ToggleButtons::make('secrets_source')
                    ->label(__('pko-secrets::secrets.source.label'))
                    ->helperText(__('pko-secrets::secrets.source.helper'))
                    ->options([
                        'env' => __('pko-secrets::secrets.source.env'),
                        'db' => __('pko-secrets::secrets.source.db'),
                    ])
                    ->icons([
                        'env' => 'heroicon-o-document-text',
                        'db' => 'heroicon-o-circle-stack',
                    ])
                    ->inline()
                    ->required(),

                Fieldset::make('Valeurs')
                    ->schema($secretInputs === [] ? [Placeholder::make('no_keys')->label(null)->content('Aucune clé déclarée pour ce module.')] : $secretInputs)
                    ->columns(1),
            ]);
    }

    /**
     * Initial data payload to pre-fill a form using this schema.
     *
     * @return array{secrets_source: string, secrets: array<string, string|null>}
     */
    public static function initialData(string $module): array
    {
        $keys = Secrets::registry()->keys($module);
        $source = Secrets::source($module);

        $values = [];
        foreach (array_keys($keys) as $key) {
            // Always show DB-stored values if they exist; env values stay in .env (masked).
            $values[$key] = $source === 'db' ? (Secrets::get($module, $key) ?? '') : '';
        }

        return [
            'secrets_source' => $source,
            'secrets' => $values,
        ];
    }

    /**
     * Persist form state (toggle + values).
     *
     * @param  array{secrets_source?: string, secrets?: array<string, string|null>}  $state
     */
    public static function save(string $module, array $state): void
    {
        $source = ($state['secrets_source'] ?? 'env') === 'db' ? 'db' : 'env';

        if ($source === 'db') {
            Secrets::useDatabase($module);
            foreach (Secrets::registry()->keys($module) as $key => $envKey) {
                $value = $state['secrets'][$key] ?? null;
                if ($value === '' || $value === null) {
                    continue;
                }
                Secrets::set($module, $key, (string) $value);
            }
        } else {
            Secrets::useEnv($module);
        }

        Secrets::flushCache();
    }
}
