# AI Importer — guide staff back-office

Ce document s'adresse aux équipes **back-office** qui gèrent l'import de catalogues fournisseurs. Il décrit **comment** utiliser le module AI Importer au quotidien, pas comment il est construit (voir `docs/` et `docs/ai-importer-migration-plan.md` pour la partie développeur).

Accès : admin → **Imports**.

---

## Le parcours en 4 étapes

Menu **Catalogue → Imports**, deux entrées : **Imports de produits** et **Configurations**.

| Étape | Où | Ce qu'on y fait |
|---|---|---|
| 0. Configuration (une fois par fournisseur) | Imports → Configurations | Décrire le fichier : ses feuilles, quelle colonne devient quel champ produit |
| 1. Préparation | Imports → **Nouvel import** | Déposer le fichier, choisir quoi calculer, lancer |
| 2. Vérification | La fiche de l'import | Contrôler les lignes préparées, corriger, écarter |
| 3. Import | Bouton **Importer dans le catalogue** | Écrire dans le catalogue (sauvegarde automatique avant) |
| 4. Terminé | La fiche de l'import | Bilan ; réessayer les erreurs ou annuler l'import |

La fiche d'un import affiche ce fil en haut de page et ne propose qu'**une action principale** :
celle de l'étape en cours. Le reste (fichier source, sauvegarde, planificateur, suppression) est
dans le menu **Plus**. La page se met à jour seule pendant un traitement.

Les clés API des modèles IA se règlent dans **Configuration → Configurations LLM**.

---

## Créer une configuration fournisseur

Une **configuration** décrit comment lire le fichier d'un fournisseur.

1. *Imports → Configurations → Nouvelle configuration*. Donner un **nom** (unique) et le fournisseur.
2. Onglet **Feuilles du fichier**
   - **Feuille principale** : celle qui a une ligne par produit (ex. `B01_COMMERCE`).
   - **Colonne commune aux feuilles** : celle qui identifie le produit dans toutes les feuilles
     (ex. `REFCIALE`).
   - **Feuilles liées** : une ligne par autre feuille à lire, avec « Une seule » ou « Plusieurs »
     lignes par produit, sa colonne clé et, pour une feuille à plusieurs lignes, sa colonne
     « type » (ex. `MTYP` pour les médias). Laisser **1ʳᵉ ligne = en-têtes** coché sauf si la
     feuille n'a vraiment pas d'en-têtes.
3. Onglet **Colonnes** : une ligne par champ produit. Chaque ligne se lit d'un coup d'œil —
   `Prix HT  B01_COMMERCE › M · défaut 0 · Multiplier ×1.2`. Badge **IA** = la colonne déclenche
   un appel facturé par ligne ; badge **non importée** = le fichier préparé la contiendra mais
   aucun champ du catalogue ne lui correspond.
   - L'**engrenage** ouvre la source (feuille, colonne, valeur par défaut) et la suite de
     transformations.
   - Déplier la ligne pour changer de champ produit. Les flèches réordonnent ; l'ordre est
     conservé.
   - La recherche et « Masquer les colonnes vides » filtrent la liste.
4. Onglet **IA** : contexte commun à tous les prompts. `{{categories}}` et `{{features}}` y sont
   remplacés à chaque préparation par les catégories et caractéristiques du site.
5. Onglet **JSON** : la même configuration en texte, pour un copier-coller ou un réglage avancé.

Un bandeau rouge ou orange en haut de la page signale ce qui empêcherait un import d'aboutir
(aucune colonne d'identification, feuille non déclarée, en-têtes décochés alors que des colonnes
sont désignées par leur intitulé…). La liste des configurations affiche le même état : **Prête**,
**À vérifier**, **À corriger**.

