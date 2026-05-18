<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                {{ __('pko-pennylane::admin.config.status.title') }}
            </h3>

            <dl class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">
                        {{ __('pko-pennylane::admin.config.status.token') }}
                    </dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">
                        @if ($this->hasApiToken())
                            <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-400/10 dark:text-green-400">
                                {{ $this->getMaskedToken($this->getApiToken()) }}
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-400/10 dark:text-red-400">
                                {{ __('pko-pennylane::admin.config.status.missing') }}
                            </span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">
                        {{ __('pko-pennylane::admin.config.status.template') }}
                    </dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">
                        {{ $this->getTemplateId() ?: __('pko-pennylane::admin.config.status.missing') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">
                        {{ __('pko-pennylane::admin.config.status.source') }}
                    </dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">
                        {{ strtoupper($this->getCurrentSource()) }}
                    </dd>
                </div>
            </dl>
        </div>

        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-6 flex items-center gap-3">
                <x-filament::button type="submit">
                    {{ __('pko-pennylane::admin.config.save') }}
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
