# Surveillance du NAS

## Relevé de l'état du NAS

Le script [docker/nas-metrics/nas-metrics](../docker/nas-metrics/nas-metrics) relève l'état du NAS et l'envoie comme un ping de tâche planifiée, avec ses métriques :

| Métrique | Contenu |
|---|---|
| `disk_used_percent` | Occupation du volume (par défaut `/volume1`) |
| `memory_used_percent` | Mémoire utilisée |
| `load_1m` | Charge moyenne sur une minute |
| `temperature_c` | Température la plus haute relevée par le système |
| `smart_failed_disks` | Nombre de disques dont le test SMART échoue, si `smartctl` est présent |

Une métrique absente du système n'est pas envoyée.

## Mise en place

1. Créer un moniteur de type tâche planifiée, par exemple « Relevé du NAS », intervalle 10 minutes
2. Renseigner ses seuils, par exemple `disk_used_percent=85, temperature_c=60, smart_failed_disks=0`
3. Copier le script sur le NAS et l'appeler toutes les 10 minutes depuis le planificateur de tâches, en root pour lire l'état SMART :

```bash
NAS_METRICS_PING_URL=https://<hôte>/ping/<jeton> sh /volume1/scripts/nas-metrics /volume1
```

Un seuil dépassé est enregistré comme une anomalie et déclenche une alerte « Anomalie », une seule par série de relevés anormaux. Les courbes de chaque métrique apparaissent sur la page du moniteur.

## Dépendances

Un moniteur peut dépendre d'un autre, par exemple chaque service du NAS du moniteur « Ping ICMP » du NAS. Quand le NAS est en panne, ses services ouvrent leurs incidents normalement, mais leurs alertes attendent. Une seule alerte part donc, celle du NAS. Si un service est toujours en panne au retour du NAS, son alerte part dans la minute.

La dépendance n'a qu'un niveau : un moniteur dont d'autres dépendent ne peut pas dépendre lui-même d'un autre.

## Contrôles réseau

- **Ping ICMP** : requête d'écho vers un hôte ou une adresse IPv4, réponse attendue en 3 secondes. Le conteneur n'a pas besoin de droits root : Docker autorise par défaut les sockets ICMP non privilégiées
- **Résolution DNS** : vérifie qu'un nom résout, et optionnellement vers l'adresse attendue (`nas.exemple.fr=192.168.1.10`). La résolution passe par le DNS du conteneur ; les noms `.local` (mDNS) ne sont pas résolus