Depuis la liste : **Nouvel import** (lance l'étape 1 avec cette configuration), et dans le menu
de la ligne *Exporter (JSON)*, *Dupliquer*, *Supprimer*. **Importer (JSON)** en haut de page
charge une configuration exportée d'ici ou d'un ancien module.

### Migrer une config PrestaShop existante

Les configs du module PS `publikoaiimporter` sont importables telles quelles :

```bash
make artisan CMD='ai-importer:import-ps-config /chemin/config.json --name=somfy --supplier=Somfy'
```

Le format JSON est déjà compatible (pipeline `actions[]` v1). La commande lifte automatiquement les vieilles configs `action:{}` (objet unique) vers `actions:[{}]`.

---

## Tester une config sans lancer d'import

Avant de créer un vrai job sur 50 000 lignes, vérifie le mapping sur les 5 premières lignes :

```bash
make artisan CMD='ai-importer:preview-config somfy /chemin/catalog.xlsx --rows=5'
```

La commande affiche un tableau des valeurs mappées. Utile pour valider qu'un `multiply`, un `template` ou un `feature_build` produit bien ce qu'on attend.

---

## Les 18 actions disponibles

Chaque action lit la valeur courante (ou certaines colonnes du row source), retourne une nouvelle valeur, et passe à la suivante.

| Catégorie | Actions | Note |
|---|---|---|
| **Calcul** | `math`, `round` | `math` = ×/÷/+/−, paramètre `operation` + `value` |
| **Texte** | `change_case` (upper/lower/capitalize), `trim`, `truncate`, `slugify`, `replace`, `regex_replace` | |
| **Combinaison** | `concat`, `template`, `copy` | `template` = chaîne avec placeholders `{key}` |
| **Lookup** | `map` | Table source → cible, `multi_value: true` pour traiter CSV |
| **Date** | `date_format` | Formats `Y-m-d`, `d/m/Y`… |
| **Validation** | `validate_ean13` | Retourne la chaîne si checksum EAN valide, sinon vide |
| **Relation 1-N** | `multiline_aggregate` | Lit une feuille secondaire, agrège selon `method` (concat/count/first/last/json_array) |
| **IA** | `llm_transform` | Prompt + colonnes → réponse Claude/OpenAI |
| **Caractéristiques catalogue** | `feature_build` | Construit le hash `{family_handle: [value_handle]}` attendu par le writer. Les features atterrissent dans `pko_feature_values` (pas dans `attribute_data` Lunar) |

### Exemple de pipeline price HT → TTC

```json
"price_cents": {
  "col": "PRIX_NET",
  "actions": [
    { "type": "math", "operation": "multiply", "value": 1.2 },
    { "type": "math", "operation": "multiply", "value": 100 },
    { "type": "round", "decimals": 0 }
  ]
}
```

Le writer attend les prix en **cents entiers** : on multiplie par 1,2 (TVA 20 %), puis par 100 pour passer en centimes, puis on arrondit.

### Exemple `feature_build`

```json
"features": {
  "actions": [
    {
      "type": "feature_build",
      "families": {
        "marque":       { "col": "MARQUE" },
        "applications": { "col": "USAGE", "multi_value": true, "separator": "|" },
        "matiere":      { "col": "MATERIAL", "values_map": { "alu": "aluminium", "pvc": "pvc" } }
      }
    }
  ]
}
```

Les handles sortis (`somfy`, `residentiel`, `aluminium`…) doivent **exister au préalable** dans *Catalogue → Caractéristiques* (Publiko Tree Manager). Le writer associe le produit aux valeurs correspondantes via `Features::syncByHandles()`.

---

## Lancer un import

### 1. Préparation — « Nouvel import »

**Étape « Fichier »**

1. Choisir la **configuration**, déposer le **fichier** (XLSX, XLS ou CSV, 100 Mo max).
2. Le **contrôle du fichier** s'affiche aussitôt : les feuilles trouvées avec leur nombre de
   lignes, et ce qui ne correspond pas à la configuration.
   - Rouge (bloquant) : feuille absente, colonne de jointure introuvable, configuration sans
     colonne d'identification. Impossible de continuer : la préparation échouerait.
   - Orange : une colonne de données est introuvable — son champ restera vide.

**Étape « Réglages »**

- **Nombre de lignes** : vide = tout le fichier. Mettre 10 ou 20 pour un essai.
- **Produits concernés** : toutes les lignes, seulement les nouveaux produits, ou seulement les
  produits déjà au catalogue. Une ligne écartée n'est **pas calculée** : aucun appel IA payé
  pour un produit qui ne sera pas importé.
- **Colonnes à calculer** : tout est coché par défaut. « Décocher les colonnes IA » retire d'un
  clic les colonnes facturées. Sous la liste : le **nombre d'appels IA à prévoir** (colonnes IA ×
  lignes) et les valeurs fixes de la configuration, toujours appliquées (fournisseur…).
