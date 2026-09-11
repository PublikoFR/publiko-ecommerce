<?php

declare(strict_types=1);

namespace Pko\CustomerAuth\Console;

use Illuminate\Console\Command;
use Lunar\Models\Customer;
use Pko\CustomerAuth\Support\DefaultCustomerGroup;

/**
 * Rattache au groupe par défaut (« Nouveau client ») les clients qui n'y sont
 * pas — réparation des comptes créés pendant que le lookup du groupe échouait
 * (handle non slugifié, cf. DefaultCustomerGroup). L'enjeu est la TARIFICATION :
 * un client sans groupe n'a pas de prix. L'accès pro, lui, ne dépend plus
 * d'aucun groupe (cf. ProAccess et docs/admin.md).
 *
 * À lancer avec discernement : la commande ne sait pas distinguer un client
 * réellement orphelin d'un client dont l'admin a délibérément retiré « Nouveau
 * client » pour le qualifier — elle le lui remettrait.
 *
 * Idempotente : ne touche que les clients réellement orphelins, n'en détache
 * aucun. `--dry-run` pour compter sans écrire.
 */
class BackfillDefaultCustomerGroupCommand extends Command
{
    protected $signature = 'pko:customers:backfill-default-group {--dry-run : Affiche le nombre de clients concernés sans rien modifier}';

    protected $description = 'Rattache les clients orphelins au groupe client par défaut';

    public function handle(): int
    {
        $group = DefaultCustomerGroup::resolve();

        if ($group === null) {
            $this->error('Groupe par défaut introuvable (handle attendu : '.DefaultCustomerGroup::handle().').');

            return self::FAILURE;
        }

        $this->line('Groupe par défaut : #'.$group->id.' — '.$group->name.' ('.$group->handle.')');

        $orphans = Customer::whereDoesntHave(
            'customerGroups',
            fn ($query) => $query->whereKey($group->id)
        )->pluck('id');

        if ($orphans->isEmpty()) {
            $this->info('Aucun client à rattacher.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn($orphans->count().' client(s) seraient rattachés (dry-run, rien écrit).');

            return self::SUCCESS;
        }

        foreach ($orphans as $id) {
            Customer::find($id)?->customerGroups()->syncWithoutDetaching([$group->id]);
        }

        $this->info($orphans->count().' client(s) rattachés au groupe par défaut.');

        return self::SUCCESS;
    }
}
