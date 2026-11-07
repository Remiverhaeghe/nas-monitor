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
- [x] Corps JSON validé, valeurs négatives refusées, message tronqué à 500 caractères
- [x] Pare-feu sans session

## API

- [x] Lecture seule
- [x] Pare-feu sans session, jeton `Bearer` vérifié à chaque appel
- [x] Jeton rattaché à un utilisateur, supprimé avec lui

## Interface web

- [x] Toutes les pages sauf `/login` exigent une connexion
- [x] Mots de passe hachés par l'algorithme `auto` de Symfony, rehachés à la connexion si besoin
- [x] Cinq tentatives de connexion par quart d'heure
- [x] Jeton CSRF sur la connexion, la déconnexion et chaque action (pause, reprise, régénération, suppression)
- [x] Actions de modification en `POST` uniquement
- [x] Échappement automatique des sorties par Twig
- [x] Pas d'inscription publique : les comptes se créent en ligne de commande

## En-têtes HTTP (production)

- [x] `Content-Security-Policy` avec nonce par requête : aucun script ni style inline sans nonce, aucune ressource externe
- [x] `Strict-Transport-Security` en HTTPS sur un vrai nom de domaine (jamais sur `localhost`)
- [x] `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` ajoutés par Caddy, en-tête `Server` retiré

## Configuration

- [x] Secrets et valeurs locales dans `.env.local`, non versionné
- [x] Version de PHP masquée (`expose_php = 0`)
- [x] `session.upload_progress.enabled = 0`, suite à l'avis de sécurité PHP du 23/10/2026
- [x] Proxys de confiance explicites (`TRUSTED_PROXIES`) : sans eux, l'en-tête `X-Forwarded-For` est ignoré et ne permet pas de contourner la limite par IP
- [ ] Accès depuis l'extérieur par VPN uniquement

## Chaîne d'approvisionnement

- [x] Images Docker fixées par empreinte `@sha256`
- [x] Dependabot chaque semaine (Composer, Docker, Docker Compose, GitHub Actions), avec un délai de 7 jours après publication
- [x] `composer.lock` versionné

## Contrôles automatiques

La CI tourne à chaque push, et chaque jour à 4 h UTC à la demande du dépôt `veille-projets`, qui regroupe les résultats de tous les projets dans une seule issue :

- [x] Version de PHP comparée à la dernière version corrective publiée sur php.net, échec si elle est en retard
- [x] `composer audit` sur les dépendances
- [x] Alertes de sécurité Dependabot relevées dans le rapport de veille
