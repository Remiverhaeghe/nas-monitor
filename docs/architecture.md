# Architecture

## Conteneurs

| Service | Rôle |
|---|---|
| `php` | FrankenPHP (serveur web et PHP) |
| `database` | PostgreSQL 16 |
| `mailer` | Mailpit, développement et simulation |
| `worker` | Scheduler, contrôles actifs et envoi des notifications, production uniquement (`compose.prod.yaml`) |

En développement, le worker se lance à la main dans le conteneur `php`. En production, FrankenPHP fonctionne en mode worker : l'application reste chargée entre les requêtes, les services ne gardent donc aucun état propre à une requête.

Les images sont fixées par empreinte (`@sha256:`) ; Dependabot propose leurs mises à jour.

## Arborescence

```
src/
  Chart/            TrendChart, TrendChartFactory
  Check/            ActiveChecker, ActiveCheckRunner, CheckResult
  Command/          CreateApiTokenCommand, CreateMonitorCommand, CreateUserCommand
  Controller/       DashboardController, MonitorController, PingController, SecurityController
  Controller/Api/   MonitorApiController
  DataFixtures/     AppFixtures
  Dto/              MonitorInput, PingPayload
  Entity/           ApiToken, Incident, Monitor, Ping, User
  Enum/             IncidentCause, MonitorStatus, MonitorType, NotificationChannel, PingKind
  EventSubscriber/  SecurityHeadersSubscriber
  Form/             MonitorFormType
  Notification/     AlertNotifier, NtfyMessage, NtfyMessageHandler
  Repository/       un dépôt par entité
  Scheduler/        messages et gestionnaires des tâches planifiées
  Security/         ApiTokenHandler
  Service/          IncidentManager, MonitorChecker, PingRecorder
  Schedule.php      planning du Scheduler
templates/          Twig : base, dashboard, monitor, security
assets/             feuille de style et contrôleur Stimulus de confirmation
migrations/         migrations Doctrine
simulation/         tâches de la simulation locale
tests/
  Chart/            TrendChartTest
  Check/            ActiveCheckerTest
  Controller/       DashboardControllerTest, MonitorControllerTest, PingControllerTest
  Controller/Api/   MonitorApiControllerTest
  Entity/           MonitorTest
  EventSubscriber/  SecurityHeadersSubscriberTest
  Notification/     NtfyMessageHandlerTest
  Scheduler/        ExternalHeartbeatHandlerTest
  Service/          MaintenanceTest, MonitorCheckerTest
```

## Règles métier

Le calcul du statut est dans l'entité `Monitor` (`evaluateStatus`, `refreshStatus`, `recordPing`, `isCheckDue`). L'heure est passée en paramètre, ce qui rend ces règles testables sans horloge.

Les contrôles actifs produisent des pings comme les tâches planifiées : un seul jeu de règles de statut et d'incident pour tous les types.

`IncidentManager` est le seul point qui ouvre ou ferme un incident. Il ne fait pas de `flush` : l'appelant enregistre le moniteur et l'incident dans la même transaction.

`Monitor` porte un numéro de version (verrouillage optimiste) : si un ping et la vérification planifiée modifient le même moniteur en même temps, la seconde écriture échoue au lieu d'écraser la première. Le ping répond alors `503` avec `Retry-After`, que `curl --retry` rejoue ; côté worker, l'erreur est journalisée et la minute suivante reprend.

## Flux principaux

**Réception d'un ping**

1. `PingController` vérifie la limite de débit, puis retrouve le moniteur par l'empreinte du jeton
2. `PingRecorder` crée le `Ping` et met à jour le moniteur
3. En cas de changement de statut, `IncidentManager` ouvre ou ferme l'incident
4. Un seul `flush` enregistre l'ensemble

**Chaque minute**

1. Le Scheduler envoie `CheckMonitorsMessage`
2. `ActiveCheckRunner` effectue les contrôles HTTP, TCP et TLS arrivés à échéance et les enregistre par `PingRecorder`
3. `MonitorChecker` recalcule le statut des moniteurs opérationnels ou en retard
4. Chaque changement passe par `IncidentManager`

**Alertes**

`AlertNotifier` choisit le canal du moniteur. E-mails et messages ntfy partent par Messenger (transport `async`) : un serveur lent ne bloque pas la vérification. Les échecs d'envoi sont réessayés trois fois, puis placés dans le transport `failed`.

## Données

| Table | Contenu |
|---|---|
| `monitor` | Configuration, état courant et version |
| `ping` | Signaux reçus et résultats de contrôles, index `(monitor_id, received_at)` et `(received_at)` |
| `incident` | Pannes, index `(monitor_id, resolved_at)` |
| `app_user` | Comptes |
| `api_token` | Jetons d'API (empreinte SHA-256, dernière utilisation) |
| `messenger_messages` | Files Messenger |

Les dates sont stockées en UTC. La suppression d'un moniteur supprime ses pings et incidents (`ON DELETE CASCADE`).
