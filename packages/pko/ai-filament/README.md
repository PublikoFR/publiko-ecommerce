# pko/lunar-ai-filament

Factory `GenerateAiAction` Filament pour boutons "Générer avec l'IA" sur n'importe quel champ.

## Installation

```bash
composer require pko/lunar-ai-filament
php artisan migrate
php artisan vendor:publish --tag=pko-lunar-ai-filament-lang  # (optionnel, pour customiser les traductions)
```

Le ServiceProvider est auto-discovered via `extra.laravel.providers`.

## Dépendances

- `pko/lunar-ai-core` @dev — providers LLM
- `filament/filament` ^3.0

## Licence

Proprietary — Publiko.
