# CKS-GO

[![Qualité et sécurité](https://github.com/Alxndr06/CKS-GO/actions/workflows/ci.yml/badge.svg)](https://github.com/Alxndr06/CKS-GO/actions/workflows/ci.yml)

CKS-GO est une application PHP de boutique et de gestion de caisse interne. Elle réunit le catalogue à variantes, les commandes, l'encaissement, la facturation, l'inventaire et les fonctions d'administration dans une interface responsive.

## Fonctionnalités principales

- boutique publique ou authentifiée, recherche et catégories ;
- produits à plusieurs variantes, prix et stocks indépendants ;
- panier, création de commande et historique utilisateur ;
- paiements partiels, soldes, remboursements et factures ;
- gestion des utilisateurs et rôles `user`, `assistant`, `gestionnaire`, `responsable` et `admin` ;
- tickets de support, alertes produit, actualités et journal d'administration ;
- archivage du catalogue et suivi des mouvements de stock ;
- mode maintenance, verrouillage de la boutique et bannissements ciblés.

## Prérequis

- PHP **8.2 ou plus récent** avec `fileinfo`, `mbstring`, `PDO` et `pdo_mysql` ;
- MariaDB **10.4+** ou MySQL 8 compatible ;
- Composer 2 ;
- Apache avec `mod_rewrite` et `AllowOverride All`, ou une configuration Nginx équivalente ;
- HTTPS pour un déploiement en production.

Sous XAMPP, activez au minimum `extension=fileinfo`, `extension=mbstring` et `extension=pdo_mysql` dans le `php.ini` utilisé par Apache et par la ligne de commande.

## Installation propre

### 1. Récupérer le projet

```powershell
git clone https://github.com/Alxndr06/CKS-GO.git
Set-Location CKS-GO
composer install --no-dev --classmap-authoritative
```

Pour travailler sur le projet, utilisez simplement `composer install` afin de conserver les outils de développement qui pourraient être ajoutés ultérieurement.

### 2. Créer la base

Le fichier [`database/schema.sql`](database/schema.sql) contient uniquement la structure : aucune donnée métier ni aucun compte réel n'est publié.

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -p -e "CREATE DATABASE cksgo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
C:\xampp\mysql\bin\mysql.exe -u root -p cksgo -e "SOURCE database/schema.sql"
```

Sur un hébergement, créez de préférence un utilisateur SQL dédié à cette seule base. N'utilisez pas le compte `root` en production.

### 3. Configurer l'application

```powershell
Copy-Item config/local.example.php config/local.php
```

Modifiez ensuite `config/local.php` : connexion SQL, URL publique et paramètres SMTP. Ce fichier est ignoré par Git et ne doit jamais être envoyé sur le dépôt.

Réglages obligatoires en production :

```php
define('APP_ENV', 'prod');
define('APP_DEBUG', false);
define('APP_URL', 'https://votre-domaine.example');
```

N'activez `TRUST_PROXY_HEADERS` que derrière un proxy de confiance qui remplace systématiquement les en-têtes transmis par le client.

### 4. Créer le premier administrateur

Le mot de passe n'est pas passé dans les arguments du processus. Il est lu depuis l'environnement et doit respecter la politique de sécurité de l'application (15 caractères minimum).

```powershell
$env:CKSGO_ADMIN_USERNAME = 'admin'
$env:CKSGO_ADMIN_FIRSTNAME = 'Prénom'
$env:CKSGO_ADMIN_LASTNAME = 'Nom'
$env:CKSGO_ADMIN_EMAIL = 'admin@example.org'
$env:CKSGO_ADMIN_PASSWORD = 'Une phrase de passe longue et unique'
$env:CKSGO_ADMIN_UNIT = 'mineurs'

C:\xampp\php\php.exe scripts/create_admin.php
Remove-Item Env:CKSGO_ADMIN_PASSWORD
```

Les unités acceptées sont `mineurs`, `vif` et `syndicat`.

### 5. Configurer le serveur web

La racine du site doit pointer vers le dossier du projet. Le fichier `.htaccess` interdit l'accès direct aux contrôleurs, à la configuration, aux tests, aux sauvegardes et au schéma SQL ; seuls `index.php` et `public/` sont servis.

Pour un test local uniquement :

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8080 tests/dev_router.php
```

Ouvrez ensuite `http://127.0.0.1:8080`. Le routeur de développement n'est pas un serveur de production.

## Mise à niveau d'une ancienne base

Une installation neuve importe directement `database/schema.sql` et ne rejoue aucune migration.

Pour une base historique, consultez [`database/README.md`](database/README.md) et sauvegardez la base avant toute opération. Les migrations sont ordonnées par date dans `database/migrations/` ; la migration consolidée du 2 août 2026 doit être suivie de `20260803_add_custom_billing_lines.sql`.

## Tests

La suite locale reconstruit une base dont le nom commence obligatoirement par `cksgo_test_`, injecte uniquement des données fictives, démarre un serveur isolé, exécute tous les scénarios, puis supprime cette base.

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File tests/run_all.ps1
```

Elle couvre notamment :

- syntaxe et audit statique des vues/formulaires ;
- politique de mot de passe Argon2id et contraintes SQL ;
- permissions et accès par rôle ;
- inventaire, paiements, soldes, factures et remboursements ;
- isolation des commandes et paniers entre utilisateurs ;
- CSRF, XSS réfléchi, entrées volumineuses et charges ressemblant à une injection SQL ;
- renouvellement de session, cookies et en-têtes de sécurité ;
- refus de `TRACE`, des routes héritées et des fichiers internes.

Options utiles :

```powershell
# Chemins et identifiants adaptés à une installation non-XAMPP
./tests/run_all.ps1 -PhpPath 'C:\php\php.exe' -DbUser 'cksgo_ci' -DbPassword 'secret'

# Conserver exceptionnellement la base fictive pour une inspection manuelle
./tests/run_all.ps1 -KeepDatabase
```

Ne donnez jamais à la suite de tests le nom d'une base réelle. Le script refuse tout nom qui ne respecte pas le préfixe `cksgo_test_`.

La CI GitHub répète les contrôles sur PHP 8.2 et MariaDB 10.11, lance `composer audit` et analyse l'historique Git à la recherche de secrets.

## Sécurité et exploitation

Les principales protections intégrées sont :

- jetons CSRF sur les mutations ;
- requêtes PDO préparées et contrôles d'intégrité en base ;
- contrôle des rôles et de la propriété des ressources ;
- sessions strictes, renouvellement d'identifiant et expiration d'inactivité ;
- CSP sans `unsafe-inline`, anti-framing, `nosniff`, politiques de référent et d'isolation ;
- validation stricte des images et noms de fichiers aléatoires ;
- réponses d'authentification non discriminantes et limitation des tentatives ;
- journalisation des actions sensibles avec identifiant de requête.

Avant chaque déploiement :

```powershell
composer install --no-dev --classmap-authoritative
composer audit --locked
powershell.exe -NoProfile -ExecutionPolicy Bypass -File tests/run_all.ps1
```

Consultez [`SECURITY.md`](SECURITY.md) pour le signalement privé des vulnérabilités et les règles de déploiement.

## Organisation du dépôt

```text
config/       configuration générale et exemple local
controllers/  orchestration HTTP
core/         routeur, contrôleur et modèle de base
database/     schéma vierge et migrations historiques
helpers/      sessions, sécurité, permissions et utilitaires UI
models/       accès aux données et règles métier
public/       CSS, JavaScript et images servies au navigateur
scripts/      outils CLI d'installation
services/     paiements, e-mails et tableaux de bord
tests/        scénarios métier, HTTP et sécurité
views/        vues PHP et modèles d'e-mails
```

## Licence

Aucune licence n'est accordée avec ce dépôt. Tous droits réservés par défaut.
