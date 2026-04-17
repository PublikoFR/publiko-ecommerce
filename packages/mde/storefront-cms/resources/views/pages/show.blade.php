<x-layout.storefront>
    <article class="py-8 md:py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-ui.breadcrumb :items="[['label' => $page->title]]" class="mb-4" />
            <header class="mb-8">
                <h1 class="text-3xl md:text-4xl font-black text-neutral-900">{{ $page->title }}</h1>
            </header>
            <div class="prose max-w-none prose-neutral">
                {!! $page->body !!}
            </div>
        </div>
    </article>
</x-layout.storefront>
