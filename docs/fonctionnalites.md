# Fonctionnalités

## Types de moniteurs

| Type | Fonctionnement | Cible |
|---|---|---|
| Tâche planifiée | La tâche envoie un ping à chaque exécution | aucune |
| Site web (HTTP) | Requête `GET`, succès si le code est inférieur à 400 | URL complète |
| Port TCP | Ouverture d'une connexion | `hôte:port` |
| Certificat TLS | Vérification du certificat, échec s'il expire dans moins de `TLS_WARNING_DAYS` jours | `hôte:port` |

Pour une tâche planifiée, l'intervalle est la durée maximale entre deux pings de succès. Pour un contrôle actif, c'est la fréquence du contrôle. Chaque contrôle actif est enregistré comme un ping, avec sa durée et son résultat.

Le jeton de ping n'existe que pour les tâches planifiées ; seule son empreinte SHA-256 est conservée.

## Statuts

| Statut | Condition |
|---|---|
| Nouveau | Aucun signal depuis la création ou la reprise |
| Opérationnel | Dernier succès dans l'intervalle attendu |
| En retard | Intervalle dépassé, délai de grâce en cours |
| En panne | Délai de grâce dépassé, ou dernier signal en échec |
| En pause | Surveillance suspendue |

Un ping `start` est enregistré sans modifier le statut.

## Incidents et alertes

- Le passage en panne ouvre un incident et envoie une alerte « En panne »
- Le retour à l'état opérationnel ferme l'incident et envoie une alerte « Rétabli » avec la durée de la panne
- Un moniteur reste dans un seul incident tant qu'il est en panne : une seule alerte est envoyée
- La mise en pause ferme l'incident en cours sans alerte
- L'état « En retard » n'envoie pas d'alerte

Chaque moniteur choisit son canal : e-mail, ntfy (notification sur le téléphone) ou les deux. Les dates des alertes sont à l'heure de Paris.

## Résultats anormaux

Pour une tâche planifiée, chaque succès est comparé à la médiane des 10 succès précédents (5 au minimum pour juger) :

- taille en baisse de plus de `ANOMALY_SIZE_DROP_PERCENT` % (50 par défaut)
- durée supérieure à `ANOMALY_DURATION_FACTOR` fois la médiane (3 par défaut)

Le ping reste un succès et le statut ne change pas, mais l'anomalie est enregistrée, affichée dans l'historique et signalée par une alerte « Anomalie ». Une seule alerte est envoyée par série d'exécutions anormales consécutives.

## Tâches planifiées internes

| Fréquence | Tâche |
|---|---|
| Chaque minute | Contrôles actifs dus, puis vérification des délais |
| Toutes les 5 minutes | Signal vers un service extérieur (`HEARTBEAT_URL`), `/fail` si la base est injoignable |
| Chaque jour à 3 h 30 UTC | Suppression des pings plus anciens que `PING_RETENTION_DAYS` jours |

## Écrans

- **Connexion** : adresse e-mail et mot de passe, cinq tentatives par quart d'heure
- **Tableau de bord** : nombre de moniteurs par statut, liste triée par gravité, début de l'incident en cours
- **Moniteur** : configuration, courbes de durée et de taille des 50 derniers succès, 20 derniers incidents, 50 derniers pings
- **Création et modification** : nom, type (non modifiable ensuite), cible, intervalle et délai de grâce en minutes, canal d'alerte
- **Actions** : mise en pause et reprise, régénération du jeton, suppression avec l'historique

## API

Lecture seule, authentification par en-tête `Authorization: Bearer <jeton>`. Dates au format ISO 8601 en UTC.

| Route | Contenu |
|---|---|
| `GET /api/monitors` | Moniteurs avec statut et incident en cours |
| `GET /api/monitors/{id}` | Moniteur, 20 derniers incidents, 50 derniers pings |

## Commandes

| Commande | Rôle |
|---|---|
| `app:user:create <email>` | Crée un compte, mot de passe saisi sans écho, douze caractères minimum |
| `app:monitor:create <nom> --interval=3600 --grace=600` | Crée une tâche planifiée (durées en secondes) et affiche son jeton |
| `app:api-token:create <email> <nom>` | Crée un jeton d'API et l'affiche une seule fois |
