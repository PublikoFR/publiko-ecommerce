{{-- Bloc documents téléchargeables — affiché uniquement si $documents est non vide --}}
@props(['documents'])

@if ($documents->isNotEmpty())
    <section class="mt-10 border-t border-neutral-200 pt-8">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-lg font-bold text-neutral-900 mb-6">Documents téléchargeables</h2>

            <div class="space-y-6">
                @foreach ($documents as $categoryLabel => $docs)
                    <div>
                        <h3 class="text-sm font-semibold text-neutral-500 uppercase tracking-wider mb-3">
                            {{ $categoryLabel }}
                        </h3>

                        <ul class="divide-y divide-neutral-100 rounded-lg border border-neutral-200 bg-white overflow-hidden">
                            @foreach ($docs as $doc)
                                @if ($doc->media)
                                    <li>
                                        <a
                                            href="{{ $doc->media->getUrl() }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="flex items-center gap-3 px-4 py-3 text-sm text-neutral-700 hover:bg-neutral-50 transition-colors"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 shrink-0 text-primary-600">
                                                <path fill-rule="evenodd" d="M4 4a2 2 0 0 1 2-2h4.586A2 2 0 0 1 12 2.586L15.414 6A2 2 0 0 1 16 7.414V16a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4Zm2 6a1 1 0 0 1 1-1h6a1 1 0 1 1 0 2H7a1 1 0 0 1-1-1Zm1 3a1 1 0 1 0 0 2h6a1 1 0 1 0 0-2H7Z" clip-rule="evenodd"/>
                                            </svg>

                                            <span class="flex-1 font-medium">
                                                {{ $doc->media->name ?: $doc->media->file_name }}
                                            </span>

                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 shrink-0 text-neutral-400">
                                                <path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z"/>
                                                <path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z"/>
                                            </svg>
                                        </a>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
