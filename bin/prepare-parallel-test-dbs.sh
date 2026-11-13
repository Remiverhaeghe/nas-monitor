#!/bin/sh
# Crée les bases de test app_test1..N (une par thread Infection/ParaTest).
# Usage : docker compose exec php sh bin/prepare-parallel-test-dbs.sh [nombre]
set -eu

vCount="${1:-4}"
vIndex=1
while [ "$vIndex" -le "$vCount" ]; do
    echo "Base de test n°$vIndex"
    TEST_TOKEN="$vIndex" bin/console -e test doctrine:database:create --if-not-exists --no-interaction
    TEST_TOKEN="$vIndex" bin/console -e test doctrine:migrations:migrate --no-interaction
    vIndex=$((vIndex + 1))
done
