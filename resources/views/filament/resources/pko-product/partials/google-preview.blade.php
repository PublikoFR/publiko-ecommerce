@php
    $displayTitle = $this->seoTitle !== '' ? $this->seoTitle : $this->productName;
    $displayDesc  = $this->seoDesc !== '' ? $this->seoDesc : $this->shortDesc;
    $slugPath     = $this->productSlug !== '' ? $this->productSlug : 'slug-produit';
    $host         = parse_url(url('/'), PHP_URL_HOST) ?? 'mon-site.com';
@endphp

<div class="rounded-md border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-gray-950/40 p-4">
    <div class="text-[11px] tracking-wide uppercase text-gray-500 mb-2">Aperçu Google</div>
    <div class="text-[12px] text-gray-700 dark:text-gray-300" style="font-family: Arial, sans-serif;">
        {{ $host }} › produits › {{ $slugPath }}
    </div>
    <div class="text-[18px] leading-snug mt-1" style="color:#1a0dab;">
        {{ $displayTitle !== '' ? $displayTitle : 'Titre du produit' }}
    </div>
    <div class="text-[13px] leading-[1.5] mt-1" style="color:#4d5156;">
        {{ \Illuminate\Support\Str::limit($displayDesc, 160) }}
    </div>
</div>