- **Réglages avancés** (repliés) : conduite en cas d'erreur, taille du lot, import automatique à
  une date.

**Lancer la préparation** ouvre la fiche de l'import. Le traitement démarre dans la minute et
continue même si on quitte la page.

### 2. Vérification — les lignes préparées

Rien n'est encore écrit dans le catalogue.

- Onglets **Toutes / À importer / Erreurs / Importées / Écartées**, chacun avec son compteur.
- Colonnes : photo, produit (nom + référence), marque, **prix HT final** (avec « dont éco … »
  quand le produit porte une éco-participation), stock, statut. Les colonnes brutes du fichier
  s'ajoutent par le sélecteur de colonnes, en haut à droite du tableau.
- **Cliquer une ligne** ouvre sa fiche : tous les champs, groupés, et l'historique de la ligne.
  Corriger une ligne en erreur la remet « à importer ».
- **Écarter** retire une ligne de l'import sans la supprimer ; **Rétablir** la remet.
- Un bandeau prévient si **plusieurs lignes portent la même référence** (elles écraseraient le
  même produit) : signe habituel d'une colonne de référence mal configurée.
- **Exporter (CSV)** : séparateur `;`, réimportable par *Plus → Importer un fichier déjà préparé*.

### 3. Import — « Importer dans le catalogue »

La fenêtre demande :

- **Produits déjà au catalogue** : tout mettre à jour, prix uniquement, stock uniquement, prix et
  stock. Un nouveau produit est toujours créé en entier.
- **Produits concernés** et **Reconnaître un produit existant par** (référence ou EAN).
- **N'écrire que certains champs** : désactivé par défaut (tout est écrit).
- **En cas d'erreur sur une ligne** : passer la ligne, arrêter, ou arrêter et tout annuler.
- **Quand** : maintenant, ou à une date.

Une **sauvegarde** des produits concernés est prise avant la première écriture.

### 4. Terminé

Bilan : produits créés, mis à jour, lignes en erreur. Selon le cas :

- **Réessayer les lignes en erreur** : elles repassent « à importer » ; corriger puis
  **Importer les lignes restantes**. Les lignes déjà importées ne sont pas rejouées.
- **Annuler l'import** : le catalogue revient à son état d'avant (voir « Annulation » plus bas).
  Les lignes repassent « à vérifier » et l'import peut être relancé.

### Les états d'un import

