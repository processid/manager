# Manager
Système de gestion et de connexion de base de données.

## Installation :
Ajoutez à votre fichier composer.json dans la section require, "processid/manager": "3.0.0". Puis lancez la commande
composer update. Voici un exemple de fichier composer.json avec uniquement l'usage du Manager.
```json
{
    "require": {
        "processid/manager": "3.0.0"
    },
    "autoload": {
        "psr-4": {
            "src\\": "src/"
        }
    }
}
```

# Utilisation

## ConnectionManager :
Le `Manager` permet d’interagir avec la base de données via un objet `DbConnect`.  
Il ne contient aucune logique de récupération de configuration.

La gestion des connexions est entièrement déléguée à une classe centrale :  
`ConnectionManager`.

Cette architecture permet :

- d’isoler le Manager de toute source de configuration
- de rendre le package portable (compatible Composer)
- de gérer plusieurs connexions (main, read_only, etc.)
- de laisser l’application décider d’où provient la configuration (JSON, Config, .env, variables d’environnement, secrets manager, etc.)

Avant d’utiliser un `Manager`, l’application doit déclarer les configurations au démarrage (bootstrap, index.php, kernel, CLI, etc.).

## Exemple

```php
ConnectionManager::setConfig(['main' => [
    'type'        => 'mysql',
    'host'        => 'localhost',
    'database'    => 'dbname',
    'user'        => 'dbuser',
    'pass'        => 'dbpassword',
    'key_aes256'  => '...',
    'key_hash512' => '...',
    'method'      => 'aes-256-cbc'
], 'read_only' => [
    'type'        => 'mysql',
    'host'        => 'localhost',
    'database'    => 'dbnamero',
    'user'        => 'dbuserro',
    'pass'        => 'dbpasswordro',
    'key_aes256'  => '...',
    'key_hash512' => '...',
    'method'      => 'aes-256-cbc'
]);
```

## Héritage de la classe Manager

`Manager.php` est une classe abstraite qui doit être héritée par autant de classes filles que de tables que vous souhaitez interroger avec le Manager.
On utilise des attributs de classe PHP 8.0 pour configurer un manager.

### Attributs de classe
#### DbFactory :
Attribut qui s'applique au class et qui permet de définir la factory de connexion à la base de données pour la class manager fille.
Cette attribute est optionnel, s'il n'est pas défini, la première factory dans le fichier de configuration sera utilisée.

#### ClassName :
Attribut qui s'applique au class et qui permet de définir le nom de la classe (modèle) qui gère l'objet fourni au manager.

#### Table :
Attribut qui s'applique au class et qui permet de définir le nom de la table représentée par la class manager fille.

Voici un exemple de classe fille de `Manager.php` avec les différents attributs de classe :
Il est à noter que les attributes utilisé doit être importé (avec le __use__) dans la class ou on les utilise.

```php
<?php
    
    use processid\manager\attributes\DbFactory;
    use processid\manager\attributes\ClassName;
    use processid\manager\attributes\Table;
    use processid\manager\Manager;
    
    #[DbFactory('main')]
    #[ClassName('\src\model\Test')]
    #[Table('nom_table')]
    class TestManager extends Manager
    {
        public function __construct()
        {
            parent::__construct();
        }
    }
?>
```

## Model

On utilise des attributs de classe PHP 8.0 pour configurer un modèle.

### Attributs
#### Field :
Attribut qui s'applique aux propriétés de la class et qui permet de définir le nom du champ de la table représentée par la propriété.
Si le nom du champ n'est pas spécifié, le nom de la propriété sera utilisé.

#### ID :
Attribut qui s'applique aux propriétés de la class et qui permet de définir la propriété comme étant la clé primaire de la table.

#### Encrypted :
Attribut qui s'applique aux propriétés de la class et qui permet de définir la propriété comme étant chiffrée.

__Note__ : Pour les attributs `ID` et `Encrypted`, l'attribute `Field` est obligatoire avant.

```php
<?php
    namespace src\model;
    
    use processid\manager\attributes\Field;
    use processid\manager\attributes\ID;
    use processid\manager\attributes\Encrypted;
    use processid\manager\Manager;
    use processid\manager\Model;
    
    class Test extends Model
    {
        #[Field('test_id')]
        #[ID]
        private $idTest;
        
        #[Field('name')]
        private $name;
        
        #[Field('encrypted')]
        #[Encrypted]
        private $encrypted;
    }
?>
```

### Encodage d'une colonne
Il est possible de chiffrer toute une colonne.
Attention cette opération peut prendre beaucoup de temps suivant le nombre d'enregistrements.
La colonne doit être suffisamment grande pour accueillir le chiffrement.
```php
$this->encrypt_column($champ);
```

