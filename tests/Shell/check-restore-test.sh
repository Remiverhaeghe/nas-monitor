#!/bin/sh
# Teste check-restore sur une base jetable : cas nominal, comptage de lignes, table ajoutée après le dump, dump illisible, aucun dump
# Lancé dans un conteneur PostgreSQL : docker compose exec -T database sh -s < tests/Shell/check-restore-test.sh
set -u
vScript="${CHECK_RESTORE:-/tmp/check-restore}"
# row-counts est copié à côté de check-restore, qui le cherche dans son dossier
vCounts="$(dirname "$vScript")/row-counts"
vDir=$(mktemp -d)
export BACKUP_DIR="$vDir" PGDATABASE=restore_source
vFailures=0

expect() {
    # $1 : code attendu, $2 : libellé
    sh "$vScript" > /tmp/check-restore.out 2>&1
    vCode=$?
    if [ "$vCode" -ne "$1" ]; then
        echo "ÉCHEC : $2 (code $vCode, attendu $1)"; cat /tmp/check-restore.out
        vFailures=$((vFailures + 1))
    else
        echo "ok : $2"
    fi
}

dropdb --if-exists restore_source
createdb restore_source
psql -q -d restore_source -c 'CREATE TABLE a (i int); CREATE TABLE b (i int); INSERT INTO a VALUES (1)'

expect 2 "aucun dump"
sh "$vCounts" > "$vDir/nas-monitor-20260101-000000.counts"
pg_dump --format=custom --file="$vDir/nas-monitor-20260101-000000.dump"
expect 0 "dump restaurable, comptage de lignes identique"
printf 'a 100\nb 0\n' > "$vDir/nas-monitor-20260101-000000.counts"
expect 1 "lignes manquantes dans la restauration"
grep -q "a (1 au lieu de 100)" /tmp/check-restore.out || { echo "ÉCHEC : message de lignes manquantes"; vFailures=$((vFailures + 1)); }
printf 'a 1\nb 0\n' > "$vDir/nas-monitor-20260101-000000.counts"
expect 0 "comptage dans la tolérance"
printf 'a 1\nzz 4\n' > "$vDir/nas-monitor-20260101-000000.counts"
expect 1 "table du comptage absente de la restauration"
rm "$vDir/nas-monitor-20260101-000000.counts"
expect 0 "dump sans fichier de comptage"
psql -q -d restore_source -c 'CREATE TABLE c (i int)'
expect 1 "nombre de tables différent"
echo illisible > "$vDir/nas-monitor-20270101-000000.dump"
expect 1 "dump illisible"

vLeft=$(psql -tA -d postgres -c "SELECT count(*) FROM pg_database WHERE datname LIKE 'restore_check_%'")
if [ "$vLeft" != "0" ]; then echo "ÉCHEC : base temporaire non supprimée"; vFailures=$((vFailures + 1)); fi

dropdb restore_source
rm -rf "$vDir"
exit "$vFailures"
