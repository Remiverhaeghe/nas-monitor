# Architecture

## Conteneurs

| Service | Rôle |
|---|---|
| `php` | FrankenPHP (serveur web et PHP) |
| `database` | PostgreSQL 16 |
| `mailer` | Mailpit, développement et simulation |
| `worker` | Scheduler, contrôles actifs, envoi des notifications et des mises à jour Mercure, production uniquement (`compose.prod.yaml`) |
| `db-backup` | `pg_dump` périodique de la base, production uniquement (voir [sauvegarde.md](sauvegarde.md)) |

En développement, le worker se lance à la main dans le conteneur `php`. En production, FrankenPHP fonctionne en mode worker : l'application reste chargée entre les requêtes, les services ne gardent donc aucun état propre à une requête.

Le hub Mercure est intégré à Caddy dans le conteneur `php`. Le worker y publie par `http://php/.well-known/mercure`.

Le contrôle de santé du worker vérifie que `var/worker-heartbeat` a été touché depuis moins de 3 minutes (`WorkerHeartbeatListener`, à chaque tour de boucle). Docker Compose ne redémarre pas un conteneur défaillant : l'état apparaît dans `docker ps` et l'interface Docker du NAS, le worker est relancé chaque heure par `--time-limit`, et le signal externe (`HEARTBEAT_URL`), envoyé par ce même worker, cesse et déclenche l'alerte.

Les images sont fixées par empreinte (`@sha256:`) ; Dependabot propose leurs mises à jour.

## Arborescence

```
src/
  Audit/            AuditLogger
  Chart/            TrendChart, TrendChartFactory
  Check/            ActiveChecker, ActiveCheckRunner, CheckResult
  Command/          CreateApiTokenCommand, CreateMonitorCommand, CreateUserCommand,
                    DisableTotpCommand, EnableTotpCommand, RegenerateBackupCodesCommand,
                    SendWeeklyReportCommand
  Controller/       AuditController, DashboardController, MaintenanceController,
                    MonitorController, PingController, SecurityController
  Controller/Api/   MonitorApiController
  DataFixtures/     AppFixtures
  Dto/              MaintenanceInput, MonitorInput, PingPayload
  Entity/           ApiToken, AuditLog, Incident, MaintenanceWindow, Monitor, Ping, User
  Enum/             AuditAction, IncidentCause, MaintenanceRecurrence, MonitorStatus, MonitorType,
                    NotificationChannel, PingKind
  EventListener/    LiveUpdatePublisher, UserTotpSecretListener, WorkerHeartbeatListener
  EventSubscriber/  LoginAuditSubscriber, SecurityHeadersSubscriber
  Form/             MaintenanceFormType, MonitorFormType
  Notification/     AlertNotifier, NtfyMessage, NtfyMessageHandler
  Report/           WeeklyReportSender
  Repository/       un dépôt par entité
  Scheduler/        messages et gestionnaires des tâches planifiées
  Security/         ApiTokenHandler, BackupCodeGenerator, SecretCipher
  Service/          AnomalyDetector, AvailabilityCalculator, IncidentManager, IncidentReminder,
                    MonitorChecker, PingRecorder
  Schedule.php      planning du Scheduler
templates/          Twig : base, audit, dashboard, maintenance, monitor, security
assets/             feuille de style et contrôleur Stimulus de confirmation
docker/db-backup/   script de sauvegarde de la base
migrations/         migrations Doctrine
simulation/         tâches de la simulation locale
tests/
  Chart/            TrendChartTest
  Check/            ActiveCheckerTest
  Controller/       AuditControllerTest, DashboardControllerTest, MaintenanceControllerTest,
                    MonitorControllerTest, PingControllerTest
  Controller/Api/   MonitorApiControllerTest
  Entity/           MaintenanceWindowTest, MonitorTest
  EventListener/    LiveUpdatePublisherTest, WorkerHeartbeatListenerTest
  EventSubscriber/  SecurityHeadersSubscriberTest
  Notification/     NtfyMessageHandlerTest
  Scheduler/        ExternalHeartbeatHandlerTest
  Security/         SecretCipherTest
  Service/          AnomalyDetectorTest, MaintenanceTest, MonitorCheckerTest, ReportingTest
```

## Règles métier

