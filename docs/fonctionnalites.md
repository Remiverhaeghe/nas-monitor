# Fonctionnalités

## Moniteur

Un moniteur représente une tâche planifiée (sauvegarde, synchronisation, script). Il est défini par :

- un nom
- un intervalle attendu entre deux pings de succès
- un délai de grâce toléré avant l'alerte
- un jeton de ping, dont seule l'empreinte SHA-256 est conservée

## Statuts

| Statut | Condition |
|---|---|
| Nouveau | Aucun ping reçu depuis la création ou la reprise |
| Opérationnel | Dernier ping de succès dans l'intervalle attendu |
| En retard | Intervalle dépassé, délai de grâce en cours |
| En panne | Délai de grâce dépassé, ou dernier ping en échec |
| En pause | Surveillance suspendue |

Un ping `start` est enregistré sans modifier le statut.

## Incidents et alertes

- Le passage en panne ouvre un incident et envoie un e-mail « En panne »
- Le retour à l'état opérationnel ferme l'incident et envoie un e-mail « Rétabli » avec la durée de la panne
- Un moniteur reste dans un seul incident tant qu'il est en panne : une seule alerte est envoyée
- La mise en pause ferme l'incident en cours sans e-mail
- L'état « En retard » n'envoie pas d'alerte

La vérification des délais est lancée chaque minute par le Scheduler.

## Écrans

- **Connexion** : adresse e-mail et mot de passe, cinq tentatives par quart d'heure
- **Tableau de bord** : nombre de moniteurs par statut, liste triée par gravité, début de l'incident en cours
- **Moniteur** : configuration, vingt derniers incidents, cinquante derniers pings (durée, taille, message, origine)
- **Création et modification** : nom, intervalle et délai de grâce en minutes
- **Actions** : mise en pause et reprise, régénération du jeton, suppression avec l'historique

Les dates sont affichées à l'heure de Paris.

## Commandes

| Commande | Rôle |
|---|---|
| `app:user:create <email>` | Crée un compte, mot de passe saisi sans écho, douze caractères minimum |
| `app:monitor:create <nom> --interval=3600 --grace=600` | Crée un moniteur (durées en secondes) et affiche son jeton |
