# pko/lunar-ai-core

AI Core — providers LLM universels (Claude, OpenAI) utilisables par tous les modules du back-office Lunar.

## Installation

```bash
composer require pko/lunar-ai-core
php artisan migrate
php artisan vendor:publish --tag=pko-lunar-ai-core-lang  # (optionnel, pour customiser les traductions)
```

Le ServiceProvider est auto-discovered via `extra.laravel.providers`.

## Dépendances

- `illuminate/support` ^11.0

## Licence

Proprietary — Publiko.
