# Changelog

## Non publié

### Tests dans un navigateur
- Parcours de bout en bout avec Panther et Chromium, serveur hors debug pour que la CSP s'applique
- Contrôle des erreurs de la console et de la confirmation avant suppression
- Captures du README générées à partir des données de démonstration (`bin/phpunit --group screenshots`)

### Surveillance du NAS
- Script de relevé du NAS (disque, mémoire, charge, température, SMART) envoyé comme ping avec métriques
- Seuils par métrique, dépassement signalé comme anomalie, courbe par métrique
- Moniteurs de type ping ICMP et résolution DNS
- Dépendance entre moniteurs : les alertes d'un service attendent tant que son parent est en panne

### Page de statut publique
- Page `/status` sans connexion, limitée aux moniteurs marqués visibles

### Exploitation
- Commande `app:totp:rotate-key` pour changer la clé de chiffrement des secrets TOTP
- Tests de mutation limités aux lignes modifiées dans les pull requests

### Double authentification renforcée
- Secret TOTP chiffré en base (libsodium), clé `TOTP_ENCRYPTION_KEY` hors de la base
- Dix codes de secours à usage unique, affichés à l'activation, commande `app:user:backup-codes` pour les remplacer

### Maintenances récurrentes
- Plage répétée chaque jour ou chaque semaine, à la même heure locale y compris après un changement d'heure

### Qualité des tests
- Tests de mutation avec Infection sur le code métier, seuil vérifié en CI

### Suivi et confort
- Tableau de bord et page d'un moniteur mis à jour en direct (Mercure), sans recharger la page
- Groupes de moniteurs sur le tableau de bord, exposés par l'API
- Disponibilité sur 7 jours par moniteur
- Rapport hebdomadaire par e-mail le lundi matin, commande `app:report:send` pour l'envoyer à la demande

### Journal des actions
- Connexions, échecs de connexion, modifications des moniteurs et des maintenances, actions en ligne de commande
- Page Journal, entrées conservées un an

### Exploitation
- Contrôle de santé du worker en production
- Couverture de code mesurée en CI, minimum 80 %

### Sauvegarde de la base
- Service `db-backup` en production : `pg_dump` quotidien, 14 fichiers conservés, ping vers un moniteur dédié
- Procédure de restauration documentée

### Alertes
- Rappel toutes les 6 heures d'une panne toujours en cours
- Plages de maintenance par moniteur ou globales, sans alerte pendant la plage et rattrapage à la fin

### Sécurité
- Double authentification par code TOTP, activée par compte en ligne de commande

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
