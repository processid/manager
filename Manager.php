<?php
/*
// Classe de connexion à la base de données
// Cette classe doit être héritée
// Voici un exemple de classe enfant:

class Clients extends \ProcessID\Manager\Manager {

    // Il est possible d'utiliser des colonnes chiffrées:
    $this->setEncryptedFields(array('nom'=>true,'email'=>true,'tel'=>true,'siret'=>true));

    // Si malgré le chiffrement, certains champs doivent rester triables:
    // Il faut utiliser le démon pour renseigner les colonnes de tri
    $this->setEncryptedFieldsSortable(array('nom'=>true,'email'=>true));

    public function __construct(\ProcessID\Manager\DbConnect $db) {
        $this->setDb($db);
        $this->setTableName('clients'); // Nom de la table de BD
        $this->setTableIdField('IDclients');  // Nom du champ ID de la table
        $this->setClassName('\src\model\Clients'); // Nom de la classe gérant l'objet fourni au manager
    }
}

// EXEMPLES D'UTILISATION:

// Il est possible de chiffrer toute une colonne:
// Attention, peut prendre beaucoup de temps suivant le nombre d'enregistrements
// La colonne doit être suffisamment large pour acueillir la chaîne chiffrée
$this->encrypt_column($champ);

// Et l'opération inverse:
$this->decrypt_column($champ);

// Le tri sur une colonne chiffrée se fait sur une colonne INT indexée qui porte le nom de la colonne chiffrée +'_tri'
// Il est possible de créer automatiquement les colonnes manquantes avec leurs index:
$this->create_encrypted_fields_sortable();

// CREATE:
// Le nouvel ID est enregistré dans $this->IDclients()
// En cas d'erreur, retourne FALSE et renseigne errorTxt
// En cas de succès, retourne l'ID créé
// Si on positionne $ignore à TRUE, la requête devient "INSERT IGNORE" et 0 est retourné si la requête n'a pas inséré d'enregistrement
$this->add(\src\model\Clients $obj, [$ignore = FALSE]);

// READ:
// get() retourne une instance de \src\model\Clients
// $ID est la valeur recherchée de IDclients
// Paramètre optionnel : $champs est un champ ex: 'nom', un tableau de champs ex: array('nom','tel') ou '*'
// Par défaut, si $champs n'est pas fourni, tous les champs sont hydratés
$this->get($ID, $champs);

// getList() retourne un tableau d'instances de \src\model\Clients
// $ta_IDs est un tableau d'IDclients
// Paramètre optionnel : $champs est un champ ex: 'nom', un tableau de champs ex: array('nom','tel') ou '*'
// Par défaut, si $champs n'est pas fourni, tous les champs sont hydratés
$this->getList($ta_IDs, $champs);

// UPDATE
// Paramètre optionnel : $champs est un champ ex: 'nom' ou un tableau de champs ex: array('nom','tel')
// Par défaut, si $champs n'est pas fourni, tous les champs sont mis à jour
// Update retourne le nombre d'enregistrements modifiés
// En cas d'erreur, retourne FALSE et renseigne errorTxt
$this->update($object, [$champs]);

// DELETE
$this->delete($ID);

SEARCH:
// search() retourne un tableau associatif des champs demandés dans fields[]
// Si fields[] est vide, search retourne un tableau d'ID qu'il est possible de passer directement à getList()
// $arg : tableau associatif facultatif
// 'fields' => tableau de tableaux des champs à retourner : 'table'=><Nom de la table>, 'field'=><Nom du champ>, (Optionnel)'alias'=><Alias du champ>, (Optionnel)'function'=><avg | count | distinct | max | min | sum>
// 'special' chaîne : 'count' ,$this->_nbResults sera mis à jour avec le nombre de résultats de la requête, sans limit ni offset et sans sort. $this->_nbResults sera également retourné
// 'join' => tableau de tableaux : 'type'=><inner | left | right | full>, 'table'=><Nom de la table>, 'on'=>['table1'=><Nom de la table>, 'field1'=><Nom du champ>, 'table2'=><Nom de la table>, 'field2'=><Nom du champ>]
// 'beforeWhere' => <Chaîne à insérer avant WHERE (INNER JOIN...)>
// 'afterWhere' => <Chaîne à insérer après WHERE (GROUP BY...)>
// 'groupBy' => tableau de tableaux : 'table'=><Nom de la table>, 'field'=><Nom du champ>
// 'start' => <Premier enregistrement retourné>
// 'limit' => <Nb enregistrements retournés> défaut: tout est retourné (limit doit être > 0 si start est > 0)
// 'search' => tableau de tableaux : 'table'=><Nom de la table>, 'field'=><Nom du champ>, 'operator'=>" < | > | <= | >= | = | != | in_array | not_in_array | fulltext | %fulltext | %fulltext% | fulltext% | like | not_like | %like | %not_like | %like% | %not_like% | like% | not_like% | is_null | is_not_null ", 'value'=><Valeur recherchée>
// 'sequence' => <Chaîne de séquencement du WHERE>
//      Par défaut, toutes les clauses 'search' du WHERE sont séquencées avec des AND, mais il est possible de renseigner la chaine 'sequence' pour personnaliser
//      Par exemple : '((WHERE1 AND WHERE2) OR (WHERE3 AND WHERE4))' Les clauses Where sont numérotées de 1 à n et sont dans l'ordre du tableau 'search'. Si 'sequence' est fourni, il faut y renseigner toutes les clauses 'search' du WHERE.
//      'sequence' ne doit comporter que les chaînes et caractères suivants en plus des WHEREn : '(', ')', ' ', 'OR', 'AND'
// 'sort' => tableau de tableaux : 'table'=><Nom de la table>, 'field'=><Nom du champ>, 'reverse'=><true | false>)

// exemple de recherche
$arg = array('start'=>0,'limit'=>0,'fields'=>array(),'search'=>array(),'sort'=>array());
$arg['start'] = 0;
$arg['limit'] = 10;
$arg['fields'][] = array('table'=>'clients','field'=>'IDclients');
$arg['fields'][] = array('table'=>'clients','field'=>'nom');
$arg['sort'][] = array('table'=>'clients','field'=>'nom','reverse'=>false);
search($arg);

Débogage:
Il est possible d'activer ou de désactiver le débogage avec $this->setDebug(TRUE | FALSE);
Quand il est actif, $this->debugTxt() contient la dernière requête et les éventuelles valeurs des binds
Le buffer de débogage est vidé lors de sa lecture : $this->debugTxt(), ou lors de son initialisation : $this->setDebug(TRUE | FALSE)

*/
namespace processid\manager;

