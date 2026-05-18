# pko/lunar-pennylane

Intégration Pennylane (API v2) pour Lunar : émission automatique de factures et d'avoirs.

## Installation

Package interne, déclaré via path repository. Ajouter dans le `composer.json` racine :

```json
"pko/lunar-pennylane": "@dev"
```

Puis :

```bash
make composer CMD='install'
make artisan CMD='migrate'
make artisan CMD='vendor:publish --tag=pennylane-config'
```

## Configuration

Variables d'environnement (cf. `config/pennylane.php`) :

```
PENNYLANE_API_TOKEN=
PENNYLANE_INVOICE_TEMPLATE_ID=
PENNYLANE_TRIGGER_STATUS=payment-received
PENNYLANE_AUTO_CREDIT_NOTE=true
PENNYLANE_SANDBOX=false
PENNYLANE_QUEUE=default
```

## Documentation

Voir `docs/packages/pennylane.md` à la racine du projet.
