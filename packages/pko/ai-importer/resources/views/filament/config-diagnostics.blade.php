{{-- Alerte en tête de l'éditeur de configuration : ce qui ferait échouer une
     préparation ou donnerait des lignes vides. --}}
@include('pko-ai-importer::filament.partials.styles')
<div class="pko-imp">
    @include('pko-ai-importer::filament.partials.findings', ['findings' => $findings])
</div>
