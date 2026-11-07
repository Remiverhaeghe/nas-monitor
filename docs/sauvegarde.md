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

## Lister les sauvegardes

```bash
docker compose -f compose.yaml -f compose.prod.yaml exec db-backup ls -lt /backups
```

## Restaurer

Arrêter l'application et le worker pour qu'aucune écriture n'ait lieu pendant la restauration, puis :

```bash
docker compose -f compose.yaml -f compose.prod.yaml stop php worker
docker compose -f compose.yaml -f compose.prod.yaml exec db-backup pg_restore --clean --if-exists --no-owner --dbname="$PGDATABASE" /backups/nas-monitor-AAAAMMJJ-HHMMSS.dump
docker compose -f compose.yaml -f compose.prod.yaml start php worker
```

## Copie hors du NAS

Le volume `db_backups` est sur le même NAS que la base : il protège d'une erreur de manipulation ou d'une base corrompue, pas d'une panne du NAS. Les fichiers doivent être copiés ailleurs (disque externe, stockage distant), chiffrés : ils contiennent les empreintes des mots de passe, des jetons et des codes de secours, ainsi que les secrets TOTP chiffrés. La clé `TOTP_ENCRYPTION_KEY` se conserve à part, dans un gestionnaire de mots de passe : sans elle, une base restaurée ne permet plus de se connecter aux comptes protégés (`app:user:totp-disable` puis `app:user:totp-enable`).