| État | Signification | Action proposée |
|---|---|---|
| Préparation en file d'attente | Prise en charge sous 2 minutes | Arrêter la préparation |
| Préparation en cours | Lignes en cours de calcul | Arrêter la préparation |
| Préparation interrompue | Erreur (affichée) ou arrêt manuel | Relancer la préparation (reprend où elle s'est arrêtée) |
| À vérifier | Lignes prêtes, rien n'est écrit | Importer dans le catalogue |
| Import programmé | Démarrera à la date choisie | Importer maintenant · Annuler la programmation |
| Import en cours | Écriture dans le catalogue | — |
| Import interrompu | Arrêt sur erreur | Reprendre l'import · Annuler l'import |
| Terminé | Import fini | Réessayer les erreurs · Annuler l'import |
| Import annulé | Catalogue restauré | Importer dans le catalogue |

### Fichier déjà préparé

*Imports → Plus → Importer un fichier déjà préparé* : un CSV (séparateur `;`, 1ʳᵉ ligne =
en-têtes) dont les colonnes portent déjà les noms des champs produit arrive directement à
l'étape de vérification, sans configuration ni IA.

---

## Images, documents et vidéos

Les fichiers sont téléchargés **à l'import** (étape 3), pas à la préparation.

### Images (`images`)

- Liste d'URLs (séparées par des virgules, ou tableau JSON). Elles arrivent dans la
  **médiathèque** (dossier *Products*) et sont liées au produit.
- **Jamais de doublon** : une URL déjà importée, le même fichier servi sous une autre URL, ou un
  fichier déjà déposé à la main dans la médiathèque est **réutilisé**. Réimporter le même
  fichier ne retélécharge rien et n'ajoute aucune image.
- Par défaut les images s'**ajoutent** à la galerie. La colonne `replace_images` à `1` (ancien
  nom : `delete_existing_images`) **remplace** la galerie par les images de la ligne.
- Une image qui ne se télécharge pas est signalée dans le journal avec sa cause
  (`Image non importée (HTTP 404): …`) ; la ligne est importée quand même.

Exemple (feuille médias à plusieurs lignes par produit) :

```json
"images": {
  "col": "N", "sheet": "B03_MEDIA",
  "actions": [{ "type": "multiline_aggregate", "method": "concat", "separator": ",", "filter_type": "PHOTO" }]
}
```

### Documents (`documents`)

Notices et brochures PDF : médiathèque (dossier *Documents*) puis onglet documents du produit,
rangés par type (`NOTICE`, `BROCH`…). Un document déjà attaché n'est ni dupliqué ni déplacé.

### Vidéos (`videos`)

URLs YouTube, Vimeo, Dailymotion ou MP4, avec titre optionnel. Une même vidéo sous deux formes
d'URL (`youtu.be/…`, `watch?v=…`) n'est attachée qu'une fois.

---

## Prix, éco-participation, frais de port

- **Prix** (`price_cents`, ou `price_tex` en euros) : le prix de base du produit est **remplacé**,
  jamais doublé. Les prix par groupe de clients et par quantité ne sont pas touchés.
- **Éco-participation** (`ecotax_cents`, ou `ecotax` en euros) : le prix du catalogue l'**inclut**.
  Avec `price_tex` (hors éco) + `ecotax`, l'import additionne les deux ; le tableau de
  vérification montre le prix final et « dont éco … ». Sur un fichier FAB-DIS, lire la somme des
  lignes `CONTRIB` de la feuille réglementaire plutôt que « la première ligne » :

  ```json
  "ecotax": {
    "col": "L", "sheet": "B04_REGLEMENTAIRE",
    "actions": [{ "type": "multiline_aggregate", "method": "sum", "filter_type": "CONTRIB" }]
  }
  ```

  (feuille déclarée « Plusieurs » lignes par produit, colonne « type » `RTYP`).
- **Frais de port** : `port_mode` (`inherit`, `standard`, `flat`, `free`, `quote`) et
  `transport_price_cents` (forfait, ou `additional_shipping_cost` en euros). Une valeur fournie
  **remplace** celle du produit ; une valeur vide ou `0` **n'y touche pas**. Un forfait > 0 passe
  le produit en port forfaitaire.
- **Fournisseur** (`supplier`) : retrouvé sans tenir compte des majuscules. C'est lui qui porte
  la règle de port héritée par ses produits.

---

## Les clés API LLM

- *Configuration → Configurations LLM → Nouveau*.
- **Provider** : Claude (Anthropic) ou OpenAI.
- **Clé API** : la clé est **chiffrée** en base (cast Laravel `encrypted` avec `APP_KEY`). Pas de clair en DB, ni dans les dumps.
- Coche **Configuration par défaut** sur la clé principale — les actions `llm_transform` sans `llm_config_id` l'utiliseront.

