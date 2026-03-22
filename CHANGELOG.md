# Changelog

Tous les changements notables de ce projet seront documentés dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Versioning Sémantique](https://semver.org/lang/fr/spec/v2.0.0.html).

## [3.0.0] - 2026-02-27

### 🚀 Ajouté

#### Nouveaux fichiers et classes

- **ConnectionManager.php** : Nouvelle classe pour gérer de manière centralisée les connexions à la base de données
  - Support de multiples profils de connexion (main, read_only, etc.)
  - Gestion du singleton pour les connexions
  - Méthode `setConfig()` pour définir les configurations
  - Méthode `setDefaultProfile()` pour définir le profil par défaut
  - Méthode `get()` pour récupérer une connexion
  - Méthode `clear()` pour nettoyer les connexions (utile pour les tests)

- **QueryBuilder.php** : Nouvelle classe pour construire des requêtes SQL de manière fluide
  - Méthodes chainables pour construire des requêtes complexes
  - Support des jointures, conditions WHERE, GROUP BY, ORDER BY
  - Gestion des sous-requêtes
  - Support des fonctions d'agrégation (AVG, COUNT, DISTINCT, MAX, MIN, SUM)

- **StandardModel.php** : Nouvelle classe de modèle standardisé
  - Implémentation de base pour les modèles standards

#### Nouveaux attributs PHP 8.0

- **attributes/ClassName.php** : Attribut de classe pour définir le nom de la classe modèle associée au manager
- **attributes/DbFactory.php** : Attribut de classe pour définir le nom du factory de connexion
- **attributes/Encrypted.php** : Attribut de propriété pour marquer un champ comme chiffré
- **attributes/Field.php** : Attribut de propriété pour mapper une propriété à un champ de table
- **attributes/ID.php** : Attribut de propriété pour marquer un champ comme clé primaire
- **attributes/Table.php** : Attribut de classe pour définir le nom de la table associée au manager

#### Nouvelles énumérations

- **enum/QueryFunction.php** : Énumération des fonctions SQL supportées (AVG, COUNT, DISTINCT, MAX, MIN, SUM)
- **enum/QueryOperator.php** : Énumération des opérateurs de requête supportés
- **enum/QuerySequenceType.php** : Énumération des types de séquence (AND, OR)

#### Nouvelles exceptions

- **exception/ManagerNotFoundException.php** : Exception levée lorsqu'un manager n'est pas trouvé
- **exception/ModelMethodNotFoundException.php** : Exception levée lorsqu'une méthode de modèle n'existe pas

#### Tests unitaires

- **unit** : Ajout de Tests unitaires pour
  - Le Manager
  - Le Modèle
  - Le QueryBuilder

### ♻️ Modifié

#### DbConnect.php

- Suppression du paramètre `$options_connexion` du constructeur
- Suppression de la constante `DEFAULT_CHARSET`
- Ajout de types de retour stricts sur toutes les méthodes
- Amélioration de la visibilité des méthodes (ajout de `public`, `private`, `protected`)
- Refactorisation de la méthode `connect()` pour supprimer la gestion du charset personnalisé
- Ajout de types de retour : `string`, `PDO`, `EncryptOpenSSL`, `void`, `bool`
- Amélioration du formatage du code et de l'organisation des imports
- Documentation améliorée avec des annotations `@return`

#### Manager.php

**Changements majeurs d'architecture :**

- Passage de classe concrète à classe **abstraite**
- Suppression de la méthode `setDb()` - la connexion est maintenant gérée automatiquement via `ConnectionManager`
- Suppression des méthodes `setTableName()`, `setClassName()`, `setTableIdField()` - remplacées par des attributs PHP 8.0
- Utilisation d'attributs PHP 8.0 pour la configuration (#[Table], #[ClassName], #[DbFactory], etc.)
- Ajout du constructeur `__construct()` qui initialise les attributs automatiquement

**Nouvelles méthodes :**

- `persist()` : Remplace et unifie `add()` et `update()` - détecte automatiquement s'il faut créer ou modifier
- `findById()` : Remplace `get()` - nom plus explicite
- `findByIds()` : Remplace `getList()` - nom plus explicite
- `findBy()` : Nouvelle méthode pour rechercher selon des critères
- `findAll()` : Récupère tous les éléments de la table
- `updateBy()` : Met à jour plusieurs enregistrements selon des critères
- `deleteBy()` : Supprime plusieurs enregistrements selon des critères
- `withConnexion()` : Permet d'exécuter une opération avec une connexion spécifique
- `_initAttributes()` : Initialise les attributs de classe (Table, ID, ClassName, DbFactory)
- `_initDb()` : Initialise la connexion à la base de données via ConnectionManager
- `_bindParamsStmt()` : Gère le binding des paramètres PDO

**Méthodes refactorisées :**

- `search()` : Refactorisation complète avec amélioration de la gestion des erreurs
- `contruct_request()` : Amélioration de la construction des requêtes
- `recordFields()` : Utilisation de `information_schema.columns` pour récupérer les métadonnées des tables
- Amélioration de la gestion du chiffrement des colonnes

**Améliorations générales :**

- Ajout de types stricts sur tous les paramètres et retours de méthodes
- Meilleure gestion des erreurs avec des exceptions au lieu de `return false`
- Documentation PHPDoc complète avec annotations `@param`, `@return`, `@throws`
- Support du débogage amélioré
- Code plus moderne et conforme aux standards PSR

#### Model.php

**Changements majeurs :**

- Refactorisation complète pour utiliser les attributs PHP 8.0
- Utilisation de la réflexion pour générer dynamiquement les getters/setters
- Ajout de la méthode magique `__call()` pour gérer les appels aux getters/setters
- Méthode `createSettersGetters()` : Génère automatiquement les accesseurs basés sur les attributs #[Field]
- Méthode `asArray()` : Convertit l'objet en tableau associatif
- Support des types complexes (DateTime, Date, array, bool, int, float, string)
- Méthode `toCamelCase()` : Conversion snake_case vers camelCase
- Gestion automatique de la conversion de types dans les setters
- Support des getters avec préfixe `is` pour les booléens

**Améliorations :**

- Hydratation automatique depuis le constructeur
- Validation des types lors du set
- Support des valeurs nullables
- Documentation complète
- Meilleure gestion des erreurs avec exceptions personnalisées

#### README.md

- Mise à jour complète de la documentation
- Ajout d'exemples d'utilisation du ConnectionManager
- Documentation des attributs PHP 8.0
- Mise à jour des exemples de code
- Ajout d'exemples d'utilisation de `persist()`, `findById()`, `findByIds()`, etc.
- Documentation du QueryBuilder
- Mise à jour des instructions d'installation

#### composer.json

- Version mise à jour : `2.2.0` → `3.0.0`
- Requirement PHP mis à jour : `>=5.4` → `>=8.3`
- Suppression de la dépendance `processid/traits`
- Ajout de `phpunit/phpunit` version `^9` en dépendance de développement
- Configuration PSR-4 maintenue

### ⚠️ Supprimé (Breaking Changes)

#### Méthodes supprimées de Manager.php

- `setDb()` - Remplacée par l'initialisation automatique via ConnectionManager
- `setTableName()` - Remplacée par l'attribut #[Table]
- `setClassName()` - Remplacée par l'attribut #[ClassName]
- `setTableIdField()` - Remplacée par l'attribut #[ID] sur le modèle
- `add()` - Remplacée par `persist()`
- `update()` - Remplacée par `persist()`
- `get()` - Remplacée par `findById()`
- `getList()` - Remplacée par `findByIds()`
- `bind_query()` - Remplacée par une gestion interne du binding

#### Autres suppressions

- Support de PHP < 8.3
- Support de la configuration du charset dans le constructeur de DbConnect
- Dépendance à `processid/traits` (fonctionnalité intégrée directement)
- Méthodes et propriétés sans typage strict

### 🔧 Changements de comportement

- **Manager** : Le constructeur doit maintenant être appelé avec `parent::__construct()` dans les classes filles
- **DbConnect** : Le charset est maintenant fixé à `utf8` (anciennement configurable)
- **Model** : Les propriétés doivent être annotées avec #[Field] pour être persistées
- **Connexions** : Doivent être configurées via `ConnectionManager::setConfig()` au démarrage de l'application
- **Erreurs** : Les méthodes lancent maintenant des exceptions au lieu de retourner `false`

### 📋 Migration depuis la version 2.x

Pour migrer depuis la version 2.x vers 3.0.0 :

1. **Mettre à jour PHP** : Nécessite PHP 8.3 minimum
2. **Configurer ConnectionManager** : Appeler `ConnectionManager::setConfig()` au démarrage
3. **Mettre à jour les Managers** :
   - Retirer l'appel à `setDb()`, `setTableName()`, `setClassName()`
   - Ajouter les attributs #[Table], #[ClassName], #[DbFactory]
   - Appeler `parent::__construct()` dans le constructeur
4. **Mettre à jour les Models** :
   - Ajouter l'attribut #[Field] sur chaque propriété
   - Ajouter l'attribut #[ID] sur la clé primaire
   - Optionnel : Ajouter #[Encrypted] sur les champs chiffrés
5. **Mettre à jour les appels de méthodes** :
   - `add()` → `persist()`
   - `update()` → `persist()`
   - `get()` → `findById()`
   - `getList()` → `findByIds()`
6. **Gérer les exceptions** : Remplacer les vérifications `if ($result === false)` par des blocs try-catch

---

## [2.2.0] - Date antérieure

Version précédente (détails non disponibles dans ce changelog)

---

**Note** : Cette version 3.0.0 représente une refonte majeure avec des changements incompatibles (breaking changes). Veuillez consulter le guide de migration ci-dessus avant de mettre à jour.

