# nas-monitor

Application Symfony 8 / PostgreSQL / FrankenPHP qui supervise les tâches et services d'un NAS. Suivre les conventions de Rémi (skill `conventions-remi`). Toujours répondre en français.

- Tout se lance dans Docker : `docker compose exec php bin/phpunit`, `vendor/bin/phpstan`, `vendor/bin/php-cs-fixer`, `vendor/bin/infection`.
- La CI exige une couverture minimale et un score de mutation (Infection). Un correctif de bug s'accompagne d'un test.
- Secrets de production dans `.env.nas` (ignoré par git, modèle dans `.env.example`). Le `.env` suivi ne contient que des valeurs par défaut sans valeur réelle.
- Sécurité et déploiement : voir `docs/securite.md`, `docs/sauvegarde.md`, `docs/simulation.md`.
- `README.md`, `CHANGELOG.md` et `docs/` à jour après chaque évolution.
