@props([
    'title' => null,
    'preheader' => null,
])

@php
    // Résolution d'URL absolue pour les clients mail (pas de chemin relatif).
    $appUrl = rtrim((string) config('app.url'), '/');

    $absolute = static function (?string $url) use ($appUrl): ?string {
        if ($url === null || $url === '') {
            return null;
        }

        if (\Illuminate\Support\Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return $appUrl.'/'.ltrim($url, '/');
    };

    $logo = $absolute(brand_logo());
    $brand = brand_name();
    $tagline = brand_tagline();

    $contactEmail = (string) (brand_setting('contact.email') ?: config('storefront.contact.email', ''));
    $contactPhone = (string) (brand_setting('contact.phone') ?: config('storefront.contact.phone', ''));

    $social = [
        'facebook' => brand_setting('social.facebook') ?: config('storefront.social.facebook'),
        'instagram' => brand_setting('social.instagram') ?: config('storefront.social.instagram'),
        'linkedin' => brand_setting('social.linkedin') ?: config('storefront.social.linkedin'),
        'youtube' => brand_setting('social.youtube') ?: config('storefront.social.youtube'),
    ];

    $legalLinks = [
        ['label' => 'Mentions légales', 'href' => $appUrl.'/pages/mentions-legales'],
        ['label' => 'CGV', 'href' => $appUrl.'/pages/cgv'],
        ['label' => 'Données personnelles', 'href' => $appUrl.'/pages/politique-donnees'],
        ['label' => 'Nous contacter', 'href' => $appUrl.'/contact'],
    ];
@endphp
<!DOCTYPE html>
<html lang="fr" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $title ?? $brand }}</title>
</head>
<body style="margin:0;padding:0;background:#f6f8f7;">
    @if ($preheader)
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;height:0;width:0;">{{ $preheader }}</div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f8f7;padding:24px 12px;font-family:Arial,Helvetica,sans-serif;color:#16201d;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #e0e4e2;border-radius:14px;overflow:hidden;">
                    {{-- En-tête / logo --}}
                    <tr>
                        <td align="center" style="background:#00453e;padding:28px 28px 24px;">
                            @if ($logo)
                                <img src="{{ $logo }}" alt="{{ $brand }}" height="44" style="display:block;height:44px;max-height:44px;width:auto;border:0;outline:none;text-decoration:none;">
                            @else
                                <span style="font-size:22px;font-weight:bold;color:#ffffff;letter-spacing:0.5px;">{{ $brand }}</span>
                            @endif
                            @if ($tagline)
                                <p style="margin:10px 0 0;font-size:12px;color:#aac932;letter-spacing:0.3px;">{{ $tagline }}</p>
                            @endif
                        </td>
                    </tr>

                    {{-- Corps --}}
                    <tr>
                        <td style="padding:32px 32px 8px;font-size:15px;line-height:1.65;color:#16201d;">
                            {{ $slot }}
                        </td>
                    </tr>

                    {{-- Pied : contact + réseaux --}}
                    <tr>
                        <td style="padding:20px 32px 8px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #eef1f0;">
                                <tr>
                                    <td style="padding-top:18px;font-size:13px;line-height:1.6;color:#586460;">
                                        @if ($contactPhone)
                                            <span style="display:inline-block;margin-right:14px;">☎ <a href="tel:{{ preg_replace('/\s/', '', $contactPhone) }}" style="color:#00453e;text-decoration:none;">{{ $contactPhone }}</a></span>
                                        @endif
                                        @if ($contactEmail)
                                            <span style="display:inline-block;">✉ <a href="mailto:{{ $contactEmail }}" style="color:#00453e;text-decoration:none;">{{ $contactEmail }}</a></span>
                                        @endif
                                    </td>
                                </tr>
                                @if (array_filter($social))
                                    <tr>
                                        <td style="padding-top:12px;font-size:13px;">
                                            @foreach ($social as $net => $href)
                                                @if (! empty($href))
                                                    <a href="{{ $href }}" target="_blank" rel="noopener" style="color:#00453e;text-decoration:none;margin-right:12px;text-transform:capitalize;">{{ $net }}</a>
                                                @endif
                                            @endforeach
                                        </td>
                                    </tr>
                                @endif
                            </table>
                        </td>
                    </tr>
                </table>

                {{-- Bandeau légal (hors carte) --}}
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
                    <tr>
                        <td align="center" style="padding:18px 24px 4px;font-family:Arial,Helvetica,sans-serif;">
                            @foreach ($legalLinks as $link)
                                <a href="{{ $link['href'] }}" style="color:#76817d;text-decoration:none;font-size:12px;margin:0 8px;white-space:nowrap;">{{ $link['label'] }}</a>@if (! $loop->last)<span style="color:#c3ccc8;">·</span>@endif
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:10px 24px 0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#98a29e;line-height:1.6;">
                            © {{ now()->year }} {{ $brand }}. Tous droits réservés.<br>
                            Cet e-mail vous a été envoyé automatiquement, merci de ne pas y répondre.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
