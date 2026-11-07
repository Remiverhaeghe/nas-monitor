# Sécurité

## Jetons de ping

- [x] Jeton aléatoire de 256 bits (`random_bytes`)
- [x] Seule l'empreinte SHA-256 est stockée, avec un index unique
- [x] Jeton affiché une seule fois, à la création ou à la régénération
- [x] Régénération possible, l'ancien jeton cesse immédiatement de fonctionner
- [x] Jeton jamais écrit dans les journaux

## Endpoint de ping

- [x] Méthode `POST` uniquement
- [x] Limite de 60 requêtes par minute et par adresse IP, vérifiée avant la recherche du jeton
- [x] Corps JSON validé, valeurs négatives refusées, message tronqué à 500 caractères
- [x] Pare-feu sans session

## Interface web

- [x] Toutes les pages sauf `/login` exigent une connexion
- [x] Mots de passe hachés par l'algorithme `auto` de Symfony, rehachés à la connexion si besoin
- [x] Cinq tentatives de connexion par quart d'heure
- [x] Jeton CSRF sur la connexion, la déconnexion et chaque action (pause, reprise, régénération, suppression)
- [x] Actions de modification en `POST` uniquement
- [x] Échappement automatique des sorties par Twig
- [x] Pas d'inscription publique : les comptes se créent en ligne de commande

## Configuration

- [x] Secrets et valeurs locales dans `.env.local`, non versionné
- [ ] En-têtes de sécurité (CSP, HSTS)
- [ ] Accès depuis l'extérieur par VPN uniquement
