# Manager

Système de gestion et de connexion de base de données.


# Installation

Ajoutez à votre fichier `composer.json` dans la section `require`, `"processid/manager": "1.8.1"`. Puis lancez la commande `composer update`.
Voici un exemple de fichier `composer.json` avec uniquement l'usage du Manager.
```json
{
    "require": {
        "processid/manager": "1.8.1"
    }
}
```

# Utilisation

## Héritage de la classe Manager

`Manager.php` est une classe qui doit être héritée par autant de classes filles que de tables que vous souhaitez interroger avec le Manager.
Voici un exemple de classe fille de `Manager.php` :

```php
<?php
    class Client extends \ProcessID\Manager\Manager {

        // Il est possible d'utiliser des colonnes chiffrées
        $this->setEncryptedFields(['nom'=>true, 'email'=>true, 'tel'=>true, 'siret'=>true]);

        // Si malgrè le chiffrement, certains champs doivent rester triables
        // il faut utiliser le daemon pour renseigner les colonnes de tri
        $this->setEncryptedFieldsSortable(['nom'=>true, 'email'=>true]);
        
        public function __construct(\ProcessID\Manager\DbConnect $db) {
            $this->setDb($db)
            $this->setTableName('clients'); // Nom de la table
            $this->setPrimaryKey('IDclients'); // Nom du champ ID de la table
            $this->setClassName('\src\model\Clients'); // Nom de la classe gérant l'objet fourni au manager
        }

    }
?>
```

## Exemples

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

### Create, ajout d'un enregistrement
`add()` ajoute un nouvel enregistrement dans la table.
Le nouvel ID est enregistré dans l'attribut `IDclients` de l'objet passé en paramètre.  
En cas d'erreur, la fonction d'ajout retourne `false`.
En cas de succès, la fonction d'ajout retourne l'ID de l'enregistrement ajouté.
Si on positionne `$ignore` à `true`, la requête devient `INSERT IGNORE et `0` est retourné si la requête n'a pas inséré d'enregistrement.
```php
$this->add(\src\model\Clients $obj, [$ignore = FALSE]);
```

### Read, lecture d'un enregistrement
`get()` retourne une instance de \src\model\Clients avec les données de l'enregistrement dont l'ID est passé en paramètre.
`$ID` est la valeur recherchée de `IDclients` dans la table `clients`.
Par défaut, la fonction récupère tous les champs de la table. Il est possible de préciser lesquels récupérer grâce au paramètre optionnel `$champs`.
Ce dernier peut être une chaine de caractères correspondant au nom du champ ou un tableau de chaines de caractères correspondant aux noms des champs.
```php
$this->get($ID, $champs);
```

### Read, lecture de plusieurs enregistrements
`getList()` retourne un tableau d'instances de \src\model\Clients avec les données des enregistrements dont les ID sont passés en paramètre.
`$IDs` est un tableau de valeurs recherchées de `IDclients` dans la table `clients`.
Par défaut, la fonction récupère tous les champs de la table. Il est possible de préciser lesquels récupérer grâce au paramètre optionnel `$champs`.
Ce dernier peut être une chaine de caractères correspondant au nom du champ ou un tableau de chaines de caractères correspondant aux noms des champs.
```php
$this->getList($IDs, $champs);
```

### Update, modification d'un enregistrement
`update()` modifie un enregistrement dans la table.
`$object` est une instance de \src\model\Clients avec les données à mettre à jour.
Cette fonction retourne le nombre d'enregistrements modifiés en cas de succès, `false` en cas d'erreur.
```php
$this->update($object, $champs);
```

### Delete, suppression d'un enregistrement
`delete()` supprime un enregistrement dans la table.
`$ID` est la valeur recherchée de `IDclients` dans la table `clients`.
```php
$this->delete($ID);
```

### Search, recherche d'enregistrements
`search()` retourne un tableau associatif des champs demandés dans fields[]
Si `fields[]` est vide, search retourne un tableau d'IDs qu'il est possible de passer directement à `getList()`.
`$arg` est un tableau associatif facultatif. Il peut contenir les clés suivantes :
- `fields` : tableau de tableaux des champs à retourner : `'table'=><Nom de la table>, 'field'=><Nom du champ>, (Optionnel)'alias'=><Alias du champ>`
- `special` : chaîne 'count', `$this->_nbResults` sera mis à jour avec le nombre de résultats de la requête, sans limit ni offset et sans sort. `$this->_nbResults` sera également retourné
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

$taClients = $clientsManager->search($arg);
```


## Débogage
Il est possible d'activer ou de désactiver le débogage avec `$this->setDebug(TRUE | FALSE)`.
Quand il est actif, `$this->debugTxt()` contient la dernière requête et les éventuelles valeurs des binds.
Le buffer de débogage est vidé lors de sa lecture : `$this->debugTxt()`, ou lors de son initialisation : `$this->setDebug(TRUE | FALSE)`.
