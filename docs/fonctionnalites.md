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

Une panne toujours en cours est rappelée toutes les `ALERT_REMINDER_HOURS` heures (6 par défaut, 0 pour désactiver) par une alerte « Toujours en panne ».

## Maintenances

Une plage de maintenance vise un moniteur ou tous. Pendant la plage, statuts et incidents sont enregistrés normalement, mais aucune alerte n'est envoyée (panne, rétablissement, anomalie, rappel). Une panne encore en cours à la fin de la plage est signalée dans la minute. Une plage dure 30 jours au plus ; le tableau de bord signale les plages en cours.

## Groupes et disponibilité

Un moniteur peut appartenir à un groupe (Sauvegardes, Services…). Le tableau de bord affiche les groupes par ordre alphabétique, les moniteurs sans groupe en dernier.

La disponibilité est la part du temps passée hors incident. Elle est calculée sur 7 jours, ou depuis la création du moniteur s'il est plus récent. Les pannes pendant une maintenance comptent ; une mise en pause ferme l'incident et ne compte pas.

Chaque lundi à 6 h UTC, un rapport part à `ALERT_EMAIL_TO` : disponibilité, nombre d'incidents et d'anomalies de la semaine, et statut actuel de chaque moniteur. Il est envoyé même pendant une maintenance.

## Mise à jour en direct

Le tableau de bord et la page d'un moniteur se mettent à jour d'eux-mêmes à chaque ping, changement de statut, incident ou maintenance. Le serveur ne diffuse qu'un signal de rafraîchissement, sans donnée ; la page se recharge avec les droits de l'utilisateur connecté. Le jeton affiché après une création ou une régénération reste à l'écran.

## Résultats anormaux

Pour une tâche planifiée, chaque succès est comparé à la médiane des 10 succès précédents (5 au minimum pour juger) :

- taille en baisse de plus de `ANOMALY_SIZE_DROP_PERCENT` % (50 par défaut)
- durée supérieure à `ANOMALY_DURATION_FACTOR` fois la médiane (3 par défaut)

Le ping reste un succès et le statut ne change pas, mais l'anomalie est enregistrée, affichée dans l'historique et signalée par une alerte « Anomalie ». Une seule alerte est envoyée par série d'exécutions anormales consécutives.

## Tâches planifiées internes

| Fréquence | Tâche |
|---|---|
| Chaque minute | Contrôles actifs dus, vérification des délais, puis rappels des pannes en cours |
| Toutes les 5 minutes | Signal vers un service extérieur (`HEARTBEAT_URL`), `/fail` si la base est injoignable |
| Chaque jour à 3 h 30 UTC | Suppression des pings plus anciens que `PING_RETENTION_DAYS` jours et des entrées du journal plus anciennes que `AUDIT_RETENTION_DAYS` jours |
| Chaque lundi à 6 h UTC | Rapport hebdomadaire par e-mail |

## Écrans

- **Connexion** : adresse e-mail et mot de passe, cinq tentatives par quart d'heure, puis code TOTP si la double authentification est activée
- **Maintenances** : plages en cours et à venir, planification et suppression
- **Journal** : 200 dernières actions avec date, auteur, détail et adresse IP
- **Tableau de bord** : nombre de moniteurs par statut, moniteurs par groupe triés par gravité, début de l'incident en cours, disponibilité sur 7 jours
- **Moniteur** : configuration, courbes de durée et de taille des 50 derniers succès, 20 derniers incidents, 50 derniers pings
- **Création et modification** : nom, type (non modifiable ensuite), cible, groupe, intervalle et délai de grâce en minutes, canal d'alerte
- **Actions** : mise en pause et reprise, régénération du jeton, suppression avec l'historique

## API

Lecture seule, authentification par en-tête `Authorization: Bearer <jeton>`. Dates au format ISO 8601 en UTC.

| Route | Contenu |
|---|---|
| `GET /api/monitors` | Moniteurs avec groupe, statut et incident en cours |
| `GET /api/monitors/{id}` | Moniteur, 20 derniers incidents, 50 derniers pings |

## Commandes

| Commande | Rôle |
|---|---|
| `app:user:create <email>` | Crée un compte, mot de passe saisi sans écho, douze caractères minimum |
| `app:monitor:create <nom> --interval=3600 --grace=600` | Crée une tâche planifiée (durées en secondes) et affiche son jeton |
| `app:api-token:create <email> <nom>` | Crée un jeton d'API et l'affiche une seule fois |
| `app:user:totp-enable <email>` | Active la double authentification : affiche la clé à saisir dans l'application, puis demande un code pour confirmer |
| `app:user:totp-disable <email>` | Désactive la double authentification, par exemple après la perte du téléphone |
| `app:report:send` | Envoie tout de suite le rapport des 7 derniers jours |

## Journal des actions

Sont tracés : connexions et échecs de connexion, création, modification, pause, reprise, régénération du jeton et suppression d'un moniteur, planification et suppression d'une maintenance, et en ligne de commande la création d'un compte ou d'un jeton d'API et l'activation ou la désactivation de la double authentification. L'auteur est conservé en texte, l'entrée survit donc à la suppression du compte.
