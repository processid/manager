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
$this->setClassName('\project\model\Clients'); // Nom de la classe gérant l'objet fourni au manager
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
$this->add(\project\model\Clients $obj);

// READ:
// get() retourne une instance de \project\model\Clients
// $ID est la valeur recherchée de IDclients
// Paramètre optionnel : $champs est un champ ex: 'nom', un tableau de champs ex: array('nom','tel') ou '*'
// Par défaut, si $champs n'est pas fourni, tous les champs sont hydratés
$this->get($ID, $champs);

// getList() retourne un tableau d'instances de \project\model\Clients
// $ta_IDs est un tableau d'IDclients
// Paramètre optionnel : $champs est un champ ex: 'nom', un tableau de champs ex: array('nom','tel') ou '*'
// Par défaut, si $champs n'est pas fourni, tous les champs sont hydratés
$this->getList($ta_IDs, $champs);

// UPDATE
// Paramètre optionnel : $champs est un champ ex: 'nom' ou un tableau de champs ex: array('nom','tel')
// Par défaut, si $champs n'est pas fourni, tous les champs sont mis à jour
$this->update($object, [$champs]);

// DELETE
$this->delete($ID);

SEARCH:
// search() retourne un tableau d'instances de \project\model\Clients 
// $arg : tableau associatif
// 'fields' => tableau de tableaux des champs à retourner : 'table'=><Nom de la table>, 'field'=><Nom du champ>
// 'special' chaîne : 'count' ,$this->_nbResults sera mis à jour avec le nombre de résultats de la requête, sans limit ni offset et sans sort. $this->_nbResults sera également retourné
// 'beforeWhere' => <Chaîne à insérer avant WHERE (INNER JOIN...)>
// 'afterWhere' => <Chaîne à insérer après WHERE (GROUP BY...)>
// 'start' => <Premier enregistrement retourné>
// 'limit' => <Nb enregistrements retournés> défaut: tout est retourné (limit doit être > 0 si start est > 0)
// 'search' => tableau de tableaux : 'table'=><Nom de la table>, 'field'=><Nom du champ>, 'operator'=>" < | > | <= | >= | = | in_array | fulltext %fulltext %fulltext% fulltext% like %like %like% like% ", 'value'=><Valeur recherchée>
// 'sort' => tableau de tableaux : 'table'=><Nom de la table>, 'field'=><Nom du champ>, 'reverse'=><true | false>)

// exemple de recherche
$arg = array('start'=>0,'limit'=>0,'fields'=>array(),'search'=>array(),'sort'=>array());
$arg['start'] = 0;
$arg['limit'] = 10;
$arg['fields'][] = array('table'=>'clients','field'=>'IDclients');
$arg['fields'][] = array('table'=>'clients','field'=>'nom');
$arg['sort'][] = array('table'=>'clients','field'=>'nom','reverse'=>false);
search($arg);

*/
namespace processid\manager;

abstract class Manager {
    protected $db;
    protected $crypt;
    protected $tableName;
    protected $tableIdField;
    protected $className; // Nom de la classe des objets retournés par get() et getList()
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
    function encryptedFields() { return $this->_encryptedFields; }
    function encryptedFieldsSortable() { return $this->_encryptedFieldsSortable; }
    function encryptedFieldsSortableCreate() { return $this->_encryptedFieldsSortableCreate; }
    function nbResults() { return $this->_nbResults; }

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

        $query->bindValue(':tableName', $this->tableName(), \PDO::PARAM_STR);

        $query->execute();

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

    public function update($object, $ta_fields='*') {
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
        $requete .= ' WHERE ' . $this->tableIdField() . ' = :ID';

        $query = $this->db->pdo()->prepare($requete);

        // Boucle sur les champs pour les bind
        foreach ($ta_tables[$this->tableName()] as $infos_field) {
            if (!$nb_fields || in_array($infos_field['Field'],$ta_fields)) {
                if (!$infos_field['auto_increment']) {
                    if ($infos_field['Encrypted']) {
                        $value = $this->db->dbCrypt()->encrypt_string($object->{$infos_field['Field']}());
                    } else {
                        $value = $object->{$infos_field['Field']}();
                    }

                    $this->bind_query($query, ':' . $infos_field['Field'], $infos_field['Type'], $value);
                }
            }
        }

        // Ajout du champ ID
        $field = $this->tableIdField();
        $query->bindValue(':ID', $object->$field(), \PDO::PARAM_INT);

        // Trouvé tous les champs?
        if ($nb_fields && $count != $nb_fields) {
            foreach ($ta_fields as $field) {
                if (!$this->fieldExists($field)) {
                    trigger_error('Le champ ' . $field . ' n\'existe pas dans la table ' . $this->tableName(),E_USER_ERROR);
                }
            }
        }

        $query->execute();
        if (!$query) {
            print_r($this->db->pdo()->errorInfo());
        }
    }




