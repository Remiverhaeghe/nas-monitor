# Changelog

## Non publié

### Résultats anormaux
- Alerte quand une sauvegarde réussit avec une taille en forte baisse ou une durée anormalement longue
- Anomalie affichée dans l'historique des pings et exposée par l'API

### Contrôles actifs et notifications
- Moniteurs de type site web (HTTP), port TCP et certificat TLS, contrôlés par l'application
- Notifications ntfy sur le téléphone, canal choisi par moniteur
- Dates des alertes à l'heure de Paris
- Courbes de durée et de taille sur la page d'un moniteur

### API
- API en lecture seule à jeton pour l'application mobile, commande de création de jeton

### Fiabilité
- Verrouillage optimiste sur les moniteurs, ping rejoué en cas de conflit
- Signal toutes les 5 minutes vers un service extérieur, qui alerte si le NAS tombe
- Suppression quotidienne des pings de plus de 90 jours

### Sécurité
- Politique CSP avec nonce et HSTS en production
- Images Docker fixées par empreinte, suivies par Dependabot
- Proxys de confiance configurables
- Contrôle quotidien déclenché par le dépôt central de veille au lieu d'un planning propre

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
