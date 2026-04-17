<x-filament-panels::page>
    <form wire:submit="submit" class="space-y-6">
        {{ $this->form }}

        <div class="flex items-center justify-between gap-3 pt-4 border-t border-gray-200 dark:border-white/10">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Astuce : vous pouvez glisser-déposer plusieurs fichiers d'un coup depuis votre explorateur.
            </p>
            <x-filament::button
                type="submit"
                size="lg"
                icon="heroicon-o-cloud-arrow-up"
                wire:loading.attr="disabled"
                wire:target="submit"
            >
                <span wire:loading.remove wire:target="submit">Uploader dans le dossier</span>
                <span wire:loading wire:target="submit">Upload en cours…</span>
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
