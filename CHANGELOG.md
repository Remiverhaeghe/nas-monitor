# Changelog

## Non publié

### Simulation du NAS et production
- Service `worker` dans la configuration de production
- En-têtes de sécurité ajoutés par Caddy en production
- Simulation locale avec une sauvegarde restic et une synchronisation instable

### Sécurité PHP
- Contrôle quotidien en CI de la version de PHP et des dépendances
- `session.upload_progress` désactivé suite à l'avis de sécurité PHP du 23/10/2026

### Intégration continue
- Tests, PHPStan, PHP-CS-Fixer, vérification des gabarits, du YAML, du conteneur et du schéma à chaque push
- Audit des dépendances Composer

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
