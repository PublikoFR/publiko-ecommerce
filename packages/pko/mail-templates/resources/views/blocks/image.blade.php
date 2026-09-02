@php
    $src = null;
    if (! empty($block['media_id']) && class_exists(\Spatie\MediaLibrary\MediaCollections\Models\Media::class)) {
        $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::query()->find($block['media_id']);
        $src = $media?->getFullUrl();
    }
    $src = $src ?? ($block['url'] ?? null);
    $alt = $block['alt'] ?? '';
@endphp
@if ($src)
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 14px;">
        <tr>
            <td align="center">
                <img src="{{ $src }}" alt="{{ $alt }}" style="display:block;max-width:100%;height:auto;border:0;outline:none;text-decoration:none;">
            </td>
        </tr>
    </table>
@endif
