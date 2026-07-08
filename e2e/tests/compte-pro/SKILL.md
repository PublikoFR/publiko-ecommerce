# Skill E2E — Parcours "Compte pro" (Weklo)

Suite de tests Playwright couvrant le parcours client professionnel du storefront :
inscription, connexion/déconnexion, accès à l'espace pro (société/SIRET/conditions),
et historique de commandes.

## Périmètre couvert

| Fichier | Domaine |
|---|---|
| `inscription.spec.ts` | Formulaire d'inscription pro : validation SIRET (Luhn), e-mail unique, mot de passe, CGV, création de compte (SIRET pending INSEE). |
| `connexion.spec.ts` | Connexion/déconnexion, gardes d'accès (`/compte`, `/panier`), redirections invité ↔ connecté. |
| `espace-pro.spec.ts` | Accès aux données réservées pro : raison sociale, SIRET, TVA intra (page « Ma société »), rubriques sidebar. |
| `historique-commandes.spec.ts` | Liste des commandes du compte pro, détail commande, navigations (dashboard, sidebar), garde d'accès. |
| `helpers.ts` | `loginAs` / `logout` / `expectNoServerError` + constantes comptes. |

## Comptes & fixtures utilisés

Seedés par `database/seeders/PkoCustomerSeeder.php` (groupe `installateurs`) — **seuls
les clients pro reçoivent un `User` connectable** (les particuliers n'en ont pas).

| Constante (`helpers.ts`) | Valeur |
|---|---|
| `PRO_EMAIL` | `thierry.leroy@weklo.test` |
| `PRO_PASSWORD` | `testing123` |
| `PRO_COMPANY` | `Leroy Fermetures` |
| `PRO_SIRET` | `12345678900015` |
| `PRO_EMAIL_ALT` | `sophie.girard@weklo.test` |

SIRET synthétiques pour l'inscription (Luhn) : `35600000000043` (valide),
`35600000000001` (14 chiffres mais Luhn invalide).

## Cas limites & points d'attention

- **Attribution aléatoire des commandes** : `PkoOrderSeeder` répartit ~20 commandes
  (réf. `WK-XXXXXX`) au hasard sur les 5 clients. Le compte pro de test peut donc
  avoir **0 commande** dans un run. Les tests gèrent les deux cas (liste vide OU
  peuplée) ; le test de détail se `skip()` proprement s'il n'y a aucune commande.
- **INSEE désactivé en E2E** : aucune credential Sirene dans `docker-compose.e2e.yml`
  → `SireneClient::verify()` retourne toujours `Status::Pending` (pas d'appel réseau).
  Une inscription valide crée donc un compte **en attente** : il n'est **pas** connecté
  et redirige vers `/connexion` avec un message de validation.
- **Locale** : les messages de validation Laravel (e-mail pris, mot de passe court,
  CGV) dépendent de la locale de la stack ; les assertions ciblent la présence d'une
  erreur (`.text-danger-600`) + les libellés stables (`SIRET invalide` est un message
  applicatif codé en français, donc fiable).
- **Déconnexion** : bouton « Se déconnecter » (form POST `/deconnexion`) présent dans
  la sidebar de l'espace `/compte` uniquement.

## Bug corrigé dans ce parcours

`RegisterPage` connectait l'utilisateur puis redirigeait vers `/compte` **même pour un
compte pending**, ce qui provoquait une **boucle de redirection infinie** (`pro.customer`
renvoie les comptes non-actifs vers `/connexion`, que `redirect.if.pro` renvoie vers
`/compte` pour un user authentifié). Corrigé : un compte pending n'est plus connecté et
est redirigé vers `/connexion` avec le message d'attente.

## Relancer la suite

```bash
# Toute la suite compte-pro (stack e2e jetable, port auto)
npm run test:e2e -- compte-pro

# Un seul fichier
npm run test:e2e -- compte-pro/inscription.spec.ts

# Debug interactif
npm run test:e2e:debug -- compte-pro
```

Prérequis infra (cf. `e2e/README.md`) : Docker + `vendor/` et `public/build/` présents
dans le repo principal. Le `global-setup` lève une stack isolée (MySQL + Redis + app),
joue `migrate:fresh --seed`, puis détruit tout en teardown.
