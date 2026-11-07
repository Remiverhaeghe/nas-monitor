# Architecture

## Conteneurs

| Service | Rôle |
|---|---|
| `php` | FrankenPHP (serveur web et PHP) |
| `database` | PostgreSQL 16 |
| `mailer` | Mailpit, développement et simulation |
| `worker` | Scheduler et envoi des e-mails, production uniquement (`compose.prod.yaml`) |

En développement, le worker se lance à la main dans le conteneur `php`. En production, FrankenPHP fonctionne en mode worker : l'application reste chargée entre les requêtes, les services ne gardent donc aucun état propre à une requête.

## Arborescence

```
src/
  Command/        CreateMonitorCommand, CreateUserCommand
  Controller/     DashboardController, MonitorController, PingController, SecurityController
  DataFixtures/   AppFixtures
  Dto/            MonitorInput, PingPayload
  Entity/         Incident, Monitor, Ping, User
  Enum/           IncidentCause, MonitorStatus, MonitorType, PingKind
  Form/           MonitorFormType
  Repository/     un dépôt par entité
  Scheduler/      CheckMonitorsMessage, CheckMonitorsHandler
  Service/        AlertMailer, IncidentManager, MonitorChecker, PingRecorder
  Schedule.php    planning du Scheduler
templates/        Twig : base, dashboard, monitor, security
assets/           feuille de style et contrôleur Stimulus de confirmation
migrations/       migrations Doctrine
tests/
  Controller/     DashboardControllerTest, MonitorControllerTest, PingControllerTest
  Entity/         MonitorTest
  Service/        MonitorCheckerTest
```

## Règles métier

Le calcul du statut est dans l'entité `Monitor` (`evaluateStatus`, `refreshStatus`, `recordPing`). L'heure est passée en paramètre, ce qui rend ces règles testables sans horloge.

`IncidentManager` est le seul point qui ouvre ou ferme un incident. Il ne fait pas de `flush` : l'appelant enregistre le moniteur et l'incident dans la même transaction.

## Flux principaux

**Réception d'un ping**

1. `PingController` vérifie la limite de débit, puis retrouve le moniteur par l'empreinte du jeton
2. `PingRecorder` crée le `Ping` et met à jour le moniteur
3. En cas de changement de statut, `IncidentManager` ouvre ou ferme l'incident
4. Un seul `flush` enregistre l'ensemble

**Vérification planifiée**

1. Le Scheduler envoie `CheckMonitorsMessage` chaque minute
2. `MonitorChecker` charge les moniteurs opérationnels ou en retard, les seuls dont le statut peut changer sans nouveau ping
3. Chaque changement passe par `IncidentManager`

**Alertes**

`AlertMailer` envoie les e-mails par Messenger (transport `async`), ce qui évite qu'un serveur SMTP lent bloque la vérification. Les échecs d'envoi sont réessayés trois fois, puis placés dans le transport `failed`.

## Données

| Table | Contenu |
|---|---|
| `monitor` | Configuration et état courant |
| `ping` | Signaux reçus, index `(monitor_id, received_at)` |
| `incident` | Pannes, index `(monitor_id, resolved_at)` |
| `app_user` | Comptes |
| `messenger_messages` | Files Messenger |

Les dates sont stockées en UTC. La suppression d'un moniteur supprime ses pings et incidents (`ON DELETE CASCADE`).
