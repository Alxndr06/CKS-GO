# Sécurité

## Versions suivies

La branche `main` est la seule version maintenue. Les correctifs de sécurité ne sont pas rétroportés vers d'anciennes révisions.

## Signaler une vulnérabilité

N'ouvrez pas d'issue publique avec une preuve de concept, des identifiants ou des données personnelles. Utilisez le canal privé **Security > Report a vulnerability** du dépôt GitHub.

Indiquez la route concernée, les conditions de reproduction, l'impact estimé et, si possible, une proposition de correction. Aucune donnée réelle ne doit être utilisée pour les tests.

## Principes de déploiement

- `APP_ENV=prod` et `APP_DEBUG=false` en production ;
- HTTPS obligatoire, avec les cookies `Secure`, `HttpOnly` et `SameSite` ;
- compte SQL dédié avec les seuls droits nécessaires sur la base CKS-GO ;
- fichiers `config/local.php`, journaux, sauvegardes et exports SQL exclus du répertoire public ;
- dépendances installées avec `composer install --no-dev --classmap-authoritative`, puis contrôlées avec `composer audit` ;
- sauvegarde vérifiée avant chaque migration.

Le dépôt ne contient volontairement ni secret, ni base métier, ni données nominatives.