---

## Annulation d'un import (sauvegarde)

À chaque import, une **sauvegarde JSON gzippée** est stockée dans `storage/app/ai-importer/backups/`. Elle contient l'état *avant* import des produits touchés (+ leurs variantes, prix, catégories).

- Bouton **Annuler l'import** sur un import terminé ou interrompu → restauration en transaction : produits mis à jour remis à
  l'identique (nom/descriptions, marque, fournisseur, mode de port, prix, prix d'achat, stock,
  EAN/MPN, quantité minimale, dimensions, collections, tags, caractéristiques, images, documents,
  vidéos), **produits créés par l'import supprimés** (ou passés en brouillon s'ils ont déjà été
  commandés ou mis en panier), lignes remises « à importer », recherche réindexée.
- Les fichiers images/documents téléchargés par l'import restent dans la médiathèque (seuls leurs
  liens aux produits sont annulés).
- Les backups restent sur disque tant que tu ne les supprimes pas à la main — prévoir un nettoyage périodique si ça s'accumule.
- La sauvegarde se télécharge depuis *Plus → Télécharger la sauvegarde*. Format lisible : `zcat job_xxx.json.gz | jq .`.

---

## Dépannage

| Symptôme | Piste |
|---|---|
| Toutes les lignes ont la même référence (ex. « SOM ») | La colonne de référence n'est pas lue : intitulé absent des en-têtes, ou « 1ʳᵉ ligne = en-têtes » décoché sur la feuille. Le bandeau de la configuration et le contrôle du fichier le signalent |
| Images, poids, documents vides sur toutes les lignes | La colonne commune aux feuilles est introuvable : aucune ligne des feuilles liées n'est rapprochée |
| La 1ʳᵉ ligne préparée contient les intitulés (`LIBELLE40`, `MARQUE`…) | « 1ʳᵉ ligne = en-têtes » décoché sur la feuille principale |
| Beaucoup d'erreurs « reference manquante » | Aucune colonne d'identification mappée, ou sa colonne source est vide |
| Caractéristiques ou catégories ignorées (avertissement) | La valeur n'existe pas sur le site : la créer dans *Catalogue → Catégorisation*, ou affiner le prompt |
| Prix divisés par 100 | La colonne est mappée sur `price_cents` avec des euros : utiliser `price_tex`, ou multiplier par 100 |
| « Image non importée (HTTP 403/404) » | L'URL du fichier fournisseur est morte ou protégée ; la cause est dans le journal |
| La préparation reste « en file d'attente » plus de 2 minutes | *Plus → Lancer le planificateur maintenant*. Si rien ne bouge : le worker des imports ne tourne pas (`make artisan CMD='schedule:list'` doit lister `queue:work … --queue=ai-importer-parse,ai-importer-import`) |
| Fichier refusé à l'envoi | Plus de 100 Mo, ou format non reconnu (XLSX, XLS, CSV attendus) |

---

## Commandes utiles

```bash
# Tester une config (dry-run)
make artisan CMD='ai-importer:preview-config somfy /tmp/catalog.xlsx --rows=10'

# Migrer une config PrestaShop
make artisan CMD='ai-importer:import-ps-config /tmp/ps-config.json --name=somfy --supplier=Somfy --replace'

# Lancer manuellement le scheduler (normalement automatique toutes les 5 min)
make artisan CMD='ai-importer:run-scheduled --dry'   # liste ce qui tournerait
make artisan CMD='ai-importer:run-scheduled'         # exécute

# Worker queue des imports (dans le conteneur app) — normalement lancé par le
# scheduler chaque minute ; la connexion vaut `database-long` / `redis-long`
# selon QUEUE_CONNECTION (cf. config('ai-importer.queues.connection'))
php artisan queue:work redis-long --queue=ai-importer-parse,ai-importer-import --stop-when-empty --timeout=7200
```
