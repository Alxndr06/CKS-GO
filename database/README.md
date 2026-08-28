# Base de données CKS GO 2.0

## Installation neuve

Pour une installation sans base historique, importez directement `database/schema.sql`. Ce fichier contient le schéma cible complet (33 tables et contraintes d'intégrité), sans compte ni donnée métier. Ne rejouez ensuite aucune migration datée.

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -p -e "CREATE DATABASE cksgo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
C:\xampp\mysql\bin\mysql.exe -u root -p cksgo -e "SOURCE database/schema.sql"
```

Créez le premier administrateur avec `scripts/create_admin.php` comme indiqué dans le README principal.

## Migration définitive depuis `cksgo_db.sql`

Le fichier à exécuter est :

`database/migrations/20260802_migrate_legacy_cksgo_db.sql`

Il regroupe, dans le bon ordre, toutes les évolutions nécessaires entre le dump historique du 31 juillet 2026 et le schéma utilisé par l’application au 2 août 2026 :

- nouveaux rôles et autorisations individuelles ;
- journal financier, lots d’encaissement et mouvements de solde ;
- actualités et tickets enrichis ;
- archivage du catalogue, seuils et historique de stock ;
- visibilité publique, connectée ou staff des produits ;
- bannissements par e-mail ou adresse IP ;
- signalements multi-produits et remboursements associés ;
- contraintes d’intégrité métier ;
- adresse IP, client HTTP et identifiant de requête dans le journal d’administration.

### Compatibilité vérifiée

- dump source : `cksgo_db.sql`, généré par MariaDB 10.11.18 le 31/07/2026 ;
- moteur de validation local : MariaDB 10.4.32 ;
- schéma cible : 33 tables, 292 colonnes, 118 contraintes et 143 index ;
- contenu du dump fourni : 27 tables, sans ligne métier.

Le script a également été éprouvé avec des données historiques de test : anciens rôles `helper` et `mod`, soldes positifs et négatifs, produit, variante sans SKU, commande, actualité, ticket et réponse administrateur.

## Procédure recommandée

### 1. Mettre l’application hors trafic

Empêcher toute nouvelle commande ou écriture pendant la sauvegarde et la migration.

### 2. Sauvegarder la base actuelle

Exemple Windows avec un fichier produit directement par `mysqldump` :

```powershell
C:\xampp\mysql\bin\mysqldump.exe -u root -p `
  --single-transaction --routines --triggers `
  --result-file=C:\backups\cksgo_avant_migration.sql `
  aulon1930571_3f4f3t
```

Adapter l’utilisateur, le chemin et le nom de base à l’hébergement. Vérifier que le fichier de sauvegarde existe et n’est pas vide avant de continuer.

### 3. Importer le dump historique si nécessaire

Attention : le dump fourni contient lui-même :

```sql
CREATE DATABASE IF NOT EXISTS `aulon1930571_3f4f3t`;
USE `aulon1930571_3f4f3t`;
```

Il créera donc et sélectionnera ce nom de base, même si un autre nom a été choisi dans l’interface d’import.

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -p `
  -e "SOURCE C:/chemin/vers/cksgo_db.sql"
```

Pour utiliser le nom local `cksgo_db`, modifier uniquement les deux instructions `CREATE DATABASE` et `USE` dans une copie du dump avant son import.

### 4. Exécuter la migration sur la base sélectionnée

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -p `
  -D aulon1930571_3f4f3t `
  -e "SOURCE C:/chemin/vers/CKS_GO_2.0/database/migrations/20260802_migrate_legacy_cksgo_db.sql"
```

Dans phpMyAdmin : sélectionner explicitement la base issue du dump, ouvrir l’onglet **Importer**, puis choisir `20260802_migrate_legacy_cksgo_db.sql`.

### 5. Exiger les résultats suivants

La fin du script doit afficher :

```text
tables_count      = 33
columns_count     = 292
constraints_count = 118
indexes_count     = 143
```

Tous les contrôles d’anomalies doivent être égaux à `0`.

### 6. Configurer l’application sur la base migrée

La variable d’environnement doit correspondre au nom réellement utilisé :

```text
CKSGO_DB_NAME=aulon1930571_3f4f3t
```

ou, si le dump a été adapté avant import :

```text
CKSGO_DB_NAME=cksgo_db
```

## Protections intégrées au script

Avant le premier changement de structure, la migration :

- exige la sélection explicite d’une base ;
- vérifie la présence des 27 tables historiques attendues ;
- refuse une base déjà migrée ou partiellement migrée ;
- refuse les quantités, prix, paiements, remboursements ou stocks incompatibles avec les contraintes cibles.

La migration complète est encapsulée dans une procédure. Une erreur de précontrôle interrompt donc les changements de structure au lieu de laisser le client MySQL poursuivre les instructions suivantes.

Les opérations DDL MariaDB valident implicitement leurs changements. En cas d’interruption pendant une migration réellement commencée, ne pas improviser une reprise : restaurer la sauvegarde dans une base propre, identifier la cause, puis relancer le fichier complet.

## Migrations unitaires

Les autres fichiers du dossier `database/migrations` documentent l’historique de développement. Ils ne doivent pas être rejoués après la migration définitive, car leur contenu est déjà inclus dans le fichier consolidé.

### Évolutions postérieures à la migration définitive

Après avoir appliqué la migration consolidée du 2 août 2026, exécuter dans l’ordre les migrations datées ultérieurement :

1. `20260803_add_custom_billing_lines.sql` pour autoriser la facturation de montants libres sans produit du catalogue.

Cette migration doit retourner `invalid_billing_lines = 0` à la fin de son exécution.
