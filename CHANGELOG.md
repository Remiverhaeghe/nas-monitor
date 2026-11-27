# Changelog

## Non publié

### Dépendances
- Correctifs de sécurité : Symfony 8.1.12 (framework-bundle, http-kernel, routing, mailer, serializer, sécurité, expression-language, doctrine-bridge), Twig 3.31.1 et otphp 11.5.1, qui faisaient échouer `composer audit` en CI

### Vérification de restauration
- Comparaison du nombre de lignes de chaque table restaurée avec le relevé pris avant le dump (`row-counts`, fichier `.counts`), tolérance de 10 %
- `docker/db-backup/check-restore` restaure le dernier dump dans une base temporaire, compare le nombre de tables avec la base réelle et supprime la base temporaire
- `db-backup` le lance tous les `DB_RESTORE_CHECK_EVERY` dumps (7 par défaut) et signale le résultat à un moniteur dédié (`DB_RESTORE_PING_URL`)
- Test du script en CI sur une base jetable : cas nominal, table ajoutée, dump illisible, aucun dump

### Documentation
- Surveillance des versions web d'Atelier et de Tremplin sur GitHub Pages : moniteurs HTTP à créer, réglages et limites (`docs/nas.md`)

### PHP 8.5.13
- Image FrankenPHP passée à PHP 8.5.13 (nouvelle empreinte dans le `Dockerfile`)
- Le contrôle de version de la CI laisse une semaine à l'image FrankenPHP pour être reconstruite après une version de PHP ; il échoue tout de suite si l'image à jour existe déjà, et donne alors l'empreinte à mettre dans le `Dockerfile`
- Dependabot propose chaque jour, sans délai d'attente, la mise à jour de l'image FrankenPHP

### Santé des disques (SMART)
- Le script de relevé du NAS joint la sortie de `smartctl -H -A` de chaque disque (SATA, NVMe, SAS), sans réveiller un disque en veille
- nas-monitor en tire la température la plus haute des disques, les secteurs réalloués, en attente et illisibles, les erreurs et l'usure NVMe, et le nombre de disques en échec
- Alerte « Anomalie » avant panne, qui nomme le disque concerné ; seuils indicatifs par défaut (50 °C, aucun secteur défectueux, usure 90 %), remplacés par ceux du moniteur
- Tests sur des sorties smartctl fictives

### Page d'état publique
- Les noms de services et de groupes y sont affichés sans adresse IP, nom d'hôte, port, chemin ni numéro de version
- Le gabarit ne reçoit plus les moniteurs eux-mêmes, seulement le nom masqué, le statut et la disponibilité
- Colonnes resserrées et noms sur plusieurs lignes sur téléphone


### Tests
- Tests de mutation : valeurs exactes pour les courbes, les contrôles actifs, le détecteur d'anomalies et les plages de maintenance (indice 81 % → 86 %), délai par mutant porté à 10 s
- Tests des journaux, de l'ordre de traitement et des alertes (PingRecorder, MonitorChecker, ActiveCheckRunner, IncidentReminder, jetons d'API) avec un logger de test
- Contrôle ping : fonctions socket isolées derrière `DatagramSocket` (testables sans réseau, vrais sockets UDP en test) ; options TLS exposées pour être vérifiées
- Contrôle DNS : résolution isolée derrière `DnsResolver`
- Correction : socket ICMP créé et jamais fermé dans le contrôle ping
- Script `bin/prepare-parallel-test-dbs.sh` pour lancer Infection sur plusieurs threads
- Couverture des lignes portée de 79 % à plus de 99 %, seuil de la CI relevé à 99 %
- Commandes console, gestionnaires du planificateur, validateurs, dépôts et fixtures testés
- Contrôles ICMP et TLS isolés derrière deux interfaces (`PingTransport`, `TlsConnector`) pour simuler les pannes du système
- Correction d'un test qui dépendait de la date du jour

### Documentation
- Modèle `.env.example` des secrets de production et `CLAUDE.md`

### Durcissement de la production
- Secrets obligatoires : Compose refuse de démarrer sur une valeur manquante
- Conteneurs sans droits système superflus, sans élévation de privilèges
- Service de sauvegarde en lecture seule
- Recherche de secrets par Gitleaks en CI

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
