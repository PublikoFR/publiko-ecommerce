<x-layout.storefront>
    <section class="py-8 md:py-12">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8">
            <x-ui.breadcrumb :items="[['label' => $brand->name]]" class="mb-4" />

            <header class="mb-8">
                <h1 class="text-3xl md:text-4xl font-black text-neutral-900 leading-tight">
                    {{ $brand->name }}
                </h1>
            </header>

            <x-page-builder::render :content="$brandPage->content" />
        </div>
    </section>
</x-layout.storefront>