Le calcul du statut est dans l'entité `Monitor` (`evaluateStatus`, `refreshStatus`, `recordPing`, `isCheckDue`). L'heure est passée en paramètre, ce qui rend ces règles testables sans horloge.

Les contrôles actifs produisent des pings comme les tâches planifiées : un seul jeu de règles de statut et d'incident pour tous les types.

`IncidentManager` est le seul point qui ouvre ou ferme un incident. Il ne fait pas de `flush` : l'appelant enregistre le moniteur et l'incident dans la même transaction.

`Monitor` porte un numéro de version (verrouillage optimiste) : si un ping et la vérification planifiée modifient le même moniteur en même temps, la seconde écriture échoue au lieu d'écraser la première. Le ping répond alors `503` avec `Retry-After`, que `curl --retry` rejoue ; côté worker, l'erreur est journalisée et la minute suivante reprend.

## Flux principaux

**Réception d'un ping**

1. `PingController` vérifie la limite de débit, puis retrouve le moniteur par l'empreinte du jeton
2. `PingRecorder` crée le `Ping`, le compare aux succès précédents (`AnomalyDetector`) et met à jour le moniteur
3. En cas de changement de statut, `IncidentManager` ouvre ou ferme l'incident
4. Un seul `flush` enregistre l'ensemble

**Chaque minute**

1. Le Scheduler envoie `CheckMonitorsMessage`
2. `ActiveCheckRunner` effectue les contrôles HTTP, TCP et TLS arrivés à échéance et les enregistre par `PingRecorder`
3. `MonitorChecker` recalcule le statut des moniteurs opérationnels ou en retard
4. Chaque changement passe par `IncidentManager`
5. `IncidentReminder` signale les pannes qui durent et celles retenues pendant une maintenance

**Mise à jour en direct**

`LiveUpdatePublisher` repère, à chaque `flush`, un changement sur un moniteur, un ping, un incident ou une maintenance. Il envoie alors une seule mise à jour Mercure privée par Messenger, pour ne pas ralentir la réception d'un ping. Les pages abonnées (`turbo_stream_from`) la reçoivent et se rechargent par morphing Turbo, en gardant la position de défilement.

**Secret TOTP**

Le secret est chiffré par `SecretCipher` avant d'être enregistré. `UserTotpSecretListener` le déchiffre au chargement d'un compte et le garde en mémoire seulement. La migration qui a introduit le chiffrement chiffre aussi les secrets existants.

**Maintenances récurrentes**

`MaintenanceWindow::findOccurrenceStart` calcule l'occurrence en cours ou la prochaine dans le fuseau d'affichage. Le dépôt présélectionne les plages en SQL, puis filtre les plages récurrentes en PHP.

**Journal des actions**

`AuditLogger` enregistre l'action, l'auteur (utilisateur connecté, `console` ou `anonyme`) et l'adresse IP. Il est appelé par les contrôleurs, les commandes et `LoginAuditSubscriber`. Un échec d'écriture est journalisé sans interrompre l'action.

**Alertes**

`AlertNotifier` retient toute alerte pendant une maintenance, puis choisit le canal du moniteur. `Incident::lastNotifiedAt` reste vide tant qu'aucune alerte n'est partie, ce qui permet à `IncidentReminder` de la rattraper. E-mails et messages ntfy partent par Messenger (transport `async`) : un serveur lent ne bloque pas la vérification. Les échecs d'envoi sont réessayés trois fois, puis placés dans le transport `failed`.

## Données

| Table | Contenu |
|---|---|
| `monitor` | Configuration, groupe, état courant et version |
| `ping` | Signaux reçus et résultats de contrôles, index `(monitor_id, received_at)` et `(received_at)` |
| `incident` | Pannes et date de dernière alerte, index `(monitor_id, resolved_at)` |
| `maintenance_window` | Plages de maintenance et répétition, index `(starts_at, ends_at)` |
| `app_user` | Comptes, secret TOTP chiffré, empreintes des codes de secours |
| `api_token` | Jetons d'API (empreinte SHA-256, dernière utilisation) |
| `audit_log` | Journal des actions, index `(occurred_at)` |
| `messenger_messages` | Files Messenger |

Les dates sont stockées en UTC. La suppression d'un moniteur supprime ses pings et incidents (`ON DELETE CASCADE`).
