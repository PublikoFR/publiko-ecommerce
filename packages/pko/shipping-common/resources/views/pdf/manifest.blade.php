<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bordereau récapitulatif du {{ $date->format('d/m/Y') }}</title>
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111; }
        h1 { font-size: 14pt; margin: 0 0 2mm; }
        .date { font-size: 9pt; color: #444; margin-bottom: 6mm; }
        h2 { font-size: 10pt; margin: 6mm 0 2mm; text-transform: uppercase; letter-spacing: .5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: .4pt solid #666; padding: 1.6mm 2mm; text-align: left; }
        th { background: #eee; font-size: 8pt; text-transform: uppercase; }
        td { font-size: 8.5pt; }
        .identity td { border: none; padding: .8mm 0; }
        .identity td.k { width: 32mm; font-weight: bold; text-transform: uppercase; font-size: 8pt; }
        .summary { width: 60mm; }
        .taken { margin: 6mm 0 10mm; font-weight: bold; }
        .sign { width: 100%; margin-top: 4mm; }
        .sign td { height: 24mm; vertical-align: top; font-weight: bold; font-size: 8.5pt; }
    </style>
</head>
<body>
    <h1>Bordereau récapitulatif</h1>
    <div class="date">Date : {{ $date->format('d/m/Y') }}</div>

    <h2>Émetteur</h2>
    <table class="identity">
        <tr><td class="k">Nom</td><td>{{ $shipper['name'] ?: $brandName }}</td></tr>
        <tr><td class="k">Adresse</td><td>{{ $shipper['street'] }}</td></tr>
        <tr><td class="k">Code postal</td><td>{{ $shipper['zip'] }}</td></tr>
        <tr><td class="k">Ville</td><td>{{ $shipper['city'] }}</td></tr>
        <tr><td class="k">Pays</td><td>{{ $shipper['country'] }}</td></tr>
        <tr><td class="k">Téléphone</td><td>{{ $shipper['phone'] }}</td></tr>
    </table>

    <h2>Détail des envois</h2>
    <table>
        <thead>
            <tr>
                <th>N° de LT</th>
                <th>Commande</th>
                <th>N° compte</th>
                <th>Transporteur</th>
                <th>Produit</th>
                <th>CP</th>
                <th>Ville</th>
                <th>Pays</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['tracking_number'] }}</td>
                    <td>{{ $row['order'] }}</td>
                    <td>{{ $row['account'] }}</td>
                    <td>{{ ucfirst($row['carrier']) }}</td>
                    <td>{{ $row['product'] }}</td>
                    <td>{{ $row['zip'] }}</td>
                    <td>{{ $row['city'] }}</td>
                    <td>{{ $row['country'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Résumé</h2>
    <table class="summary">
        <thead>
            <tr><th>Destination</th><th>Unités</th></tr>
        </thead>
        <tbody>
            <tr><td>National</td><td>{{ $national }}</td></tr>
            <tr><td>International</td><td>{{ $international }}</td></tr>
            <tr><td><strong>Total</strong></td><td><strong>{{ $total }}</strong></td></tr>
        </tbody>
    </table>

    <p class="taken">Bien pris en charge {{ $total }} colis.</p>

    <table class="sign">
        <tr>
            <td>Signature de l'expéditeur</td>
            <td>Signature du chauffeur</td>
        </tr>
    </table>
</body>
</html>
