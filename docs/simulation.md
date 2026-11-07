# Simulation locale du NAS

Reproduit en local le déploiement prévu sur le NAS : image de production, worker séparé et tâches planifiées qui envoient leurs pings.

| Service | Rôle |
|---|---|
| `php` | Application en image de production, ports 8080 et 8443 |
| `worker` | Scheduler et envoi des e-mails |
| `database` | PostgreSQL |
| `mailer` | Mailpit, interface sur http://localhost:8025 |
| `backup` | Sauvegarde restic d'un dossier toutes les 2 minutes |
| `sync` | Synchronisation fictive toutes les minutes, en échec dans 30 % des cas |

Le projet Docker `nas-sim` est distinct de l'environnement de développement : volumes et données séparés.

## Démarrage

Application, worker et base :

```bash
docker compose -p nas-sim --env-file simulation/simulation.env -f compose.yaml -f compose.prod.yaml -f compose.simulation.yaml up --wait --build
docker compose -p nas-sim --env-file simulation/simulation.env -f compose.yaml -f compose.prod.yaml -f compose.simulation.yaml exec php bin/console app:user:create admin@simulation.local
```

Création des deux moniteurs, chaque commande affiche un jeton :

```bash
docker compose -p nas-sim --env-file simulation/simulation.env -f compose.yaml -f compose.prod.yaml -f compose.simulation.yaml exec php bin/console app:monitor:create "Sauvegarde restic" --interval=180 --grace=60
docker compose -p nas-sim --env-file simulation/simulation.env -f compose.yaml -f compose.prod.yaml -f compose.simulation.yaml exec php bin/console app:monitor:create "Synchronisation distante" --interval=120 --grace=60
```

Les jetons vont dans `simulation/tokens.env`, non versionné :

```
PING_TOKEN_BACKUP=<jeton de la sauvegarde>
PING_TOKEN_SYNC=<jeton de la synchronisation>
```

Lancement des tâches :

```bash
docker compose -p nas-sim --env-file simulation/simulation.env --env-file simulation/tokens.env -f compose.yaml -f compose.prod.yaml -f compose.simulation.yaml --profile jobs up -d --build
```

Tableau de bord : https://localhost:8443

## Scénarios

- **Échecs** : la synchronisation échoue au hasard, ce qui ouvre un incident et envoie un e-mail, puis un e-mail de rétablissement au succès suivant
- **Ping manquant** : arrêter une tâche (`... stop backup`) la fait passer en retard après 3 minutes, puis en panne après le délai de grâce
- **Pause** : mettre un moniteur en pause depuis l'interface ferme son incident sans e-mail

## Arrêt

```bash
docker compose -p nas-sim --env-file simulation/simulation.env -f compose.yaml -f compose.prod.yaml -f compose.simulation.yaml --profile jobs down
```

Ajouter `--volumes` pour supprimer aussi les données de la simulation.

## Fichiers

| Fichier | Rôle |
|---|---|
| `compose.simulation.yaml` | Mailpit, tâches simulées, adresses d'alerte |
| `simulation/simulation.env` | Ports et secrets propres à la simulation |
| `simulation/Dockerfile` | Image Alpine avec restic, curl et jq |
| `simulation/backup` | Sauvegarde restic, pings de début et de fin avec durée, taille et snapshot |
| `simulation/flaky-sync` | Tâche qui échoue selon `FAIL_PERCENT` |
| `simulation/run-every` | Relance une commande à intervalle fixe |
