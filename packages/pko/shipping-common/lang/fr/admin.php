<?php

declare(strict_types=1);

return [
    'product' => [
        'port_mode' => 'Facturation du port',
        'port_mode_inherit' => 'Fournisseur',
        'port_mode_standard' => 'Standard',
        'port_mode_flat' => 'Forfait',
        'port_mode_free' => 'Offert',
        'port_mode_quote' => 'Devis',
        'port_mode_inherit_desc' => 'Port selon la politique du fournisseur.',
        'port_mode_standard_desc' => 'Tarif transporteur habituel selon poids et dimensions.',
        'port_mode_flat_desc' => 'Prix forfaitaire fixe — saisir le montant ci-dessous.',
        'port_mode_free_desc' => 'Port inclus dans le prix d\'achat (dropshipping) — exclu du calcul de livraison.',
        'port_mode_quote_desc' => 'Commande en attente de devis transport, sans paiement immédiat.',
        'franco_eligible' => 'Éligible au franco',
        'franco_eligible_help' => 'Décocher pour les produits volumineux, longs, fragiles, palettes, menuiseries, hors normes…',
        'franco_forced_manually' => 'Forcé manuellement (diffère de la valeur dérivée du mode)',
        'transport_price' => 'Prix transport forfaitaire',
        'transport_price_help' => 'Frais de transport fixes appliqués à ce produit (mode forfait).',
        'supplier' => 'Fournisseur',
        'supplier_none' => 'Aucun',
        'filter_port_a_trancher' => 'Port à trancher',
        'filter_port_a_trancher_label' => 'Produits en héritage fournisseur (cas par cas)',
    ],

    'supplier' => [
        'nav' => 'Fournisseurs',
        'label' => 'Fournisseur',
        'plural_label' => 'Fournisseurs',
        'name' => 'Nom',
        'bl_neutre' => 'Bon de livraison neutre',
        'bl_neutre_help' => 'Bon de livraison neutre / sans prix → livraison directe fournisseur → client.',
        'lead_time_min' => 'Délai min (jours)',
        'lead_time_max' => 'Délai max (jours)',
        'notes' => 'Notes',
        'port_inclus' => 'Port inclus',
        'port_inclus_help' => 'Indique si ce fournisseur facture ou non les frais de port sur ses expéditions.',
        'port_inclus_oui' => 'Oui — port inclus',
        'port_inclus_non' => 'Non — port facturé',
        'port_inclus_cas_par_cas' => 'Cas par cas',
    ],

    'settings' => [
        'nav' => 'Paramètres',
        'title' => 'Paramètres d\'expédition',

        'section_franco' => 'Franco de port',
        'section_tax' => 'Taxe et affichage des prix',

        'threshold_eur' => 'Seuil franco (€ HT)',
        'threshold_eur_help' => 'Montant minimum du panier HT pour déclencher la livraison offerte.',

        'services' => 'Services couverts par le franco',
        'services_help' => 'Codes nus des services (ex : chrono13). Appuyez sur Entrée après chaque code.',

        'basis' => 'Base de calcul',
        'basis_help' => 'Définit quelles lignes entrent dans le total comparé au seuil.',
        'basis_eligible_only' => 'Lignes éligibles uniquement',
        'basis_cart_total' => 'Panier complet',

        'tax_price_base' => 'Base de taxe des prix grille',
        'tax_price_base_help' => 'Indique si les montants saisis dans la grille tarifaire transporteur sont HT ou TTC.',
        'tax_price_base_ht' => 'HT — prix nets (défaut)',
        'tax_price_base_ttc' => 'TTC — prix toutes taxes comprises',

        'tax_display' => 'Affichage des prix au checkout',
        'tax_display_help' => 'Détermine quels montants sont présentés au client sur la page de sélection du transporteur.',
        'tax_display_both' => 'HT et TTC (défaut)',
        'tax_display_ht' => 'HT uniquement',
        'tax_display_ttc' => 'TTC uniquement',

        'saved' => 'Paramètres enregistrés',
        'save_button' => 'Enregistrer',
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