    function bind_query($query, $bind, $type, $value) {
        if (in_array($type, array('bigint', 'int', 'tinyint', 'smallint', 'mediumint', 'timestamp', 'time', 'year'))) {
            //echo ($bind . ' : Bind entier : ' . $value . '<br>');
            $query->bindValue($bind, (int) $value, \PDO::PARAM_INT);
        }
        elseif (in_array($type, array('date', 'datetime', 'char', 'varchar'))) {
            //echo ($bind . ' : Bind chaine : ' . $value . '<br>');
            $query->bindValue($bind, $value, \PDO::PARAM_STR);
        }
        elseif (in_array($type, array('tinyblob', 'tinytext', 'blob', 'text', 'mediumblob', 'mediumtext', 'longblob', 'longtext'))) {
            //echo ($bind . ' : Bind bloc texte : ' . $value . '<br>');
            $query->bindValue($bind, $value, \PDO::PARAM_LOB);
        }
        elseif (in_array($type, array('decimal', 'float'))) {
            //echo ($bind . ' : Bind decimal : ' . $value . '<br>');
            $query->bindValue($bind, (float) $value);
        }
        elseif ($type == 'double') {
            //echo ($bind . ' : Bind double : ' . $value . '<br>');
            $query->bindValue($bind, (double) $value);
        }
        else {
            trigger_error('Champ de type inconnu : ' . $type . ' dans la classe : ' . $this->className(),E_USER_NOTICE);
            $query->bindValue($bind, $value);
        }
    }



    public function add($object) {
        $requete = 'INSERT INTO ' . $this->tableName() . ' (';

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

        $query = $this->db->pdo()->prepare($requete);

        // Boucle sur les champs pour les bind
        foreach ($ta_tables[$this->tableName()] as $infos_field) {
            if (!$infos_field['auto_increment']) {
                if ($infos_field['Encrypted']) {
                    $value = $this->db->dbCrypt()->encrypt_string($object->{$infos_field['Field']}());
                } else {
                    $value = $object->{$infos_field['Field']}();
                }

                $this->bind_query($query, ':' . $infos_field['Field'], $infos_field['Type'], $value);

            }
        }

        $query->execute();
        if (!$query) {
            print_r($this->db->pdo()->errorInfo());
        }

        $setterID = 'set' . ucfirst($this->tableIdField);
        $object->$setterID($this->db->pdo()->lastInsertId());
    }



    public function get($ID, $ta_fields='*') {
        if (is_int($ID)) {
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

            $query = $this->db->pdo()->prepare('SELECT ' . $fields . ' FROM ' . $this->tableName() . ' WHERE ' . $this->tableIdField() . '=:id');
            $query->bindValue(':id', $ID, \PDO::PARAM_INT);

            $query->execute();

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
            trigger_error('L\'ID doit etre un entier',E_USER_ERROR);
        }
    }



    public function getList($ta_IDs, $ta_fields='*') {
        if (is_array($ta_IDs)) {
            $IDs = implode(",", array_map('intval', $ta_IDs));

            $return = array();

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

            $query = $this->db->pdo()->prepare('SELECT ' . $fields . ' FROM ' . $this->tableName() . ' WHERE ' . $this->tableIdField() . ' IN (' . $IDs . ')');

            $query->execute();

            while ($results = $query->fetch(\PDO::FETCH_ASSOC)) {
                // Champs à déchiffrer
                $ta_fields_to_decrypt = array_intersect_key($results,$this->encryptedFields());
                foreach ($ta_fields_to_decrypt as $key=>$field_to_decrypt) {
                    $results[$key] = $this->db->dbCrypt()->decrypt_string($results[$key]);
                }
                $return[] = new $this->className($results);
            }

            return $return;
        } else {
            trigger_error('$ta_ID doit etre un tableau d\'entiers',E_USER_ERROR);
        }
    }