### Décodage d'une colonne
L'opération inverse est également possible.
```php
$this->decrypt_column($champ);
```

### Create et update, ajout et modification d'un enregistrement

`persist()` ajoute un nouvel enregistrement dans la table si l'id de l'objet n'est pas spécifié sinon modifie l'enregistrement correspondant.
`$object` est une instance de la classe modèle avec les données à insérer ou à modifier.
`$fields` Seulement pour l'action modifiée. C'est un tableau correspondant aux noms des champs à modifier. Si non spécifié, tous les champs sont pris en compte.
`$ignore` Seulement pour l'action ajoutée. La requête devient `INSERT IGNORE` et lève une exception si la requête n'a pas inséré d'enregistrement.
En cas d'erreur, la fonction lève une exception.
En cas de succès, la fonction retourne l'ojet passé en paramètre avec son ID d'enregistrement.
```php
$this->persist(\src\model\Clients $object, [$ignore = FALSE]);
```

### Read, lecture d'un enregistrement
`findById()` retourne une instance de \src\model\Clients avec les données de l'enregistrement dont l'ID est passé en paramètre.
`$ID` est la valeur recherchée de `IDclients` dans la table `clients`.
Par défaut, la fonction récupère tous les champs de la table. Il est possible de préciser lesquels récupérer grâce au paramètre optionnel `$champs`.
Ce dernier peut être une chaine de caractères correspondant au nom du champ ou un tableau de chaines de caractères correspondant aux noms des champs.
```php
$this->findById($ID, $champs);
```

### Read, lecture de plusieurs enregistrements
`findByIds()` retourne un tableau d'instances de \src\model\Clients avec les données des enregistrements dont les ID sont passés en paramètre.
`$IDs` est un tableau de valeurs recherchées de `IDclients` dans la table `clients`.
Par défaut, la fonction récupère tous les champs de la table. Il est possible de préciser lesquels récupérer grâce au paramètre optionnel `$champs`.
Ce dernier peut être une chaine de caractères correspondant au nom du champ ou un tableau de chaines de caractères correspondant aux noms des champs.
```php
$this->findByIds($IDs, $champs);
```

### Delete, suppression d'un enregistrement
`delete()` supprime un enregistrement dans la table.
`$ID` est la valeur recherchée de `IDclients` dans la table `clients`.
```php
$this->delete($ID);
```

### findBy, recherche d'enregistrements
`findBy()` retourne un tableau d'objet et par rapport aux champs demandés dans fields[].
Par défaut l'objet du modèle est retourné suivant les fields demander (si non spécifié, on retourne l'intégralité de l'objet).
Sinon, on peut passer en 2ème paramètre le nom de la class à retourner Pour hydrater l'objet, il se base sur les setters de la class qui seront en rapport avec les fields demandés (l'alias en utilisé en priorité).
Si `fields[]` est vide, on retourne un tableau d'objet avec toutes les données de la table.
`$arg` est un tableau associatif facultatif. Il peut contenir les clés suivantes :
- `fields` : tableau de tableaux des champs à retourner : `'table'=><Nom de la table>, 'field'=><Nom du champ>, (Optionnel)'alias'=><Alias du champ>`
- `beforeWhere` : Chaîne à insérer avant WHERE (INNER JOIN...)
- `afterWhere` : Chaîne à insérer après WHERE (GROUP BY...)
- `start` : Premier enregistrement retourné
- `limit` : Nombre d'enregistrements retournés. Si `start `> 0, alors `limit` > 0. `limit` = 0 retourne tous les enregistrements.
- `search` : tableau de tableaux : `'table'=><Nom de la table>, 'field'=><Nom du champ>, 'operator'=>" < | > | <= | >= | = | != | in_array | not_in_array | fulltext | %fulltext | %fulltext% | fulltext% | like | not_like | %like | %not_like | %like% | %not_like% | like% | not_like% | is_null | is_not_null ", 'value'=><Valeur recherchée>`
- `sequence` : Chaine de séquence des WHERE. Par défaut, toutes les clauses 'search' du WHERE sont séquencées avec des AND, mais il est possible de renseigner la chaine 'sequence' pour personnaliser. Par exemple : '((WHERE1 AND WHERE2) OR (WHERE3 AND WHERE4))' Les clauses Where sont numérotées de 1 à n et sont dans l'ordre du tableau 'search'. Si 'sequence' est fourni, il faut y renseigner toutes les clauses 'search' du WHERE. 'sequence' ne doit comporter que les chaînes et caractères suivants en plus des WHEREn : '(', ')', ' ', 'OR', 'AND'
- `sort` : tableau de tableaux : `'table'=><Nom de la table>, 'field'=><Nom du champ>, 'reverse'=><true | false>)`. Si `reverse` n'est pas renseigné, il est considéré comme `false`.

