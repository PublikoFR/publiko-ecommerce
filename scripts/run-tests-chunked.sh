#!/bin/sh
# Lance la suite PHPUnit en PLUSIEURS processus PHP successifs (un chunk par
# dossier de tests) au lieu d'un unique process pour toute la suite.
#
# Pourquoi : au-dela de ~250 tests dans un seul process PHP, l'accumulation
# d'etat (graphes d'objets, extensions C, GC) provoquait en fin de suite un
# segfault intermittent (signal 11, exit 139) OU un hang (exit 124) — ~80% des
# runs complets. Chaque test/fichier passe pourtant en isolation : le probleme
# est purement CUMULATIF. Decouper en chunks frais borne l'etat par process
# bien en-dessous du seuil de crash et rend `make test` deterministe.
#
# Chaque chunk = un `php artisan test <path>` = un process PHP neuf (RefreshDatabase
# re-migre la base de test au demarrage de chaque chunk). Les chunks tournent en
# SERIE et partagent la meme base de test (un seul a la fois), donc aucune
# isolation DB supplementaire n'est requise.
#
# A executer DEPUIS la racine du projet (cwd = /var/www/html dans le conteneur).
set -u

# Stack C elargi (defaut 8 Mo -> 64 Mo). Le signal 11 aleatoire observe frappait
# au SHUTDOWN du process PHP (apres que tous les tests du chunk soient passes) :
# la destruction recursive du graphe d'objets accumule (container Laravel, Livewire,
# Lunar) depassait par moments la pile C de 8 Mo -> SIGSEGV. Elargir la pile borne
# ce risque. Voir docs/workflow.md, section "Segfault signal 11".
ulimit -s 65536 2>/dev/null || true

rc=0

run() {
    echo ""
    echo "==> chunk: $*"
    php artisan test "$@" || rc=1
}

# Unit : tests legers (aucune accumulation problematique), un seul chunk.
run tests/Unit

# Feature : un chunk par sous-dossier. Chaque dossier reste petit (<= ~46 tests),
# tres en-dessous du seuil cumulatif. La boucle est dynamique : tout nouveau
# sous-dossier de tests/Feature est automatiquement pris en charge.
for d in tests/Feature/*/; do
    [ -d "$d" ] || continue
    run "$d"
done

# Feature : fichiers de test a la racine de tests/Feature (hors sous-dossier).
set -- tests/Feature/*Test.php
if [ -e "$1" ]; then
    run "$@"
fi

echo ""
if [ "$rc" -eq 0 ]; then
    echo "==> Tous les chunks sont verts."
else
    echo "==> Au moins un chunk a echoue (voir ci-dessus)."
fi
exit "$rc"
