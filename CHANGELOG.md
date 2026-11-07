# Changelog

## Non publié

### Gestion des moniteurs depuis l'interface
- Création et modification d'un moniteur, avec affichage unique du jeton
- Mise en pause, qui ferme l'incident en cours sans alerte, et reprise
- Régénération du jeton et suppression, avec confirmation

### Tableau de bord et connexion
- Connexion par e-mail et mot de passe, comptes créés en ligne de commande
- Liste des moniteurs triée par gravité, page de détail avec incidents et pings

### Incidents et alertes
- Vérification des délais chaque minute
- Ouverture d'un incident et e-mail au passage en panne, e-mail de rétablissement

### Réception des pings
- Endpoint `POST /ping/<jeton>` avec variantes `start` et `fail`, limite de débit par adresse IP
- Commande de création de moniteur