    public function delete($ID) {
        if (is_int($ID)) {
            $query = $this->db->pdo()->prepare('DELETE FROM ' . $this->tableName() . ' WHERE ' . $this->tableIdField() . ' = :id');
            $query->bindValue(':id', $ID, \PDO::PARAM_INT);

            $query->execute();
        } else {
            trigger_error('L\'ID doit etre un entier',E_USER_ERROR);
        }
    }


    public function search($arg) {
        $return = array();

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
        foreach ($arg['fields'] as $ta_field) {
            if ($fields != '') {
                $fields .= ',';
            }
            if (!array_key_exists($ta_field['table'],$ta_tables)) {
                $classe = 'project\manager\\' . ucfirst($ta_field['table']).'Manager';
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
                $fields .= $ta_field['table'] . '.' . $ta_field['field'];
            }
        }

        $requete = 'SELECT ' . $fields;
        $requete .= ' FROM ' . $this->tableName() . ' ';

        // beforeWhere
        if (array_key_exists('beforeWhere',$arg)) {
            $requete .= $arg['beforeWhere'] . ' ';
        }

        // search
        if (array_key_exists('search',$arg)) {
            if (is_array($arg['search'])) {
                $countBind = 0;
                $ta_bind = array();
                $ta_where = array();

                foreach ($arg['search'] as $ta_search) {
                    if (!array_key_exists($ta_search['table'],$ta_tables)) {
                        $classe = 'project\manager\\' . ucfirst($ta_search['table']).'Manager';
                        $obj = new $classe($this->db);
                        $obj->recordFields();
                        $ta_tables = $this->fieldsList();
                        unset($obj);
                    }
                    if (!array_key_exists($ta_search['field'],$ta_tables[$ta_search['table']])) {
                        trigger_error('Le champ : ' . $ta_search['field'] . ' est introuvable dans la table : ' . $ta_search['table'],E_USER_ERROR);
                    }
                    if (!in_array($ta_search['operator'],array("<",">","<=",">=","=","in_array","fulltext","%fulltext","%fulltext%","fulltext%","like","%like","%like%","like%"))) {
                        trigger_error('Operateur inconnu : ' . $ta_search['operator'],E_USER_ERROR);
                    }
                    if ($ta_search['operator'] == 'fulltext' || $ta_search['operator'] == '%fulltext' || $ta_search['operator'] == '%fulltext%' || $ta_search['operator'] == 'fulltext%') {
                        if (preg_match('#^id[0-9]{1,}$#', $ta_search['value'])) {
                            $ta_bind[$countBind] = array();
                            $ta_bind[$countBind]['table'] = $ta_search['table'];
                            $ta_bind[$countBind]['field'] = $this->tableIdField();
                            $ta_bind[$countBind]['value'] = (int) trim(substr($ta_search['value'], 2));
                            $ta_where[] = $ta_search['table'] . '.' . $this->tableIdField() . '=:bind' . $countBind++;
                        } else {
                            $recStr = preg_replace("/[[:punct:]]/", " ", $ta_search['value']);
                            $recStr = preg_replace("/[[:space:]]{2,}/", " ", $recStr);
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

                                $condition_tmp .= $ta_search['table'] . '.' . $ta_search['field'] . ' like :bind' . $countBind++;
                                $countMots++;
                            }
                            $condition_tmp .= ' )';
                            $ta_where[] = $condition_tmp;
                        }
                    } elseif ($ta_search['operator'] == 'like' || $ta_search['operator'] == '%like' || $ta_search['operator'] == '%like%' || $ta_search['operator'] == 'like%') {
                        $ta_bind[$countBind] = array();
                        $ta_bind[$countBind]['table'] = $ta_search['table'];
                        $ta_bind[$countBind]['field'] = $ta_search['field'];
                        if ($ta_search['operator'] == 'like') { $ta_bind[$countBind]['value'] = $ta_search['value']; }
                        elseif ($ta_search['operator'] == '%like') { $ta_bind[$countBind]['value'] = '%' . $ta_search['value']; }
                        elseif ($ta_search['operator'] == '%like%') { $ta_bind[$countBind]['value'] = '%' . $ta_search['value'] . '%'; }
                        elseif ($ta_search['operator'] == 'like%') { $ta_bind[$countBind]['value'] = $ta_search['value'] . '%'; }
                        $ta_where[] = $ta_search['table'] . '.' . $ta_search['field'] . ' like :bind' . $countBind++;
                    } elseif ($ta_search['operator'] == 'in_array') {
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
                            $ta_where[] = $ta_search['table'] . '.' . $ta_search['field'] . ' IN (' . $IDs . ')';
                        }
                    } else {
                        $ta_bind[$countBind] = array();
                        $ta_bind[$countBind]['table'] = $ta_search['table'];
                        $ta_bind[$countBind]['field'] = $ta_search['field'];
                        $ta_bind[$countBind]['value'] = $ta_search['value'];
                        $ta_where[] = $ta_search['table'] . '.' . $ta_search['field'] . ' ' . $ta_search['operator'] . ':bind' . $countBind++;
                    }
                }

                $count = 0;
                if (count($ta_where)) {
                    $requete .= ' WHERE ';
                    foreach ($ta_where as $where) {
                        if ($count++) { $requete .= ' AND '; }
                        $requete .= $where;
                    }
                }

                // afterWhere
                if (array_key_exists('afterWhere',$arg)) {
                    $requete .= ' ' . $arg['afterWhere'] . ' ';
                }

                if (!$flag_count) {
                    // Tris
                    if (array_key_exists('sort',$arg)) {
                        if (is_array($arg['sort'])) {
                            $count = 0;
                            foreach ($arg['sort'] as $ta_sort) {
                                if (!array_key_exists($ta_sort['table'],$ta_tables)) {
                                    $classe = 'project\manager\\' . ucfirst($ta_sort['table']).'Manager';
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
                    if (!$arg['limit']) {
                        $arg['limit'] = 0;
                    }

                    if (!$arg['start']) {
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

                $query = $this->db->pdo()->prepare($requete);

                // Bind
                foreach ($ta_bind as $key=>$infos_bind) {
                    $type = $ta_tables[$infos_bind['table']][$infos_bind['field']]['Type'];

                    $this->bind_query($query, ':bind' . $key, $type, $infos_bind['value']);
                }

                $query->execute();

                if ($flag_count) {
                    $results = $query->fetchColumn();
                    $this->setNbResults($results);
                    return $this->nbResults();
                } else {
                    while ($results = $query->fetch(\PDO::FETCH_ASSOC)) {
                        // Champs à déchiffrer
                        $ta_fields_to_decrypt = array_intersect_key($results,$this->encryptedFields());
                        foreach ($ta_fields_to_decrypt as $key=>$field_to_decrypt) {
                            $results[$key] = $this->db->dbCrypt()->decrypt_string($results[$key]);
                        }
                        $return[] = new $this->className($results);
                    }
                }

                echo ($requete . '<br>');

                return $return;
            }
        }
    }

    public function encrypt_column($field) {
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
        $requete .= ' WHERE ' . $this->tableIdField() . ' = :ID';
        $query = $this->db->pdo()->prepare($requete);

        while ($results = $query2->fetch(\PDO::FETCH_ASSOC)) {

            //$value = $this->crypt->encrypt_string($results[$list_fields[$field]['Field']]);
            $value = $this->db->dbCrypt()->encrypt_string($results[$list_fields[$this->tableName()][$field]['Field']]);
            if (strlen($value) > $list_fields[$this->tableName()][$field]['CharOctetLength']) {
                trigger_error('Le champ ' . $field . ' est trop petit, la chaine sera tronquee : ' . strlen($value) . ' > ' . $list_fields[$this->tableName()][$field]['CharOctetLength'],E_USER_NOTICE);
            }

            $query->bindValue(':value', $value, \PDO::PARAM_STR);

            $query->bindValue(':ID', (int) $results[$this->tableIdField()], \PDO::PARAM_INT);

            $query->execute();
            if (!$query) {
                print_r($this->db->pdo()->errorInfo());
            }
        }
    }

    public function decrypt_column($field) {
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
        $requete .= ' WHERE ' . $this->tableIdField() . ' = :ID';
        $query = $this->db->pdo()->prepare($requete);

        while ($results = $query2->fetch(\PDO::FETCH_ASSOC)) {

            //$value = $this->crypt->decrypt_string($results[$list_fields[$field]['Field']]);
            $value = $this->db->dbCrypt()->decrypt_string($results[$list_fields[$this->tableName()][$field]['Field']]);
            $query->bindValue(':value', $value, \PDO::PARAM_STR);

            $query->bindValue(':ID', (int) $results[$this->tableIdField()], \PDO::PARAM_INT);

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
