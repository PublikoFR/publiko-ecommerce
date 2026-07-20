@php
    $meta = $customer->meta ?? [];
    $groups = $customer->customerGroups()->pluck('name')->implode(', ');
    $address = array_filter([$customer->pko_street, trim(($customer->pko_postcode ?? '').' '.($customer->pko_city ?? '')), $customer->pko_country]);
    $rows = array_filter([
        'Société' => $customer->company_name,
        'Contact' => trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')),
        'E-mail' => $user->email,
        'Téléphone' => $meta['phone'] ?? null,
        'SIRET' => $meta['siret'] ?? null,
        'N° TVA' => $customer->tax_identifier,
        'Code NAF' => $customer->naf_code,
        'Activité' => $meta['activity'] ?? null,
        'Adresse' => $address ? implode(', ', $address) : null,
        'Groupe(s)' => $groups ?: null,
        'Statut' => $customer->pko_status,
        'SIRET INSEE' => $customer->sirene_status,
    ], fn ($v) => $v !== null && $v !== '');
@endphp
<x-storefront-cms::mail.layout
    title="Nouvelle inscription client"
    preheader="Un nouveau client vient de s'inscrire sur {{ brand_name() }}.">

    <h1 style="margin:0 0 16px;font-size:20px;color:#00453e;">Nouvelle inscription client</h1>

    <p style="margin:0 0 16px;">Un nouveau client vient de créer un compte sur {{ brand_name() }}. Voici ses coordonnées :</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.5;border-collapse:collapse;">
        @foreach ($rows as $label => $value)
            <tr>
                <td style="padding:8px 12px;color:#586460;width:140px;background:#f6f8f7;border:1px solid #e0e4e2;vertical-align:top;">{{ $label }}</td>
                <td style="padding:8px 12px;border:1px solid #e0e4e2;">
                    @if ($label === 'E-mail')
                        <a href="mailto:{{ $value }}" style="color:#00453e;">{{ $value }}</a>
                    @else
                        {{ $value }}
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
</x-storefront-cms::mail.layout>
