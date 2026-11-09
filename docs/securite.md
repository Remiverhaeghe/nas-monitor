# Sécurité

## Jetons de ping et d'API

- [x] Jetons aléatoires de 256 bits (`random_bytes`)
- [x] Seule l'empreinte SHA-256 est stockée, avec un index unique
- [x] Jeton affiché une seule fois, à la création ou à la régénération
- [x] Jeton de ping régénérable, l'ancien cesse immédiatement de fonctionner
- [x] Jetons jamais écrits dans les journaux

## Endpoint de ping

- [x] Méthode `POST` uniquement
- [x] Limite de 60 requêtes par minute et par adresse IP, vérifiée avant la recherche du jeton
- [x] Corps JSON validé, valeurs négatives refusées, message tronqué à 500 caractères, 20 métriques numériques au plus aux noms contrôlés
- [x] Pare-feu sans session

## API

- [x] Lecture seule
- [x] Pare-feu sans session, jeton `Bearer` vérifié à chaque appel
- [x] Jeton rattaché à un utilisateur, supprimé avec lui

## Interface web

- [x] Toutes les pages sauf `/login` et `/status` exigent une connexion
- [x] Page de statut publique limitée aux moniteurs marqués visibles : ni cible, ni message, ni historique
- [x] Mots de passe hachés par l'algorithme `auto` de Symfony, rehachés à la connexion si besoin
- [x] Cinq tentatives de connexion par quart d'heure
- [x] Double authentification par code TOTP (scheb/2fa), activée par compte, avec confirmation d'un code avant activation
- [x] Secret TOTP chiffré en base (libsodium secretbox), clé `TOTP_ENCRYPTION_KEY` fournie par l'environnement ; un secret illisible bloque la connexion au lieu de sauter le second facteur
- [x] Dix codes de secours de 64 bits, à usage unique, stockés sous forme d'empreinte SHA-256
- [x] Rotation de la clé de chiffrement par `app:totp:rotate-key`, en une transaction
- [x] Jeton CSRF sur la connexion, la déconnexion et chaque action (pause, reprise, régénération, suppression)
- [x] Actions de modification en `POST` uniquement
- [x] Échappement automatique des sorties par Twig
- [x] Pas d'inscription publique : les comptes se créent en ligne de commande
- [x] Journal des connexions, des échecs de connexion et des modifications, avec adresse IP

## Mise à jour en direct (Mercure)

- [x] Mises à jour privées : seul un navigateur connecté reçoit un cookie d'abonnement signé
- [x] Abonnement anonyme refusé et API de liste des abonnés désactivée dans le hub (le mode `playground` du développement les autorise)
- [x] Audience des jetons fixée sur l'URL publique du hub (`resource_identifier`)
- [x] Aucune donnée dans les messages : un simple signal de rafraîchissement
- [x] `CADDY_MERCURE_JWT_SECRET` obligatoire en production : Compose refuse de démarrer sans lui

## En-têtes HTTP (production)

- [x] `Content-Security-Policy` avec nonce par requête : aucun script ni style inline sans nonce, aucune ressource externe
- [x] `Strict-Transport-Security` en HTTPS sur un vrai nom de domaine (jamais sur `localhost`)
- [x] `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` ajoutés par Caddy, en-tête `Server` retiré

## Configuration

- [x] Secrets et valeurs locales dans `.env.local`, non versionné
- [x] Version de PHP masquée (`expose_php = 0`)
- [x] `session.upload_progress.enabled = 0`, suite à l'avis de sécurité PHP du 23/10/2026
- [x] Proxys de confiance explicites (`TRUSTED_PROXIES`) : sans eux, l'en-tête `X-Forwarded-For` est ignoré et ne permet pas de contourner la limite par IP
- [x] `APP_SECRET`, `TOTP_ENCRYPTION_KEY`, `POSTGRES_PASSWORD` et `CADDY_MERCURE_JWT_SECRET` obligatoires en production, à fournir dans `.env.nas`, non versionné, avec `--env-file .env.nas`
- [ ] Accès depuis l'extérieur par VPN uniquement

## Conteneurs (production)

- [x] Application et worker sous le compte `www-data`, sans droit système (`cap_drop: ALL`), sauf l'écoute sur les ports 80 et 443
- [x] Base de données limitée aux droits nécessaires à son démarrage
- [x] Service de sauvegarde sans droit système, système de fichiers en lecture seule
- [x] Pas d'élévation de privilèges (`no-new-privileges`) sur tous les services
- [x] Base de données non publiée sur un port
- [ ] Système de fichiers en lecture seule pour l'application et le worker

## Chaîne d'approvisionnement

- [x] Images Docker fixées par empreinte `@sha256`
- [x] Dependabot chaque semaine (Composer, Docker, Docker Compose, GitHub Actions), avec un délai de 7 jours après publication
- [x] `composer.lock` versionné

## Contrôles automatiques

La CI tourne à chaque push, et chaque jour à 4 h UTC à la demande du dépôt `veille-projets`, qui regroupe les résultats de tous les projets dans une seule issue :

- [x] Version de PHP comparée à la dernière version corrective publiée sur php.net, échec si elle est en retard
- [x] `composer audit` sur les dépendances
- [x] Gitleaks sur les secrets du dépôt
- [x] Alertes de sécurité Dependabot relevées dans le rapport de veille
