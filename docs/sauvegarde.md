# Sauvegarde de la base

En production, le service `db-backup` (`compose.prod.yaml`) exécute `pg_dump` au démarrage puis toutes les `DB_BACKUP_INTERVAL_SECONDS` secondes (24 h par défaut). Il garde les `DB_BACKUP_KEEP` derniers fichiers (14 par défaut) dans le volume `db_backups`.

Le service utilise la même image que la base : la version de `pg_dump` correspond à celle du serveur.

## Surveillance

1. Créer un moniteur dédié, par exemple « Sauvegarde base nas-monitor », intervalle 1440 minutes, délai de grâce 60 minutes
2. Renseigner son URL de ping, vue depuis le réseau Docker :

```
DB_BACKUP_PING_URL=http://php/ping/<jeton>
```

Chaque sauvegarde envoie un ping de début, puis un ping de succès avec la durée et la taille, ou un ping d'échec avec l'erreur de `pg_dump`. La détection des résultats anormaux s'applique : un fichier deux fois plus petit que d'habitude déclenche une alerte.

## Vérification automatique de la restauration

Un dump qui ne se restaure pas ne sert à rien, et on ne le découvre qu'au pire moment. Tous les `DB_RESTORE_CHECK_EVERY` dumps (7 par défaut, `0` pour désactiver), `db-backup` lance `docker/db-backup/check-restore` : le dernier dump est restauré dans une base temporaire, le nombre de tables publiques est comparé à celui de la base réelle, puis la base temporaire est supprimée. La base réelle n'est jamais modifiée.

Pour être alerté, créer un second moniteur (« Restauration base nas-monitor », intervalle 10 080 minutes pour un contrôle hebdomadaire, délai de grâce 60 minutes) et renseigner `DB_RESTORE_PING_URL=http://php/ping/<jeton>`. Un échec envoie un ping d'échec avec la cause ; l'absence de ping déclenche l'alerte habituelle. Sans URL, le résultat reste dans les journaux du service.

Chaque dump est accompagné d'un fichier `.counts` : le nombre de lignes de chaque table, relevé juste avant le `pg_dump` (`row-counts`). Au contrôle, chaque table restaurée est comparée à ce relevé : une table absente, ou qui a perdu plus de 10 % de ses lignes, fait échouer le contrôle et le message nomme les tables. Les 10 % de tolérance absorbent les purges et les écritures entre le relevé et le dump. Un dump sans `.counts` (anciennes sauvegardes) est seulement contrôlé sur le nombre de tables.

Si le moniteur de restauration ne reçoit aucun ping pendant sa période (une semaine) plus son délai de grâce, l'alerte habituelle des moniteurs de battement part : le silence d'un contrôle qui ne tourne plus est donc signalé comme un échec.

Le script est testé en CI sur une base jetable (`tests/Shell/check-restore-test.sh`).

## Lister les sauvegardes

```bash
docker compose --env-file .env.nas -f compose.yaml -f compose.prod.yaml exec db-backup ls -lt /backups
```

## Restaurer

Arrêter l'application et le worker pour qu'aucune écriture n'ait lieu pendant la restauration, puis :

```bash
docker compose --env-file .env.nas -f compose.yaml -f compose.prod.yaml stop php worker
docker compose --env-file .env.nas -f compose.yaml -f compose.prod.yaml exec db-backup pg_restore --clean --if-exists --no-owner --dbname="$PGDATABASE" /backups/nas-monitor-AAAAMMJJ-HHMMSS.dump
docker compose --env-file .env.nas -f compose.yaml -f compose.prod.yaml start php worker
```

## Copie hors du NAS

Le volume `db_backups` est sur le même NAS que la base : il protège d'une erreur de manipulation ou d'une base corrompue, pas d'une panne du NAS. Les fichiers doivent être copiés ailleurs (disque externe, stockage distant), chiffrés : ils contiennent les empreintes des mots de passe, des jetons et des codes de secours, ainsi que les secrets TOTP chiffrés. La clé `TOTP_ENCRYPTION_KEY` se conserve à part, dans un gestionnaire de mots de passe : sans elle, une base restaurée ne permet plus de se connecter aux comptes protégés (`app:user:totp-disable` puis `app:user:totp-enable`).