abstract class Manager {
    protected $db;
    protected $crypt;
    protected $tableName;
    protected $tableIdField;
    protected $className; // Nom de la classe des objets retournés par get() et getList()
    protected $errorTxt;
    protected $debug = false;
    protected $debugTxt = '';
    private $_nbResults;
    private $_encryptedFields = array();
    private $_encryptedFieldsSortable = array();
    private $_encryptedFieldsSortableCreate = false;

    // La liste des champs est partagée entre toutes les classes qui héritent de la classe Manager (<table>Manager)
    private static $_fieldsList = array();

    public function setDb(\processid\manager\DbConnect $db) {
        $this->db = $db;
    }

    function tableName() { return $this->tableName; }
    function tableIdField() { return $this->tableIdField; }
    function className() { return $this->className; }
    function errorTxt() { return $this->errorTxt; }
    function encryptedFields() { return $this->_encryptedFields; }
    function encryptedFieldsSortable() { return $this->_encryptedFieldsSortable; }
    function encryptedFieldsSortableCreate() { return $this->_encryptedFieldsSortableCreate; }
    function nbResults() { return $this->_nbResults; }
    
    public function debug() { return $this->debug; }
    
    public function debugTxt() {
        $texte = $this->debugTxt;
        $this->debugTxt = '';
        return $texte;
    }
    
    public function setDebug($debug) {
        if (is_bool($debug)) {
            $this->debug = $debug;
            $this->debugTxt = '';
        }
    }
    
    private function setDebugTxt($texte) {
        if (strlen($this->debugTxt)) { $this->debugTxt .= "\n"; }
        $this->debugTxt .= $texte;
    }
    
    function fieldsList() {
        if (!isset(self::$_fieldsList[$this->tableName()]) || !is_array(self::$_fieldsList[$this->tableName()])) {
            $this->recordFields();
        }
        return self::$_fieldsList;
    }

    function setTableName($tableName) {
        $this->tableName = $tableName;
    }

    function setClassName($className) {
        $this->className = $className;
    }

    function setErrorTxt($errorTxt) {
        $this->errorTxt = $errorTxt;
    }

    function setTableIdField($tableIdField) {
        $this->tableIdField = $tableIdField;
    }

    function setEncryptedFields($encryptedFields) {;
        if (is_array($encryptedFields)) {
            $this->_encryptedFields = $encryptedFields;
        } else {
            $this->_encryptedFields = array();
        }
    }

    function setEncryptedFieldsSortable($encryptedFieldsSortable) {;
        if (is_array($encryptedFieldsSortable)) {
            $this->_encryptedFieldsSortable = $encryptedFieldsSortable;
        } else {
            $this->_encryptedFieldsSortable = array();
        }
    }

    function setEncryptedFieldsSortableCreate($encryptedFieldsSortableCreate) {;
        if ($encryptedFieldsSortableCreate) {
            $this->_encryptedFieldsSortableCreate = true;
        } else {
            $this->_encryptedFieldsSortableCreate = false;
        }
    }

    function setNbResults($nbResults) {
        $this->_nbResults = $nbResults;
    }

    function isEncrypted($field) {
        if (is_array($this->encryptedFields())) {
            return array_key_exists($field,$this->encryptedFields());
        }
    }

    function isEncryptedSortable($field) {
        if (is_array($this->encryptedFieldsSortable())) {
            return array_key_exists($field,$this->encryptedFieldsSortable());
        }
    }

    function fieldExists($field) {
        $list_fields = $this->fieldsList();

        if (!isset($list_fields[$this->tableName()]) || !is_array($list_fields[$this->tableName()])) {
            $this->recordFields();
            $list_fields = $this->fieldsList();
        }
        return array_key_exists($field,$list_fields[$this->tableName()]);
    }

