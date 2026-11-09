<?php

declare(strict_types=1);

namespace Pko\StorefrontCms\Filament\Pages\Settings;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\HtmlString;
use Pko\StorefrontCms\Models\Setting;

/**
 * Réglages d'en-tête et de pied de page du storefront (FEAT-009) : bandeau
 * supérieur, menu principal, bandeau sous le menu, colonnes du footer.
 *
 * Isolé de StorefrontSettings pour garder la page lisible : chargement,
 * schéma et sauvegarde vivent ici, la page ne fait que déléguer.
 */
final class NavigationSettingsSchema
{
    /**
     * URL acceptée dans un lien éditorial : chemin relatif, ancre, http(s),
     * mailto, tel. Miroir de Pko\Storefront\Support\StorefrontLinks::safeHref()
     * (le rendu refait le contrôle : ce regex n'est qu'un retour immédiat en admin).
     */
    public const HREF_REGEX = '#^(/(?!/)|\#|https?://|mailto:|tel:)#i';

    /**
     * @return array<string, mixed>
     */
    public static function load(): array
    {
        $config = config('storefront');

        return [
            'banner_enabled' => (bool) Setting::get('banner.enabled', $config['banner']['enabled'] ?? true),
            'banner_text' => Setting::get('banner.text', $config['banner']['text'] ?? null),
            'banner_icon' => Setting::get('banner.icon', $config['banner']['icon'] ?? 'truck'),
            'topbar_links' => Setting::get('topbar.links', $config['topbar']['links'] ?? []),
            'nav_links' => Setting::get('nav.secondary', $config['nav']['secondary'] ?? []),
            'nav_quote_url' => Setting::get('nav.quote_url', $config['nav']['quote_url'] ?? '/contact'),
            'access_items' => Setting::get('access_band.items', $config['access_band']['items'] ?? []),
            'footer_columns' => Setting::get('footer.columns', $config['footer']['columns'] ?? []),
            'footer_baseline' => Setting::get('footer.baseline', $config['footer']['baseline'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function save(array $data): void
    {
        Setting::set('banner.enabled', (bool) ($data['banner_enabled'] ?? false));
        Setting::set('banner.text', $data['banner_text'] ?? null);
        Setting::set('banner.icon', $data['banner_icon'] ?? 'truck');
        Setting::set('topbar.links', self::links($data['topbar_links'] ?? []));
        Setting::set('nav.secondary', self::links($data['nav_links'] ?? []));
        Setting::set('nav.quote_url', $data['nav_quote_url'] ?? null);
        Setting::set('access_band.items', array_values(array_map(
            fn (array $item): array => ['icon' => $item['icon'] ?? 'check', 'text' => $item['text'] ?? ''],
            $data['access_items'] ?? [],
        )));
        Setting::set('footer.columns', array_values(array_map(
            fn (array $col): array => ['title' => $col['title'] ?? '', 'links' => self::links($col['links'] ?? [])],
            $data['footer_columns'] ?? [],
        )));
        Setting::set('footer.baseline', $data['footer_baseline'] ?? null);
    }

    /**
     * @return array<int, Section>
     */
    public static function sections(): array
    {
        $variablesHelp = new HtmlString(
            'Variables dynamiques disponibles : <code>{{port_franco}}</code> (seuil de livraison offerte, ex. « 500 € HT ») '
            .'et <code>{{port_franco_montant}}</code> (montant seul). Le seuil se règle dans Expédition → Paramètres.'
        );

        return [
            Section::make('Bandeau supérieur (au-dessus du menu)')
                ->description("À gauche : l'accroche de la boutique (Identité) puis l'info livraison. À droite : les liens utiles.")
                ->schema([
                    Toggle::make('banner_enabled')->label("Afficher l'info livraison"),
                    Grid::make(2)->schema([
                        TextInput::make('banner_text')
                            ->label('Info livraison')
                            ->placeholder('Livraison offerte dès {{port_franco}}')
                            ->helperText($variablesHelp),
                        Select::make('banner_icon')->label('Icône')->options(self::iconOptions())->default('truck'),
                    ]),
                    self::linksRepeater('topbar_links', 'Liens utiles (à droite)', 'Ajouter un lien'),
                ])->collapsible(),

            Section::make('Menu principal')
                ->description('« Tous nos produits » est toujours affiché en premier. Un lien sans URL est masqué.')
                ->schema([
                    self::linksRepeater('nav_links', 'Liens du menu', 'Ajouter un lien'),
                    self::hrefInput('nav_quote_url', 'URL du bouton « Demander un devis »')->required(),
                ])->collapsible(),

            Section::make('Bandeau sous le menu')
                ->schema([
                    Repeater::make('access_items')
                        ->label('')
                        ->schema([
                            Select::make('icon')->label('Icône')->options(self::iconOptions())->required(),
                            TextInput::make('text')->label('Texte')->required()->maxLength(120),
                        ])
                        ->helperText($variablesHelp)
                        ->columns(2)
                        ->defaultItems(0)
                        ->reorderableWithButtons()
                        ->addActionLabel('Ajouter un message')
                        ->itemLabel(fn (array $state): ?string => $state['text'] ?? null),
                ])->collapsible(),

            Section::make('Pied de page')
                ->description('Le logo, l\'accroche et le téléphone (Contact) sont repris automatiquement.')
                ->schema([
                    Repeater::make('footer_columns')
                        ->label('Colonnes')
                        ->schema([
                            TextInput::make('title')->label('Titre de la colonne')->required()->maxLength(60),
                            self::linksRepeater('links', 'Liens', 'Ajouter un lien'),
                        ])
                        ->defaultItems(0)
                        ->maxItems(3)
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->addActionLabel('Ajouter une colonne')
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null),
                    TextInput::make('footer_baseline')
                        ->label('Mention du bas de page')
                        ->maxLength(160)
                        ->placeholder('Catalogue ouvert à tous · Achat réservé aux professionnels validés'),
                ])->collapsible(),
        ];
    }

    public static function linksRepeater(string $name, string $label, string $addLabel): Repeater
    {
        return Repeater::make($name)
            ->label($label)
            ->schema([
                TextInput::make('label')->label('Libellé')->required()->maxLength(60),
                self::hrefInput('href', 'URL')->helperText('Ex. /pages/faq, /contact, https://… — vide = lien masqué.'),
            ])
            ->columns(2)
            ->defaultItems(0)
            ->reorderableWithButtons()
            ->addActionLabel($addLabel)
            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null);
    }

    public static function hrefInput(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->maxLength(255)
            ->regex(self::HREF_REGEX)
            ->validationMessages(['regex' => 'URL invalide : commencez par /, #, https://, mailto: ou tel:.']);
    }

    /**
     * @return array<string, string>
     */
    public static function iconOptions(): array
    {
        return [
            'truck' => 'Camion',
            'book-open-check' => 'Livre (accès libre)',
            'lock' => 'Cadenas',
            'check' => 'Check',
            'info' => 'Info',
            'map-pin' => 'Localisation',
            'phone' => 'Téléphone',
            'credit-card' => 'Carte',
            'clock' => 'Horloge',
            'shield-check' => 'Bouclier',
        ];
    }

    /**
     * @param  array<int, mixed>  $links
     * @return list<array{label: string, href: ?string}>
     */
    private static function links(array $links): array
    {
        return array_values(array_map(
            fn (array $link): array => ['label' => (string) ($link['label'] ?? ''), 'href' => ($link['href'] ?? '') !== '' ? $link['href'] : null],
            array_filter($links, 'is_array'),
        ));
    }
}
