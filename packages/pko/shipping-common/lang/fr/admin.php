<?php

declare(strict_types=1);

return [
    'product' => [
        'logistics_class' => 'Classe logistique',
        'logistics_class_none' => 'Non définie',
        'logistics_class_a' => 'Standard',
        'logistics_class_b' => 'Fournisseur (surcoût transport)',
        'logistics_class_c' => 'Volumineux / spécifique',
        'franco_eligible' => 'Éligible au franco',
        'franco_eligible_help' => 'Décocher pour les produits volumineux, longs, fragiles, palettes, menuiseries, hors normes…',
        'transport_price' => 'Prix transport dédié (centimes €)',
        'transport_price_help' => 'Frais de transport spécifiques à ce produit (classe C).',
        'quote_only' => 'Sur devis',
        'quote_only_help' => 'Commande créée en attente de devis transport, sans paiement immédiat.',
        'supplier' => 'Fournisseur',
        'supplier_none' => 'Aucun',
    ],

    'supplier' => [
        'nav' => 'Fournisseurs',
        'label' => 'Fournisseur',
        'plural_label' => 'Fournisseurs',
        'name' => 'Nom',
        'bl_neutre' => 'BL neutre',
        'bl_neutre_help' => 'BL neutre / sans prix → livraison directe fournisseur → client.',
        'lead_time_min' => 'Délai min (jours)',
        'lead_time_max' => 'Délai max (jours)',
        'notes' => 'Notes',
    ],

    'surcharge' => [
        'nav' => 'Suppléments transport',
        'label' => 'Supplément',
        'plural_label' => 'Suppléments transport',
        'code' => 'Code',
        'label_field' => 'Libellé',
        'amount_cents' => 'Montant (€)',
        'mode' => 'Mode',
        'mode_auto' => 'Automatique',
        'mode_quote' => 'Sur devis',
        'mode_rebill' => 'Refacturé',
        'mode_auto_help' => 'Appliqué si la règle est connue au checkout.',
        'mode_quote_help' => 'Transport sur devis — montant non fixé.',
        'mode_rebill_help' => 'Refacturé au client après coup.',
        'rule' => 'Règle (JSON)',
        'enabled' => 'Actif',
    ],
];