Exemple de recherche des 10 premiers clients de France triés par nom de famille
```php
$arg = ['start' => 0, 'limit' => 10, 'fields' => [], 'search' => [], 'sort' = []];

$arg['fields'][] = ['table' => 'clients', 'field' => 'IDclients']
$arg['fields'][] = ['table' => 'clients', 'field' => 'nom']

$arg['search'][] = ['table' => 'clients', 'field' => 'pays', 'operator' => '=', 'value' => 'France'];

$arg['sort'][] = ['table' => 'clients', 'field' => 'nom', 'reverse' => false];

// Retourner un tableau d'objet model Clients
$taClients = $clientsManager->findBy($arg);

// Retourner un tableau d'objet quelquonque
$taClients = $clientsManager->findBy($arg, '\src\Obj\ClientsCustom');
```

### countBy, nombre d'enregistrements
`countBy()` retourne le nombre d'enregistrements de la table.
Elle repose sur le même principe qu'un `findBy()` mais ne retourne que le nombre d'enregistrements.

```php
$arg = ['start' => 0, 'limit' => 10, 'fields' => [], 'search' => [], 'sort' = []];

$arg['fields'][] = ['table' => 'clients', 'field' => 'IDclients']
$arg['fields'][] = ['table' => 'clients', 'field' => 'nom']

$arg['search'][] = ['table' => 'clients', 'field' => 'pays', 'operator' => '=', 'value' => 'France'];

$arg['sort'][] = ['table' => 'clients', 'field' => 'nom', 'reverse' => false];

$nbItems = $clientsManager->countBy($arg);
```

### findAll, tous les enregistrements
`findAll()` retourne un tableau d'objet et par rapport aux champs demandés dans fields[]. Un tri peut être effectué sur les données.

```php
/**
 * format sort : nom_colonne => constante du class Manager SORT_ASC ou SORT_DESC
 */
$this->persist($fields, [$sort = array()]);
```

### countAll, nombre d'enregistrements
`countAll()` retourne le nombre d'enregistrements de la table.

```php
$this->countAll();
```

## Débogage
`$this->debugTxt()` contient la dernière requête et les éventuelles valeurs des binds.

## QueryBuilder

La classe QueryBuilder permet de construire des arguments de requêtes complexes pour les fonctions findBy, countBy, updateBy et deleteBy d'un
objet Manager. Elle fournit une interface fluide pour gérer la sélection de champs, les conditions de recherche, les tris, les jointures,
les regroupements, et bien plus, tout en respectant les contraintes des tables associées au Manager.

### Utilisation
Pour utiliser QueryBuilder, vous devez d'abord disposer d'une instance de Manager.

Dans cette exemple on va chercher le tableau d'agrument pour avoir les 10 premiers enregistrements de la table `nom_table` avec les champs `id` et `name` triés par `created_at` en ordre décroissant, et où le champ `name` contient la chaîne `test` ou champ `id` est entre 1 à 20 :
```php
use processid\manager\QueryBuilder;
use processid\manager\enum\QueryOperator;

$manager = new SomeManager(); // Une instance de Manager
$queryBuilder = new QueryBuilder($manager);

$args = $queryBuilder
    ->field('id')
    ->field('name',)
    ->search('name', 'test', QueryOperator::LIKE_BOTH)
    ->orSearch('id', range(1, 20), QueryOperator::IN_ARRAY)
    ->sort('created_at', true)
    ->limit(10, 0)
    ->build();
```

### Action final du queryBuilder
Avec le queryBuilder, vous pouvez effectuer les actions finales suivantes :
- **build** : Construit les arguments de requête
- **run** : Exécute la requête et retourne le résultat
- **update** : Avec un paramètre tableau de champs à modifier, exécute la requête en faisant un update des resultats par rapport au critère en paramètre et retourne le nombre de lignes affectées
- **delete** : Exécute la requête en suppriment les résultats et retourne le nombre de lignes affectées

exemple :

```php
$qb = $queryBuilder
    ->field('id')
    ->field('name',)
    ->search('name', 'test', QueryOperator::LIKE_BOTH)
    ->orSearch('id', range(1, 20), QueryOperator::IN_ARRAY)
    ->sort('created_at', true)
    ->limit(10, 0);

// Construit les arguments de requête
$args = $qb->build();

// Exécute la requête et retourne le résultat
$result = $qb->run();

// update des resultats par rapport au critère en paramètre et retourne le nombre de lignes affectées
$nbElementAffecte = $qb->update(['name' => 'test update']);

// Exécute la requête en suppriment les résultats et retourne le nombre de lignes affectées
$nbElementAffecte = $qb->delete();
```