<x-filament-tiptap-editor::dropdown-button
    label="{{ trans('filament-tiptap-editor::editor.heading.label') }}"
    active="heading"
    icon="heading"
    indicator="editor().isActive('heading', updatedAt) && editor().isFocused ? editor().getAttributes('heading').level : null"
    :list="false"
>
    {{-- H1 réservé au titre de page (SEO) : on ne l'expose pas dans l'éditeur. --}}
    <x-filament-tiptap-editor::button
        action="editor().chain().focus().toggleHeading({level: 2}).run()"
        icon="heading-two"
        :secondary="true"
        label="{{ trans('filament-tiptap-editor::editor.heading.label') }} 2"
    />

    <x-filament-tiptap-editor::button
        action="editor().chain().focus().toggleHeading({level: 3}).run()"
        icon="heading-three"
        :secondary="true"
        label="{{ trans('filament-tiptap-editor::editor.heading.label') }} 3"
    />

    <x-filament-tiptap-editor::button
        action="editor().chain().focus().toggleHeading({level: 4}).run()"
        icon="heading-four"
        :secondary="true"
        label="{{ trans('filament-tiptap-editor::editor.heading.label') }} 4"
    />

</x-filament-tiptap-editor::dropdown-button>