    function recordFields() {
        $requete = 'SELECT COLUMN_NAME, COLUMN_DEFAULT, IS_NULLABLE, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, CHARACTER_OCTET_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE, COLUMN_KEY, EXTRA';
        $requete .= ' FROM information_schema.columns';
        $requete .= ' WHERE table_name=:tableName';
        $requete .= ' AND table_schema=DATABASE()';

        $query = $this->db->pdo()->prepare($requete);
        if ($query === false) {
            trigger_error('Erreur dans recordFields():prepare() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requete,E_USER_ERROR);
            return false;
        }

        $query->bindValue(':tableName', $this->tableName(), \PDO::PARAM_STR);

        $query->execute();
        if ($query === false) {
            trigger_error('Erreur dans recordFields():execute() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requete,E_USER_ERROR);
            return false;
        }

        self::$_fieldsList[$this->tableName()] = array();

        while ($results = $query->fetch(\PDO::FETCH_ASSOC)) {
            self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']] = array();
            self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['Field'] = $results['COLUMN_NAME'];                                         // Nom du champ
            self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['Type'] = strtolower($results['DATA_TYPE']);                                // Type de champs (bigint, varchar, int, date, decimal, ...)
            self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['Null'] = $results['IS_NULLABLE'];                                          // Peut être null (true | false)
            self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['Key'] = $results['COLUMN_KEY'];                                            // PRI, UNI, MUL
            self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['CharMaxLength'] = $results['CHARACTER_MAXIMUM_LENGTH'];                    // La taille max en caractères pour les chaînes
            self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['CharOctetLength'] = $results['CHARACTER_OCTET_LENGTH'];                    // La taille max en octets pour les chaînes
            self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['Precision'] = $results['NUMERIC_PRECISION'];                               // La précision pour les colonnes numériques
            self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['Precision'] = $results['NUMERIC_SCALE'];                                   // L'échelle pour les colonnes numériques
            self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['Default'] = $results['COLUMN_DEFAULT'];                                    // Valeur par défaut
            self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['Extra'] = $results['EXTRA'];                                               // auto_increment, ...
            self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['Encrypted'] = $this->isEncrypted($results['COLUMN_NAME']);                 // true | false
            self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['EncryptedSortable'] = $this->isEncryptedSortable($results['COLUMN_NAME']); // true | false

            if (strstr(strtolower($results['EXTRA']),'auto_increment') === false) {
                self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['auto_increment'] = false;
            } else {
                self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['auto_increment'] = true;
            }
        }
    }

    public function update(object $object, $ta_fields='') {
        if (is_array($ta_fields)) {
            $nb_fields = count($ta_fields);
        } else {
            if (strlen($ta_fields)) {
                $nb_fields = 1;
                $ta_fields = array($ta_fields);
            } else {
                $nb_fields = 0;
            }
        }

        $requete = 'UPDATE ' . $this->tableName() . ' SET ';
        // Boucle sur les champs pour les variables de bind
        $count = 0;
        $ta_tables = $this->fieldsList();
        foreach ($ta_tables[$this->tableName()] as $infos_field) {
            if (!$nb_fields || in_array($infos_field['Field'],$ta_fields)) {
                if (!$infos_field['auto_increment']) {
                    if ($count > 0) { $requete .= ','; }
                    $count++;
                    $requete .= ' ' . $infos_field['Field'] . '=:' . $infos_field['Field'];
                }
            }
        }
        $requete .= ' WHERE ' . $this->tableIdField() . ' = :' . $this->tableIdField();
        
        if ($this->debug()) {
            $this->setDebugTxt($requete);
        }
        
        $query = $this->db->pdo()->prepare($requete);
        if ($query === false) {
            $this->setErrorTxt('Erreur dans update():prepare() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requete);
            return false;
        }

        // Boucle sur les champs pour les bind
        foreach ($ta_tables[$this->tableName()] as $infos_field) {
            if (!$nb_fields || in_array($infos_field['Field'],$ta_fields)) {
                if (!$infos_field['auto_increment']) {
                    if ($infos_field['Encrypted']) {
                        $value = $this->db->dbCrypt()->encrypt_string($object->{$infos_field['Field']}());
                    } else {
                        $value = $object->{$infos_field['Field']}();
                    }
                    
                    if ($this->debug()) {
                        $this->setDebugTxt('bind:' . $infos_field['Field'] . ' = ' . $value . ' (' . $infos_field['Type'] . ')');
                    }
                    
                    $this->bind_query($query, ':' . $infos_field['Field'], $infos_field['Type'], $value);
                }
            }
        }

        // Ajout du champ ID
        $field = $this->tableIdField();
        if ($this->debug()) {
            $this->setDebugTxt('bind:' . $this->tableIdField() . ' = ' . $object->$field() . ' (INTEGER)');
        }
        $query->bindValue(':'.$this->tableIdField(), $object->$field(), \PDO::PARAM_INT);

        // Trouvé tous les champs?
        if ($nb_fields && $count != $nb_fields) {
            foreach ($ta_fields as $field) {
                if (!$this->fieldExists($field)) {
                    trigger_error('Le champ ' . $field . ' n\'existe pas dans la table ' . $this->tableName(),E_USER_ERROR);
                }
            }
        }

        $query->execute();
        if ($query === false) {
            $this->setErrorTxt('Erreur dans update():execute() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requete);
            return false;
        }
        
        return $query->rowCount();
    }




    function bind_query($query, $bind, $type, $value) {
        if (in_array($type, array('bigint', 'int', 'tinyint', 'smallint', 'mediumint', 'time', 'year'))) {
            $query->bindValue($bind, (int) $value, \PDO::PARAM_INT);
        }
        elseif (in_array($type, array('date', 'datetime', 'timestamp', 'char', 'varchar'))) {
            $query->bindValue($bind, $value, \PDO::PARAM_STR);
        }
        elseif ($type == 'enum') {
            $query->bindValue($bind, $value, \PDO::PARAM_STR);
        }
        elseif (in_array($type, array('tinyblob', 'tinytext', 'blob', 'text', 'mediumblob', 'mediumtext', 'longblob', 'longtext'))) {
            $query->bindValue($bind, $value, \PDO::PARAM_LOB);
        }
        elseif ($type == 'float') {
            $query->bindValue($bind, (float) $value, \PDO::PARAM_STR);
        }
        elseif ($type == 'double') {
            $query->bindValue($bind, (double) $value, \PDO::PARAM_STR);
        }
        elseif ($type == 'decimal') {
            $query->bindValue($bind, $value, \PDO::PARAM_STR);
        }
        else {
            trigger_error('Champ de type inconnu : ' . $type . ' dans la classe : ' . $this->className(),E_USER_NOTICE);
            $query->bindValue($bind, $value);
        }
    }



    public function add(object $object, $ignore = false) {
        if ($ignore) {
            $requete = 'INSERT IGNORE INTO ';
        } else {
            $requete = 'INSERT INTO ';
        }
        $requete .= $this->tableName() . ' (';

        // Boucle sur les champs pour les noms des champs
        $count = 0;
        $ta_tables = $this->fieldsList();
        foreach ($ta_tables[$this->tableName()] as $infos_field) {
            if (!$infos_field['auto_increment']) {
                if ($count > 0) { $requete .= ','; }
                $count++;
                $requete .= $infos_field['Field'];
            }
        }
        $requete .= ') VALUES (';
        // Boucle sur les champs pour les variables de bind
        $count = 0;
        foreach ($ta_tables[$this->tableName()] as $infos_field) {
            if (!$infos_field['auto_increment']) {
                if ($count > 0) { $requete .= ','; }
                $count++;
                $requete .= ':' . $infos_field['Field'];
            }
        }
        $requete .= ')';
        
        if ($this->debug()) {
            $this->setDebugTxt($requete);
        }
        
        $query = $this->db->pdo()->prepare($requete);
        if ($query === false) {
            $this->setErrorTxt('Erreur dans add():prepare() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requete);
            return false;
        }

        // Boucle sur les champs pour les bind
        foreach ($ta_tables[$this->tableName()] as $infos_field) {
            if (!$infos_field['auto_increment']) {
                if ($infos_field['Encrypted']) {
                    $value = $this->db->dbCrypt()->encrypt_string($object->{$infos_field['Field']}());
                } else {
                    $value = $object->{$infos_field['Field']}();
                }
                
                if ($this->debug()) {
                    $this->setDebugTxt('bind:' . $infos_field['Field'] . ' = ' . $value . ' (' . $infos_field['Type'] . ')');
                }
                
                $this->bind_query($query, ':' . $infos_field['Field'], $infos_field['Type'], $value);

            }
        }

        $query->execute();
        if ($query === false) {
            $this->setErrorTxt('Erreur dans add():execute() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requete);
            return false;
        }
        
        if ($ignore && !$query->rowCount()) {
            return 0;
        }
        
        $setterID = 'set' . ucfirst($this->tableIdField);
        $lastInsertId = intval($this->db->pdo()->lastInsertId());
        $object->$setterID($lastInsertId);
        return $lastInsertId;
    }



    public function get(int $ID, $ta_fields='*') {
        $ID = intval($ID);
        if ($ID) {
            $fields = '';
            if (is_array($ta_fields)) {
                foreach ($ta_fields as $field) {
                    if ($fields != '') { $fields .= ','; }
                    if ($this->fieldExists($field)) {
                        $fields .= $field;
                    } else {
                        trigger_error('Champ inconnu : ' . $field . ' dans la classe : ' . $this->className,E_USER_ERROR);
                    }
                }
            } else {
                if ($ta_fields == '*' || $this->fieldExists($ta_fields)) {
                    $fields .= $ta_fields;
                } else {
                    trigger_error('Champ inconnu : ' . $ta_fields . ' dans la classe : ' . $this->className,E_USER_ERROR);
                }
            }
            
            $requete = 'SELECT ' . $fields . ' FROM ' . $this->tableName() . ' WHERE ' . $this->tableIdField() . '=:'.$this->tableIdField();
            if ($this->debug()) {
                $this->setDebugTxt($requete);
            }
            $query = $this->db->pdo()->prepare($requete);
            if ($query === false) {
                $this->setErrorTxt('Erreur dans get():prepare() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requete . ' - :id=' . $ID);
                return false;
            }
            
            if ($this->debug()) {
                $this->setDebugTxt('bind:' . $this->tableIdField() . ' = ' . $ID . ' (INTEGER)');
            }
            $query->bindValue(':'.$this->tableIdField(), $ID, \PDO::PARAM_INT);
            if ($query === false) {
                trigger_error(implode(' - ', $this->db->pdo()->errorInfo()) . ' - Error during bindValue() of ' . $requete . ' - :id=' . $ID,E_USER_ERROR);
            }
            
            $query->execute();
            if ($query === false) {
                $this->setErrorTxt('Erreur dans get():execute() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requete . ' - :id=' . $ID);
                return false;
            }

            if ($results = $query->fetch(\PDO::FETCH_ASSOC)) {
                // Champs à déchiffrer
                $ta_fields_to_decrypt = array_intersect_key($results,$this->encryptedFields());
                foreach ($ta_fields_to_decrypt as $key=>$field_to_decrypt) {
                    $results[$key] = $this->db->dbCrypt()->decrypt_string($results[$key]);
                }
                return new $this->className($results);
            } else {
                return false;
            }
        } else {
            //error_reporting(-1);
            trigger_error('L\'ID doit etre un entier > 0',E_USER_ERROR);
        }
    }



    public function getList(array $ta_IDs, $ta_fields='*') {
        if (is_array($ta_IDs)) {
            $return = array();
            
            if (count($ta_IDs)) {
                $IDs = implode(",", array_map('intval', $ta_IDs));
                
                $fields = '';
                if (is_array($ta_fields)) {
                    foreach ($ta_fields as $field) {
                        if ($fields != '') { $fields .= ','; }
                        if ($this->fieldExists($field)) {
                            $fields .= $field;
                        } else {
                            trigger_error('Champ inconnu : ' . $field . ' dans la classe : ' . $this->className,E_USER_ERROR);
                        }
                    }
                } else {
                    if ($ta_fields == '*' || $this->fieldExists($ta_fields)) {
                        $fields .= $ta_fields;
                    } else {
                        trigger_error('Champ inconnu : ' . $ta_fields . ' dans la classe : ' . $this->className,E_USER_ERROR);
                    }
                }
                
                $requete = 'SELECT ' . $fields . ' FROM ' . $this->tableName() . ' WHERE ' . $this->tableIdField() . ' IN (' . $IDs . ') ORDER BY FIELD(' . $this->tableIdField() . ', ' . $IDs . ')';
                if ($this->debug()) {
                    $this->setDebugTxt($requete);
                }
                $query = $this->db->pdo()->prepare($requete);
                if ($query === false) {
                    $this->setErrorTxt('Erreur dans getList():prepare() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requete);
                    return false;
                }

                $query->execute();
                if ($query === false) {
                    $this->setErrorTxt('Erreur dans getList():execute() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requete);
                    return false;
                }

                while ($results = $query->fetch(\PDO::FETCH_ASSOC)) {
                    // Champs à déchiffrer
                    $ta_fields_to_decrypt = array_intersect_key($results,$this->encryptedFields());
                    foreach ($ta_fields_to_decrypt as $key=>$field_to_decrypt) {
                        $results[$key] = $this->db->dbCrypt()->decrypt_string($results[$key]);
                    }
                    $return[] = new $this->className($results);
                }
            }

            return $return;
        } else {
            trigger_error('$ta_ID doit etre un tableau d\'entiers',E_USER_ERROR);
        }
    }



    public function delete(int $ID) {
        $ID = intval($ID);
        if ($ID) {
            $requete = 'DELETE FROM ' . $this->tableName() . ' WHERE ' . $this->tableIdField() . ' = :'.$this->tableIdField();
            if ($this->debug()) {
                $this->setDebugTxt($requete);
            }
            $query = $this->db->pdo()->prepare($requete);
            if ($query === false) {
                $this->setErrorTxt('Erreur dans delete():prepare() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requete . ' - ID:'.$ID);
                return false;
            }
            
            if ($this->debug()) {
                $this->setDebugTxt('bind:' . $this->tableIdField() . ' = ' . $ID . ' (INTEGER');
            }
            $query->bindValue(':'.$this->tableIdField(), $ID, \PDO::PARAM_INT);

            $query->execute();
            if ($query === false) {
                $this->setErrorTxt('Erreur dans delete():execute() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requete . ' - ID:'.$ID);
                return false;
            }
        } else {
            trigger_error('L\'ID doit etre un entier > 0',E_USER_ERROR);
        }
    }


    public function search(array $arg = array()) {
        $return = array();
        
        // Par défaut, on retourne un tableau d'ID
        $flag_return_id = true;

        // Tableau de la structure des tables
        $ta_tables = $this->fieldsList();
        if (!isset($ta_tables[$this->tableName()]) || !is_array($ta_tables[$this->tableName()])) {
            $this->recordFields();
            $ta_tables = $this->fieldsList();
        }

        $flag_count = false;
        if (isset($arg['special'])) {
            if ($arg['special'] == 'count') {
                $flag_count = true;
            }
        }

        $fields = '';
        if (array_key_exists('fields',$arg) && is_array($arg['fields']) && count($arg['fields'])) {
            $flag_return_id = false;
            foreach ($arg['fields'] as $ta_field) {
                if ($fields != '') {
                    $fields .= ',';
                }
                if (!array_key_exists($ta_field['table'],$ta_tables)) {
                    $table = preg_replace('/ /','',ucwords(preg_replace('/_/',' ',$ta_field['table'])));
                    $classe = 'src\manager\\' . $table.'Manager';
                    $obj = new $classe($this->db);
                    $obj->recordFields();
                    $ta_tables = $this->fieldsList();
                    unset($obj);
                }
                if ($ta_field['field'] == '*') {
                    $fields .= '*';
                } elseif (!array_key_exists($ta_field['field'],$ta_tables[$ta_field['table']])) {
                    trigger_error('Le champ : ' . $ta_field['field'] . ' est introuvable dans la table : ' . $ta_field['table'],E_USER_ERROR);
                } else {
                    if (array_key_exists('function',$ta_field) && !empty($ta_field['function'])) {
                        if (!in_array($ta_field['function'],array('avg','count','distinct','max','min','sum'))) {
                            trigger_error('La fonction : ' . $ta_field['function'] . ' est inconnue',E_USER_ERROR);
                        }
                        $fields .= strtoupper($ta_field['function']) . '(' . $ta_field['table'] . '.' . $ta_field['field'] . ')';
                    } else {
                        $fields .= $ta_field['table'] . '.' . $ta_field['field'];
                    }

                    if (!empty($ta_field['alias'])) {
                        $fields .= ' AS ' . $ta_field['alias'];
                    }
                }
            }
        } else {
            $fields = $this->tableName() . '.' . $this->tableIdField();
        }

        $requete = 'SELECT ' . $fields;
        $requete .= ' FROM ' . $this->tableName() . ' ';

        // join
        if (array_key_exists('join',$arg) && is_array($arg['join']) && count($arg['join'])) {
            foreach ($arg['join'] as $ta_join) {
                if (!array_key_exists($ta_join['table'], $ta_tables)) {
                    $table = preg_replace('/ /', '', ucwords(preg_replace('/_/', ' ', $ta_join['table'])));
                    $classe = 'src\manager\\' . $table . 'Manager';
                    $obj = new $classe($this->db);
                    $obj->recordFields();
                    $ta_tables = $this->fieldsList();
                    unset($obj);
                }
                if (!array_key_exists($ta_join['on']['table1'], $ta_tables)) {
                    $table = preg_replace('/ /', '', ucwords(preg_replace('/_/', ' ', $ta_join['on']['table1'])));
                    $classe = 'src\manager\\' . $table . 'Manager';
                    $obj = new $classe($this->db);
                    $obj->recordFields();
                    $ta_tables = $this->fieldsList();
                    unset($obj);
                }
                if (!array_key_exists($ta_join['on']['table2'], $ta_tables)) {
                    $table = preg_replace('/ /', '', ucwords(preg_replace('/_/', ' ', $ta_join['on']['table2'])));
                    $classe = 'src\manager\\' . $table . 'Manager';
                    $obj = new $classe($this->db);
                    $obj->recordFields();
                    $ta_tables = $this->fieldsList();
                    unset($obj);
                }
                if (!array_key_exists($ta_join['on']['table1'], $ta_tables)) {
                    trigger_error('La table : ' . $ta_join['on']['table1'] . ' est introuvable', E_USER_ERROR);
                }
                if (!array_key_exists($ta_join['on']['table2'], $ta_tables)) {
                    trigger_error('La table : ' . $ta_join['on']['table2'] . ' est introuvable', E_USER_ERROR);
                }
                if (!array_key_exists($ta_join['on']['field1'], $ta_tables[$ta_join['on']['table1']])) {
                    trigger_error('Le champ : ' . $ta_join['on']['field1'] . ' est introuvable dans la table : ' . $ta_join['on']['table1'], E_USER_ERROR);
                }
                if (!array_key_exists($ta_join['on']['field2'], $ta_tables[$ta_join['on']['table2']])) {
                    trigger_error('Le champ : ' . $ta_join['on']['field2'] . ' est introuvable dans la table : ' . $ta_join['on']['table2'], E_USER_ERROR);
                }

                $requete .= ' ' . strtoupper($ta_join['type']) . ' JOIN ' . $ta_join['table'] . ' ON ' . $ta_join['on']['table1'] . '.' . $ta_join['on']['field1'] . '=' . $ta_join['on']['table2'] . '.' . $ta_join['on']['field2'];
            }
        }


        // beforeWhere
        if (array_key_exists('beforeWhere',$arg)) {
            $requete .= $arg['beforeWhere'] . ' ';
        }

        // search
        if (!array_key_exists('search',$arg) || !is_array($arg['search'])) {
            $arg['search'] = array();
        }
        
        $countBind = 0;
        $ta_bind = array();
        $ta_where = array();

        foreach ($arg['search'] as $ta_search) {
            if (!array_key_exists($ta_search['table'],$ta_tables)) {
                $table = preg_replace('/ /','',ucwords(preg_replace('/_/',' ',$ta_search['table'])));
                $classe = 'src\manager\\' . $table.'Manager';
                //$classe = 'src\manager\\' . $ta_search['table'].'Manager';
                $obj = new $classe($this->db);
                $obj->recordFields();
                $ta_tables = $this->fieldsList();
                unset($obj);
            }
            if (!array_key_exists($ta_search['field'],$ta_tables[$ta_search['table']])) {
                trigger_error('Le champ : ' . $ta_search['field'] . ' est introuvable dans la table : ' . $ta_search['table'],E_USER_ERROR);
            }
            if (!in_array($ta_search['operator'],array('<','>','<=','>=','=','!=','in_array','not_in_array','fulltext','%fulltext','%fulltext%','fulltext%','like','not_like','%like','%not_like','%like%','%not_like%','like%','not_like%','is_null','is_not_null'))) {
                trigger_error('Operateur inconnu : ' . $ta_search['operator'],E_USER_ERROR);
            }
            if (in_array($ta_search['operator'],array('fulltext','%fulltext','%fulltext%','fulltext%'))) {
                if (preg_match('#^id[0-9]{1,}$#', $ta_search['value'])) {
                    $ta_bind[$countBind] = array();
                    $ta_bind[$countBind]['table'] = $ta_search['table'];
                    $ta_bind[$countBind]['field'] = $this->tableIdField();
                    $ta_bind[$countBind]['value'] = (int) trim(substr($ta_search['value'], 2));
                    $ta_where[] = $ta_search['table'] . '.' . $this->tableIdField() . '=:bind' . $countBind++;
                } else {
                    $recStr = preg_replace("/[[:punct:]]/u", " ", $ta_search['value']);
                    $recStr = preg_replace("/[[:space:]]{2,}/u", " ", $recStr);
                    $array_rec = explode (" ", trim($recStr));
                    $countMots = 0;
                    $condition_tmp = ' (';
                    foreach ($array_rec as $mot) {
                        if ($countMots > 0) {
                            $condition_tmp .= ' AND ';
                        }
                        $ta_bind[$countBind] = array();
                        $ta_bind[$countBind]['table'] = $ta_search['table'];
                        $ta_bind[$countBind]['field'] = $ta_search['field'];
                        if ($ta_search['operator'] == 'fulltext') { $ta_bind[$countBind]['value'] = $mot; }
                        elseif ($ta_search['operator'] == '%fulltext') { $ta_bind[$countBind]['value'] = '%' . $mot; }
                        elseif ($ta_search['operator'] == '%fulltext%') { $ta_bind[$countBind]['value'] = '%' . $mot . '%'; }
                        elseif ($ta_search['operator'] == 'fulltext%') { $ta_bind[$countBind]['value'] = $mot . '%'; }

                        $condition_tmp .= $ta_search['table'] . '.' . $ta_search['field'] . ' LIKE :bind' . $countBind++;
                        $countMots++;
                    }
                    $condition_tmp .= ' )';
                    $ta_where[] = $condition_tmp;
                }
            } elseif (in_array($ta_search['operator'],array('like','not_like','%like','%not_like','%like%','%not_like%','like%','not_like%'))) {
                $ta_bind[$countBind] = array();
                $ta_bind[$countBind]['table'] = $ta_search['table'];
                $ta_bind[$countBind]['field'] = $ta_search['field'];
                if ($ta_search['operator'] == 'like') { $ta_bind[$countBind]['value'] = $ta_search['value']; }
                elseif ($ta_search['operator'] == '%like') { $ta_bind[$countBind]['value'] = '%' . $ta_search['value']; }
                elseif ($ta_search['operator'] == '%like%') { $ta_bind[$countBind]['value'] = '%' . $ta_search['value'] . '%'; }
                elseif ($ta_search['operator'] == 'like%') { $ta_bind[$countBind]['value'] = $ta_search['value'] . '%'; }
                $not = '';
                if (in_array($ta_search['operator'],array('not_like','%not_like','%not_like%','not_like%'))) {
                    $not = ' NOT';
                }
                $ta_where[] = $ta_search['table'] . '.' . $ta_search['field'] . $not . ' LIKE :bind' . $countBind++;
            } elseif (in_array($ta_search['operator'],array('in_array','not_in_array'))) {
                if (is_array($ta_search['value'])) {
                    $IDs = '';
                    if (count($ta_search['value'])) {
                        foreach ($ta_search['value'] as $value) {
                            $ta_bind[$countBind] = array();
                            $ta_bind[$countBind]['table'] = $ta_search['table'];
                            $ta_bind[$countBind]['field'] = $ta_search['field'];
                            $ta_bind[$countBind]['value'] = $value;
                            $IDs .= ':bind' . $countBind++ . ',';
                        }
                        $IDs = rtrim($IDs,",");
                    }
                    $not = '';
                    if ($ta_search['operator'] == 'not_in_array') {
                        $not = ' NOT';
                    }
                    $ta_where[] = $ta_search['table'] . '.' . $ta_search['field'] . $not . ' IN (' . $IDs . ')';
                }
            } elseif ($ta_search['operator'] == 'is_null') {
                $ta_where[] = $ta_search['table'] . '.' . $ta_search['field'] . ' IS NULL';
            } elseif ($ta_search['operator'] == 'is_not_null') {
                $ta_where[] = $ta_search['table'] . '.' . $ta_search['field'] . ' IS NOT NULL';
            } else {
                $ta_bind[$countBind] = array();
                $ta_bind[$countBind]['table'] = $ta_search['table'];
                $ta_bind[$countBind]['field'] = $ta_search['field'];
                $ta_bind[$countBind]['value'] = $ta_search['value'];
                $ta_where[] = $ta_search['table'] . '.' . $ta_search['field'] . ' ' . $ta_search['operator'] . ' :bind' . $countBind++;
            }
        }
        
        // Séquencement des clauses WHERE
        $count = 0;
        if (count($ta_where)) {
            $requete .= ' WHERE ';
            if (!isset($arg['sequence']) || !strlen($arg['sequence'])) {
                foreach ($ta_where as $where) {
                    if ($count++) { $requete .= ' AND '; }
                    $requete .= $where;
                }
            } else {
                $chaine_where = $arg['sequence'];
                // Vérification des caractères de la séquence
                $verif_sequence = preg_replace('#WHERE[0-9]{1,}|[\(]|[\)]|[ ]|OR|AND#','',$chaine_where);
                if (strlen($verif_sequence)) {
                    trigger_error('Caractères indésirables dans la séquence de WHERE : ' . $verif_sequence,E_USER_ERROR);
                }
                foreach ($ta_where as $where) {
                    $count++;
                    $chaine_where = preg_replace('#WHERE' . $count . '\b#', $where, $chaine_where);
                }
                $requete .= $chaine_where;
            }
        }

        // afterWhere
        if (array_key_exists('afterWhere',$arg)) {
            $requete .= ' ' . $arg['afterWhere'] . ' ';
        }

        // Group by
        if (array_key_exists('groupBy',$arg) && is_array($arg['groupBy']) && count($arg['groupBy'])) {
            $requete .= ' GROUP BY ';
            $count = 0;
            foreach ($arg['groupBy'] as $ta_groupBy) {
                if (!array_key_exists($ta_groupBy['table'],$ta_tables)) {
                    $table = preg_replace('/ /','',ucwords(preg_replace('/_/',' ',$ta_groupBy['table'])));
                    $classe = 'src\manager\\' . $table.'Manager';
                    //$classe = 'src\manager\\' . ucfirst($ta_groupBy['table']).'Manager';
                    $obj = new $classe($this->db);
                    $obj->recordFields();
                    $ta_tables = $this->fieldsList();
                    unset($obj);
                }
                if (!array_key_exists($ta_groupBy['field'],$ta_tables[$ta_groupBy['table']])) {
                    trigger_error('Le champ : ' . $ta_groupBy['field'] . ' est introuvable dans la table : ' . $ta_groupBy['table'],E_USER_ERROR);
                }
                if ($count++) { $requete .= ', '; }
                $requete .= $ta_groupBy['table'] . '.' . $ta_groupBy['field'];
            }
        }

        if (!$flag_count) {
            // Tris
            if (array_key_exists('sort',$arg)) {
                if (is_array($arg['sort'])) {
                    $count = 0;
                    foreach ($arg['sort'] as $ta_sort) {
                        if (!array_key_exists($ta_sort['table'],$ta_tables)) {
                            $table = preg_replace('/ /','',ucwords(preg_replace('/_/',' ',$ta_sort['table'])));
                            $classe = 'src\manager\\' . $table.'Manager';
                            //$classe = 'src\manager\\' . ucfirst($ta_sort['table']).'Manager';
                            $obj = new $classe($this->db);
                            $obj->recordFields();
                            $ta_tables = $this->fieldsList();
                            unset($obj);
                        }

                        // Si le champ est chiffré et qu'il a une colonne de tri, elle est utilisée
                        if ($this->isEncrypted($ta_sort['field']) && $this->isEncryptedSortable($ta_sort['field'])) {
                            $ta_sort['field'] = $ta_sort['field'] . '_tri';
                        }

                        if (!array_key_exists($ta_sort['field'],$ta_tables[$ta_sort['table']])) {
                            trigger_error('Le champ : ' . $ta_sort['field'] . ' est introuvable dans la table : ' . $ta_sort['table'],E_USER_ERROR);
                        }
                        if ($count++) { $requete .= ', '; } else { $requete .= ' ORDER BY '; }
                        $requete .= $ta_sort['table'] . '.' . $ta_sort['field'];
                        if ($ta_sort['reverse']) {
                            $requete .= ' DESC';
                        }
                    }
                }
            }

            // Limites
            if (!array_key_exists('limit',$arg) || !$arg['limit']) {
                $arg['limit'] = 0;
            }

            if (!array_key_exists('start',$arg) || !$arg['start']) {
                $arg['start'] = 0;
            }

            // LIMIT doit être > 0 si OFFSET est utilisé
            if ($arg['limit'] == 0 && $arg['start'] > 0) {
                trigger_error('limit doit être > 0 si start est > 0',E_USER_ERROR);
            }

            if ($arg['limit']) {
                $requete .= ' LIMIT ' . (int) $arg['limit'];
            }

            if ($arg['start']) {
                $requete .= ' OFFSET ' . (int) $arg['start'];
            }
        }
        
        if ($flag_count) {
            $requete = 'SELECT COUNT(1) FROM (' . addslashes($requete) . ') x';
        }
        
        if ($this->debug()) {
            $this->setDebugTxt($requete);
        }
        $query = $this->db->pdo()->prepare($requete);
        if ($query === false) {
            $this->setErrorTxt('Erreur dans search():prepare() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requete);
            return false;
        }

        // Bind
        foreach ($ta_bind as $key=>$infos_bind) {
            $type = $ta_tables[$infos_bind['table']][$infos_bind['field']]['Type'];
            if ($this->debug()) {
                $this->setDebugTxt('bind:' . $key . ' = ' . $infos_bind['value'] . ' (' . $type . ')');
            }
            $this->bind_query($query, ':bind' . $key, $type, $infos_bind['value']);
        }
        
        $query->execute();
        if ($query === false) {
            $this->setErrorTxt('Erreur dans search():execute() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requete);
            return false;
        }

        if ($flag_count) {
            $results = $query->fetchColumn();
            $this->setNbResults($results);
            return $this->nbResults();
        } else {
            while ($results = $query->fetch(\PDO::FETCH_ASSOC)) {
                
                if ($flag_return_id) {
                    $return[] = intval($results[$this->tableIdField()]);
                } else {
                    // Champs à déchiffrer
                    $ta_fields_to_decrypt = array_intersect_key($results,$this->encryptedFields());
                    foreach ($ta_fields_to_decrypt as $key=>$field_to_decrypt) {
                        $results[$key] = $this->db->dbCrypt()->decrypt_string($results[$key]);
                    }
                    //$return[] = new $this->className($results);
                    $return[] = $results;
                }
            }
        }
        
        return $return;
    }

    public function encrypt_column(string $field) {
        if (!$this->fieldExists($field)) {
            trigger_error('Le champ ' . $field . ' n\'existe pas dans la table ' . $this->tableName(),E_USER_ERROR);
        }

        // La colonne doit être en texte
        $list_fields = $this->fieldsList();
        $type = $list_fields[$this->tableName()][$field]['Type'];
        if ($type != 'char' && $type != 'varchar' && $type != 'tinyblob' && $type != 'tinytext' && $type != 'blob' && $type != 'text' && $type != 'mediumblob' && $type != 'mediumtext' && $type != 'longblob' && $type != 'longtext') {
            trigger_error('Le champ ' . $field . ' doit etre au format texte',E_USER_ERROR);
        }

        // On boucle sur tous les enregistrements pour chiffrer la colonne
        $requete = 'SELECT ' . $this->tableIdField() . ', ' . $list_fields[$this->tableName()][$field]['Field'] . ' FROM ' . $this->tableName() . ' ORDER BY ' . $this->tableIdField();
        $query2 = $this->db->pdo()->prepare($requete);
        $query2->execute();

        $requete = 'UPDATE ' . $this->tableName() . ' SET ';
        $requete .= ' ' . $list_fields[$this->tableName()][$field]['Field'] . '=:value';
        $requete .= ' WHERE ' . $this->tableIdField() . ' = :'.$this->tableIdField();
        $query = $this->db->pdo()->prepare($requete);

        while ($results = $query2->fetch(\PDO::FETCH_ASSOC)) {
            $value = $this->db->dbCrypt()->encrypt_string($results[$list_fields[$this->tableName()][$field]['Field']]);
            if (strlen($value) > $list_fields[$this->tableName()][$field]['CharOctetLength']) {
                trigger_error('Le champ ' . $field . ' est trop petit, la chaine sera tronquee : ' . strlen($value) . ' > ' . $list_fields[$this->tableName()][$field]['CharOctetLength'],E_USER_NOTICE);
            }

            $query->bindValue(':value', $value, \PDO::PARAM_STR);

            $query->bindValue(':'.$this->tableIdField(), (int) $results[$this->tableIdField()], \PDO::PARAM_INT);

            $query->execute();
            if (!$query) {
                print_r($this->db->pdo()->errorInfo());
            }
        }
    }

    public function decrypt_column(string $field) {
        if (!$this->fieldExists($field)) {
            trigger_error('Le champ ' . $field . ' n\'existe pas dans la table ' . $this->tableName(),E_USER_ERROR);
        }

        // La colonne doit être en texte
        $list_fields = $this->fieldsList();
        $type = $list_fields[$this->tableName()][$field]['Type'];
        if ($type != 'char' && $type != 'varchar' && $type != 'tinyblob' && $type != 'tinytext' && $type != 'blob' && $type != 'text' && $type != 'mediumblob' && $type != 'mediumtext' && $type != 'longblob' && $type != 'longtext') {
            trigger_error('Le champ ' . $field . ' doit etre au format texte',E_USER_ERROR);
        }

        // On boucle sur tous les enregistrements pour déchiffrer la colonne
        $requete = 'SELECT ' . $this->tableIdField() . ', ' . $list_fields[$this->tableName()][$field]['Field'] . ' FROM ' . $this->tableName() . ' ORDER BY ' . $this->tableIdField();
        $query2 = $this->db->pdo()->prepare($requete);
        $query2->execute();

        $requete = 'UPDATE ' . $this->tableName() . ' SET ';
        $requete .= ' ' . $list_fields[$this->tableName()][$field]['Field'] . '=:value';
        $requete .= ' WHERE ' . $this->tableIdField() . ' = :'.$this->tableIdField();
        $query = $this->db->pdo()->prepare($requete);

        while ($results = $query2->fetch(\PDO::FETCH_ASSOC)) {
            $value = $this->db->dbCrypt()->decrypt_string($results[$list_fields[$this->tableName()][$field]['Field']]);
            $query->bindValue(':value', $value, \PDO::PARAM_STR);

            $query->bindValue(':'.$this->tableIdField(), (int) $results[$this->tableIdField()], \PDO::PARAM_INT);

            $query->execute();
            if (!$query) {
                print_r($this->db->pdo()->errorInfo());
            }
        }
    }


    // Crée les champs de tri manquants des champs chiffrés
    public function create_encrypted_fields_sortable() {
        foreach ($this->encryptedFieldsSortable() as $key=>$value) {
            if ($value) {
                if (!$this->fieldExists($key . '_tri')) {
                    $field = $key . '_tri';

                    trigger_error('Creation du champ de tri ' . $field . ' sur la table ' . $this->tableName(),E_USER_NOTICE);
                    $query = $this->db->pdo()->prepare('ALTER TABLE ' . $this->tableName() . ' ADD ' . $field . ' BIGINT DEFAULT 0');
                    $query->execute();
                    $query = $this->db->pdo()->prepare('CREATE INDEX `IDX_' . $key . '_tri` ON `' . $this->tableName() . '` (`' . $key . '_tri`);');
                    $query->execute();
                }
            }
        }
    }

}
?>
