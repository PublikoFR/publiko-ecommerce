<?php

declare(strict_types=1);

return [
    /*
     * Masque le sous-menu on-page (sub-nav des clusters) désormais redondant avec
     * les sous-menus imbriqués de la sidebar. Concerne uniquement les clusters
     * « internes » qui overrident getClusteredComponents() (Catalogue settings,
     * Boutique & paiement, Système & données) — les clusters Taxes / Expédition /
     * Pennylane gardent leur sub-nav.
     *
     * Réactivable sans toucher au code : ADMIN_NAV_HIDE_CLUSTER_SUBNAV=false
     */
    'hide_cluster_subnav' => env('ADMIN_NAV_HIDE_CLUSTER_SUBNAV', true),
];
