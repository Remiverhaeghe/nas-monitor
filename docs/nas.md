# Surveillance du NAS

## Relevé de l'état du NAS

Le script [docker/nas-metrics/nas-metrics](../docker/nas-metrics/nas-metrics) relève l'état du NAS et l'envoie comme un ping de tâche planifiée, avec ses métriques :

| Métrique | Contenu |
|---|---|
| `disk_used_percent` | Occupation du volume (par défaut `/volume1`) |
| `memory_used_percent` | Mémoire utilisée |
| `load_1m` | Charge moyenne sur une minute |
| `temperature_c` | Température la plus haute relevée par le système |

Une métrique absente du système n'est pas envoyée.

## Santé des disques (SMART)

Si `smartctl` est présent, le script joint au relevé la sortie de `smartctl -n standby -H -A` pour chaque disque (`/dev/sd?`, `/dev/sata?`, `/dev/nvme?n1`), dans le champ `smart` : `{"sda": "<sortie texte>"}`. Un disque en veille n'est pas réveillé et ne donne aucune valeur. nas-monitor analyse ces sorties et en tire les métriques suivantes, pire valeur sur l'ensemble des disques :

| Métrique | Contenu | Seuil par défaut |
|---|---|---|
| `disk_temperature_max_c` | Température du disque le plus chaud (attribut 194, sinon 190 ; NVMe et SAS) | 50 |
| `smart_failed_disks` | Nombre de disques dont le bilan SMART échoue, ou NVMe en avertissement critique | 0 |
| `smart_reallocated_sectors` | Secteurs réalloués (attribut 5, liste des défauts SAS) | 0 |
| `smart_pending_sectors` | Secteurs en attente de réallocation (attribut 197) | 0 |
| `smart_uncorrectable_sectors` | Secteurs illisibles (attribut 198) | 0 |
| `smart_media_errors` | Erreurs de données NVMe | 0 |
| `smart_wear_percent` | Usure NVMe (`Percentage Used`) | 90 |

Ces seuils par défaut sont indicatifs, à adapter aux disques : la température conseillée varie selon le fabricant (à vérifier dans la fiche technique), et un disque peut vivre longtemps avec quelques secteurs réalloués qui n'augmentent plus. Un seuil saisi sur le moniteur (`disk_temperature_max_c=55, smart_reallocated_sectors=10`) remplace le seuil par défaut. Chaque dépassement est vérifié disque par disque et l'anomalie nomme le disque : `sdb : smart_pending_sectors à 3 (maximum 0)`. Un secteur en attente ou illisible annonce souvent une panne : c'est le moment de vérifier la sauvegarde et de prévoir le remplacement.

Quand le champ `smart` est présent, les métriques SMART envoyées par le script lui-même sont ignorées. Un ancien script qui n'envoie que `smart_failed_disks` reste pris en charge.

## Mise en place

1. Créer un moniteur de type tâche planifiée, par exemple « Relevé du NAS », intervalle 10 minutes
2. Renseigner ses seuils, par exemple `disk_used_percent=85, temperature_c=60` ; les seuils SMART ont des valeurs par défaut
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
