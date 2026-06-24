<?php
    
    namespace processid\manager;
    
    use Exception;
    use PDO;
    use PDOStatement;
    use processid\manager\attributes\ClassName;
    use processid\manager\attributes\DbFactory;
    use processid\manager\attributes\Encrypted;
    use processid\manager\attributes\Field;
    use processid\manager\attributes\ID;
    use processid\manager\attributes\Table;
    use processid\manager\enum\QueryOperator;
    use processid\manager\exception\ManagerNotFoundException;
    use ReflectionClass;
    use RuntimeException;
    use Throwable;
    
    /**
     * Classe résponsable de l'interaction avec la base de données.
     * Voir README.md pour plus d'informations.
     *
     * @version 3
     */
    abstract class Manager
    {
        const SORT_ASC = 'ASC';
        const SORT_DESC = 'DESC';
        /** @var ?string Nom du factory à chargé. */
        protected ?string $dbFactoryName = null;
        /** @var DbConnect Instance de la connexion à la base de données. */
        protected DbConnect $db;
        /** @var ?string Nom de la classe des objets retournés par get() et getList(). */
        protected ?string $className = null;
        /** @var string Nom de la table lié au manager. */
        protected string $tableName;
        /** @var string Nom du champ id de la table. */
        protected string $tableIdField;
        /** @var string[] Liste des champs de la table : valeur par rapport aux attributs du modèle. */
        protected array $fields = [];
        /** @var string[] Liste de champs cryptés. */
        protected array $_encryptedFields = [];
        protected $crypt;
        /** @var string|null Contenu texte erreur. */
        protected ?string $errorTxt = null;
        /** @var string Contenu texte du debug. */
        protected string $debugTxt = '';
        /** @var array Liste des champs cryptés qui doivent rester triable. */
        private array $_encryptedFieldsSortable = [];
        /** @var bool Liste des champs cryptés où l'on doit y créer des colonnes pour le tri. */
        private bool $_encryptedFieldsSortableCreate = false;
        /** @var array Tableau utilisé pour la construction de requête. */
        private array $_ta_bind = [];
        /** @var int Index pour la construction de requête. */
        private int $_count_bind = 0;
        /** @var array Liste des tables utilisées pour la construction de requête. */
        private array $_ta_request_tables = [];
        /** @var array La liste des champs est partagée entre toutes les classes qui héritent de la classe Manager (<table>Manager). */
        private static array $_fieldsList = [];
        
        public function __construct()
        {
            $this->_initAttributes();
        }
        
        /**
         * Récupère le nom de la table lié au manager.
         *
         * @return string
         */
        public function tableName(): string
        {
            return $this->tableName;
        }
        
        /**
         * Récupère le nom de la colonne id de la table.
         *
         * @return string
         */
        public function tableIdField(): string
        {
            return $this->tableIdField;
        }
        
        /**
         * Récupère le nom complet de la classe du modèle lié au manager.
         *
         * @return string
         */
        public function className(): string
        {
            return $this->className;
        }
        
        /**
         * Récupère le message d'erreur s'il y en a lors d'une intéraction avec la base de données.
         *
         * @return ?string
         */
        public function errorTxt(): ?string
        {
            return $this->errorTxt;
        }
        
        /**
         * Changement de l'instance )
         *
         * @param DbConnect|string $db
         *
         * @return $this
         *
         * @throws Exception
         * @throws RuntimeException
         */
        public function setDb(DbConnect|string $db): Manager
        {
            if (is_string($db)) {
                $this->dbFactoryName = $db;
                $this->db = ConnectionManager::get($this->dbFactoryName);
            } elseif ($db instanceof DbConnect) {
                $this->db = $db;
            } else {
                throw new Exception('Le paramètre doit être une instance de DbConnect ou le nom d\'un factory.');
            }
            
            return $this;
        }
        
        /**
         * Retourne la dernière requête SQL exécutée.
         *
         * @return string
         */
        public function debugTxt(): string
        {
            return $this->debugTxt;
        }
        
        /**
         * Retourne la liste des champs des tables déjà utilisées dans les instances de Manager.
         *
         * @return array
         */
        public function fieldsList(): array
        {
            if (!isset(self::$_fieldsList[$this->tableName()]) || !is_array(self::$_fieldsList[$this->tableName()])) {
                $this->recordFields();
            }
            
            return self::$_fieldsList;
        }
        
        /**
         * Retourne la liste des champs taggé avec l'attribut Field dans le model.
         *
         * @return array
         */
        public function modelFieldsList(): array
        {
            return array_intersect_key($this->fieldsList()[$this->tableName()], $this->fields);
        }
        
        /**
         * Vérfie si un champ existe dans la table.
         *
         * @param $field
         *
         * @return bool
         */
        public function fieldExists($field): bool
        {
            $list_fields = $this->fieldsList();
            
            if (!isset($list_fields[$this->tableName()]) || !is_array($list_fields[$this->tableName()])) {
                $this->recordFields();
                $list_fields = $this->fieldsList();
            }
            return array_key_exists($field, $list_fields[$this->tableName()]);
        }
        
        /**
         * Récupère une instance du modèle rattaché au manager par son id, les champs à récupérer peuvent être spécifiés.
         *
         * @param int $id
         * @param string|string[] $fields
         * @param DbConnect|string|null $connexion
         *
         * @return object
         *
         * @throws Exception Si une erreur survient lors de la préparation ou de l'exécution de la requête.
         */
        public function findById(int $id, string|array $fields = '*', null|DbConnect|string $connexion = null): object
        {
            return $this->withConnexion($connexion, function () use ($id, $fields) {
                $sql = "SELECT {$this->_traitFieldsParamsQuery($fields)} FROM {$this->tableName()} WHERE {$this->tableIdField()} = :{$this->tableIdField()}";
                $this->setDebugTxt($sql, true);
                
                if (false === $stmt = $this->db->pdo()->prepare($sql)) {
                    $this->setErrorTxt('Erreur dans findById():prepare() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $sql . ' - :id=' . $id);
                    throw new Exception($this->errorTxt());
                }
                
                $stmt->bindValue(':' . $this->tableIdField(), $id, PDO::PARAM_INT);
                $stmt->execute();
                
                if ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    
                    return $this->fetchOne($result);
                } else {
                    throw new Exception('Aucun résultat pour l\'ID : ' . $id . ' dans la table : ' . $this->tableName());
                }
            });
        }
        
        /**
         * Récupère une liste de modèles rattachés au manager par leurs ids, les champs à récupérer peuvent être spécifiés.
         *
         * @param int[] $taIds
         * @param string|string[] $fields
         * @param DbConnect|string|null $connexion
         *
         * @return array
         *
         * @throws Exception Si une erreur survient lors de la préparation ou de l'exécution de la requête.
         */
        public function findByIds(array $taIds, string|array $fields = '*', null|DbConnect|string $connexion = null): array
        {
            return $this->withConnexion($connexion, function () use ($taIds, $fields) {
                if (empty($taIds)) {
                    throw new Exception('Le tableau des IDs ne peut pas être vide.');
                }
                
                $ids = implode(',', array_map('intval', $taIds));
                $sql = "SELECT {$this->_traitFieldsParamsQuery($fields)} FROM {$this->tableName()} WHERE {$this->tableIdField()} IN ({$ids}) ORDER BY {$this->tableIdField()}";
                $this->setDebugTxt($sql, true);
                
                if (false === $stmt = $this->db->pdo()->prepare($sql)) {
                    throw new Exception('Erreur dans findByIds():prepare() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $sql);
                }
                
                $stmt->execute();
                
                return $this->fetchAll($stmt->fetchAll(PDO::FETCH_ASSOC));
            });
        }
        
        /**
         * Recherche des éléments dans la base de données en fonction des critères spécifiés en paramètre.
         *
         * @param array $arg Tableau au format search de la précedente version du Manager.
         * @param ?string $classname Nom de la classe à instancier pour chaque élément trouvé, si null on utilise le modèle.
         * @param DbConnect|string|null $connexion Connexion à utiliser, si null on utilise la connexion par défaut.
         *
         * @return object[]
         *
         * @throws Exception Si la classe spécifiée n'existe pas.
         * @throws Exception Si une erreur survient lors de la préparation ou de l'exécution de la requête.
         */
        public function findBy(array $arg = array(), ?string $classname = null, null|DbConnect|string $connexion = null): array
        {
            return $this->withConnexion($connexion, function () use ($arg, $classname) {
                //Si $classname spécifié, on vérifie que la classe existe
                if ($classname && !class_exists($classname)) {
                    throw new Exception('La classe ' . $classname . ' n\'existe pas');
                }
                
                $fieldsTable = array_keys($this->fieldsList()[$this->tableName()]);
                //Si pas de clé fields dans $arg, on retourne les valeurs complet dans la base
                if (!array_key_exists('fields', $arg) || !is_array($arg['fields'])) {
                    $arg['fields'] = array_map(function ($field) {
                        return ['table' => $this->tableName(), 'field' => $field];
                    }, $fieldsTable);
                }
                
                $request = $this->contruct_request($arg);
                $this->setDebugTxt($request, true);
                
                if (false === ($stmt = $this->db->pdo()->prepare($request))) {
                    $this->setErrorTxt('Erreur dans search():prepare() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $request);
                    throw new Exception($this->errorTxt());
                }
                
                $this->_bindParamsStmt($stmt);
                
                if (false === $stmt->execute()) {
                    $this->setErrorTxt('Erreur dans search():execute() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $request);
                    throw new Exception($this->errorTxt());
                }
                
                return $this->fetchAll($stmt->fetchAll(PDO::FETCH_ASSOC), $classname);
            });
        }
        
        /**
         * Compte les éléments dans la base de données en fonction des critères spécifiés en paramètre.
         *
         * @param array $arg Tableau au format search de la précedente version du Manager.
         * @param DbConnect|string|null $connexion
         *
         * @return int
         *
         * @throws Exception Si une erreur survient lors de la préparation ou de l'exécution de la requête.
         */
        public function countBy(array $arg = array(), null|DbConnect|string $connexion = null): int
        {
            return $this->withConnexion($connexion, function () use ($arg) {
                $arg['special'] = 'count';
                $request = $this->contruct_request($arg);
                $this->setDebugTxt($request, true);
                
                if (false === $stmt = $this->db->pdo()->prepare($request)) {
                    $this->setErrorTxt('Erreur dans countBy():prepare() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $request);
                    throw new Exception($this->errorTxt());
                }
                
                $this->_bindParamsStmt($stmt);
                
                if (false === $stmt->execute()) {
                    $this->setErrorTxt('Erreur dans countBy():execute() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $request);
                    throw new Exception($this->errorTxt());
                }
                
                return (int)$stmt->fetchColumn();
            });
        }
        
        /**
         * Met à jour les éléments de la base de données en fonction des critères spécifiés en paramètre.
         * Les données à mettre à jour sont spécifiées dans le tableau $dataUpdate.
         *
         * @param array $arg
         * @param array $dataUpdate
         * @param DbConnect|string|null $connexion
         *
         * @return int
         *
         * @throws Exception
         */
        public function updateBy(array $arg, array $dataUpdate, null|DbConnect|string $connexion = null): int
        {
            return $this->withConnexion($connexion, function () use ($arg, $dataUpdate) {
                //Supprimer le champ fields pour éviter les erreurs et ajouter le champ id de la table uniquement
                if (isset($arg['fields'])) {
                    unset($arg['fields']);
                }
                $arg['fields'] = [
                    ['table' => $this->tableName(), 'field' => $this->tableIdField()]
                ];
                
                $dataSet = $paramsUpdate = array();
                foreach ($dataUpdate as $key => $value) {
                    if ($key == $this->tableIdField()) {
                        continue;
                    }
                    
                    if (!array_key_exists($key, $this->fieldsList()[$this->tableName()])) {
                        trigger_error('Le champ : ' . $key . ' est introuvable dans la table : ' . $this->tableName(), E_USER_ERROR);
                    }
                    
                    $keyValue = "updatekey_{$key}";
                    $dataSet[] = "{$key} = :{$keyValue}";
                    $paramsUpdate[":{$keyValue}"] = $value;
                }
                
                if (empty($dataSet)) {
                    return 0;
                }
                
                $requestSearch = $this->contruct_request($arg);
                $requestUpdate = 'UPDATE ' . $this->tableName() . ' SET ' . implode(', ', $dataSet)
                    . ' WHERE ' . $this->tableName() . '.' . $this->tableIdField() . ' IN (SELECT ' . $this->tableIdField . ' FROM (' . $requestSearch . ') as temp )';
                
                $this->setDebugTxt($requestUpdate, true);
                if (false === $stmt = $this->db->pdo()->prepare($requestUpdate)) {
                    $this->setErrorTxt('Erreur dans updateBy():prepare() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requestUpdate);
                    throw new Exception($this->errorTxt());
                }
                
                $this->_bindParamsStmt($stmt);
                foreach ($paramsUpdate as $key => $value) {
                    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
                }
                
                if (false === $stmt->execute()) {
                    $this->setErrorTxt('Erreur dans updateBy():execute() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requestUpdate);
                    throw new Exception($this->errorTxt());
                }
                
                return $stmt->rowCount();
            });
        }
        
        /**
         * Supprime les éléments de la base de données en fonction des critères spécifiés en paramètre.
         *
         * @param array $arg
         * @param DbConnect|string|null $connexion
         *
         * @return int
         * @throws Exception
         */
        public function deleteBy(array $arg, null|DbConnect|string $connexion = null): int
        {
            return $this->withConnexion($connexion, function () use ($arg) {
                // Si les arguments sont vides, rien à faire
                if (empty($arg)) {
                    return 0;
                }
                
                // Supprimer le champ fields pour éviter les erreurs et ajouter le champ id de la table uniquement
                if (isset($arg['fields'])) {
                    unset($arg['fields']);
                }
                $arg['fields'] = [
                    ['table' => $this->tableName(), 'field' => $this->tableIdField()]
                ];
                
                // Construire la requête de recherche
                $requestSearch = $this->contruct_request($arg);
                
                // Construire la requête DELETE
                $requestDelete = 'DELETE FROM ' . $this->tableName() . '
                      WHERE ' . $this->tableIdField() . ' IN
                      (SELECT ' . $this->tableIdField() . ' FROM (' . $requestSearch . ') AS temp)';
                
                // Déboguer la requête
                $this->setDebugTxt($requestDelete, true);
                
                // Préparer la requête
                if (false === $stmt = $this->db->pdo()->prepare($requestDelete)) {
                    $this->setErrorTxt('Erreur dans deleteBy():prepare() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requestDelete);
                    throw new Exception($this->errorTxt());
                }
                
                $this->_bindParamsStmt($stmt);
                // Exécuter la requête
                if (false === $stmt->execute()) {
                    $this->setErrorTxt('Erreur dans deleteBy():execute() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requestDelete);
                    throw new Exception($this->errorTxt());
                }
                
                return $stmt->rowCount();
            });
        }
        
        /**
         * Récupère tous les éléments de la table. Les colonnes à chercher et l'ordre de tri peut être spécifié.
         *
         * @param string|string[] $fields
         * @param array $sort [nom du champ => direction de tri] (self::SORT_ASC ou self::SORT_DES).
         * @param DbConnect|string|null $connexion
         *
         * @return array tableau associatif des éléments trouvés.
         *
         * @throws Exception Si le champ spécifié n'existe pas.
         * @throws Exception Si une erreur survient lors de la préparation ou de l'exécution de la requête.
         */
        public function findAll(string|array $fields = '*', array $sort = [], null|DbConnect|string $connexion = null): array
        {
            return $this->withConnexion($connexion, function () use ($fields, $sort) {
                $sql = "SELECT {$this->_traitFieldsParamsQuery($fields)} FROM {$this->tableName()} WHERE {$this->tableIdField()}";
                if (!empty($sort)) {
                    $sortClauses = [];
                    foreach ($sort as $field => $direction) {
                        if (!$this->fieldExists($field)) {
                            throw new Exception("Le champ spécifié '$field' n'existe pas.");
                        }
                        $direction = strtoupper($direction) === self::SORT_DESC ? self::SORT_DESC : self::SORT_ASC;
                        $sortClauses[] = "`$field` $direction";
                    }
                    $sql .= ' ORDER BY ' . implode(', ', $sortClauses);
                }
                $this->setDebugTxt($sql, true);
                
                if (false === $stmt = $this->db->pdo()->prepare($sql)) {
                    throw new Exception('Erreur dans findAll():prepare() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $sql);
                }
                $stmt->execute();
                
                return $this->fetchAll($stmt->fetchAll(PDO::FETCH_ASSOC));
            });
        }
        
        /**
         * Compte tous les éléments de la table.
         *
         * @param DbConnect|string|null $connexion
         *
         * @return int
         *
         * @throws Exception Si une erreur survient lors de la préparation ou de l'exécution de la requête.
         */
        public function countAll(null|DbConnect|string $connexion = null): int
        {
            return $this->withConnexion($connexion, function () {
                $sql = "SELECT COUNT(1) FROM {$this->tableName()}";
                $this->setDebugTxt($sql, true);
                
                if (false === $stmt = $this->db->pdo()->prepare($sql)) {
                    throw new Exception('Erreur dans countAll():prepare() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $sql);
                }
                $stmt->execute();
                
                return (int)$stmt->fetchColumn();
            });
        }
        
        /**
         * Sauvegarde une instance du modèle (création ou mise à jour si l'identifiant de l'objet n'est pas vide).
         *
         * @param object $object Instance du modèle à sauvegarder.
         * @param string[]|string $fields Liste des champs à mettre à jour.
         * @param bool $ignore Indique si on doit faire un insert ignore au lieu d'un insert si création.
         * @param DbConnect|string|null $connexion
         *
         * @return object
         *
         * @throws Exception Si une erreur survient lors de la préparation ou de l'exécution de la requête.
         */
        public function persist(object $object, array|string $fields = '', bool $ignore = false, null|DbConnect|string $connexion = null): object
        {
            return $this->withConnexion($connexion, function () use ($object, $fields, $ignore) {
                if ($object->{$this->tableIdField}() > 0) {
                    
                    return $this->_update($object, $fields);
                } else {
                    
                    return $this->_add($object, $ignore);
                }
            });
        }
        
        /**
         * Insertion multiple de models
         *
         * @param array $models
         * @param bool $ignore
         * @param DbConnect|string|null $connexion
         *
         * @return void
         * @throws Exception
         */
        public function insertMultiple(array $models, bool $ignore = false, null|DbConnect|string $connexion = null): void
        {
            $this->withConnexion($connexion, function () use ($models, $ignore) {
                if (!empty($models)) {
                    //Vérifie si les modèles sont des instances de processid\manager\Model
                    if (!array_reduce(
                        $models,
                        fn($carry, $item) => $carry && $item instanceof Model,
                        true
                    )) {
                        trigger_error('Les modèles doivent être des instances de Model.', E_USER_ERROR);
                    }
                    
                    $fields = array_keys($this->fieldsList()[$this->tableName()]);
                    $params = array();
                    $allValues = array();
                    
                    foreach ($models as $key => $model) {
                        $values = array();
                        foreach ($fields as $field) {
                            $fieldValue = $model->{$field}();
                            if (is_null($fieldValue)) {
                                // Champ non renseigné : on laisse la base appliquer le DEFAULT de la
                                // colonne (NULL, '', littéral ou expression comme CURRENT_TIMESTAMP), par ligne.
                                $values[] = 'DEFAULT';
                            } else {
                                // Chiffrement des champs marqués #[Encrypted], comme dans persist()
                                // (même garde !empty, cohérente avec _descryptData() à la lecture).
                                if (in_array($field, $this->encryptedFields()) && !empty($fieldValue)) {
                                    $fieldValue = $this->db->dbCrypt()->encrypt_string($fieldValue);
                                }
                                $params[$field . '_' . $key] = $fieldValue;
                                $values[] = ':' . $field . '_' . $key;
                            }
                        }
                        $allValues[] = '(' . implode(', ', $values) . ')';
                    }
                    $fields = implode(', ', $fields);
                    $values = implode(', ', $allValues);
                    
                    $sql = "INSERT INTO {$this->tableName()} ($fields) VALUES $values";
                    if ($ignore) {
                        $sql = "INSERT IGNORE INTO {$this->tableName()} ($fields) VALUES $values";
                    }
                    $this->setDebugTxt($sql, true);
                    
                    try {
                        $this->db->pdo()->beginTransaction();
                        if (false === $stmt = $this->db->pdo()->prepare($sql)) {
                            throw new Exception('Erreur dans insertMultiple():prepare() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $sql);
                        }
                        $result = $stmt->execute($params);
                        if ($result) {
                            $this->db->pdo()->commit(); // Valider la transaction
                        } else {
                            $this->db->pdo()->rollBack(); // Annuler en cas d'échec
                        }
                    } catch (Exception $e) {
                        $this->db->pdo()->rollBack(); // Annuler en cas d'échec
                        throw new Exception($e->getMessage());
                    }
                }
            });
        }
        
        /**
         * Supprime un élément de la base de données.
         *
         * @param int $id Identifiant de l'élément à supprimer.
         * @param DbConnect|string|null $connexion
         *
         * @return void
         *
         * @throws Exception Si une erreur survient lors de la préparation ou de l'exécution de la requête.
         */
        public function delete(int $id, null|DbConnect|string $connexion = null): void
        {
            $this->withConnexion($connexion, function () use ($id) {
                $requete = 'DELETE FROM ' . $this->tableName() . ' WHERE ' . $this->tableIdField() . ' = :' . $this->tableIdField();
                $this->setDebugTxt($requete, true);
                
                if (false === $stmt = $this->db->pdo()->prepare($requete)) {
                    $this->setErrorTxt('Erreur dans delete():prepare() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requete . ' - ID:' . $id);
                    throw new Exception($this->errorTxt());
                }
                
                $stmt->bindValue(':' . $this->tableIdField(), $id, PDO::PARAM_INT);
                $this->setDebugTxt('bind:' . $this->tableIdField() . ' = ' . $id . ' (INTEGER');
                
                if (false === $stmt->execute()) {
                    $this->setErrorTxt('Erreur dans delete():execute() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requete . ' - ID:' . $id);
                    throw new Exception($this->errorTxt());
                }
            });
        }
        
        /**
         * Converti le tableau résultant d’un PDO “execute” fetchall en un tableau d’objet $classname ou du modèle du manager, décryptant les éventuels champs concernés.
         *
         * @param array $results Tableau de plusieurs résultats issus d'un execute pdo (fetchAll PDO::FETCH_ASSOC).
         * @param ?string $classname Nom de la classe à instancier pour chaque élément trouvé, si null on utilise le modèle.
         *
         * @return object[] Tableau d'objets $classname ou modèle.
         *
         * @throws Exception Si le tableau de résultats n'est pas un tableau de tableaux de résultats.
         * @throws Exception Si le tableau de résultat ne correspond pas à la classe.
         * @throws Exception Si le tableau de résultat ne correspond pas au modèle.
         */
        public function fetchAll(array $results, ?string $classname = null): array
        {
            $return = [];
            foreach ($results as $result) {
                if (!is_array($result)) {
                    throw new Exception('Le tableau de résultats doit être un tableau de n tableaux de résultat');
                }
                $return[] = $this->fetchOne($result, $classname);
            }
            
            return $return;
        }
        
        /**
         * Converti le tableau résultant d’un PDO “execute” en fetch en une instance de l'objet $classname ou du modèle, décryptant les éventuels champs concernés.
         *
         * @param array $result Tableau de résultat issu d'un execute pdo (fetch PDO::FETCH_ASSOC).
         * @param ?string $classname Nom de la classe à instancier pour chaque élément trouvé, si null on utilise le modèle.
         *
         * @return object Objet $classname ou modèle.
         *
         * @throws Exception Si le tableau de résultat est vide.
         * @throws Exception Si le tableau de résultat ne correspond pas à la classe.
         * @throws Exception Si le tableau de résultat ne correspond pas au modèle.
         */
        public function fetchOne(array $result, ?string $classname = null): object
        {
            if (empty($result)) {
                throw new Exception('Le tableau de résultat est vide');
            }
            
            $this->_descryptData($result);
            if ($classname == StandardModel::class) {
                return new $classname($result);
            } elseif ($classname) {
                $valid = false;
                //Si on utilise un autre modele que celui de la classe courante, on retourne un tableau d'objet de ce modele
                // La base de l'hydratation est par rapport au alias et à travers un setter de la classe
                $objet = new $classname();
                foreach ($result as $key => $value) {
                    $setter = 'set' . ucfirst($key);
                    // Si le nom de clé contient un underscore, on génère une version camelCase
                    if (strpos($key, '_') !== false) {
                        $camelCaseKey = str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
                        $setterCamelCase = 'set' . $camelCaseKey;
                        // Vérifie d'abord si la méthode camelCase existe
                        if ((method_exists($objet, 'hasMethod') && $objet->hasMethod($setterCamelCase)) || method_exists($objet, $setterCamelCase)) {
                            $setter = $setterCamelCase;
                        }
                    }
                    if ((method_exists($objet, 'hasMethod') && $objet->hasMethod($setter)) || method_exists($objet, $setter)) {
                        $valid = true;
                        $objet->$setter($value);
                    }
                }
                
                if (!$valid) {
                    // Aucune correspondance entre la classe et le tableau
                    throw new Exception('Le tableau de résultat ne correspond pas à la classe.');
                }
                
                return $objet;
            } else {
                
                $fieldsTable = array_keys($this->fieldsList()[$this->tableName()]);
                $result = array_filter($result, function ($key) use ($fieldsTable) {
                    return in_array($key, $fieldsTable);
                }, ARRAY_FILTER_USE_KEY);
                
                if (empty($result)) {
                    // Aucun clé du tableau des resultants ne correspond à une colonne de la table
                    throw new Exception('Le tableau de résultat ne correspond pas au modèle.');
                }
                
                return new $this->className($result);
            }
        }
        
        /**
         * Crypte les lignes de la colonne en paramètre.
         *
         * @param string $field Nom de la colonne à crypter.
         *
         * @return void
         *
         * @throws Exception Si le champ n'existe pas dans la table.
         * @throws Exception Si le champ n'est pas de type texte.
         * @throws Exception Si le champ est trop petit pour contenir la chaine cryptée.
         */
        public function encrypt_column(string $field): void
        {
            if (!$this->fieldExists($field)) {
                throw new Exception('Le champ ' . $field . ' n\'existe pas dans la table ' . $this->tableName());
            }
            
            // La colonne doit être en texte
            $list_fields = $this->fieldsList();
            $type = $list_fields[$this->tableName()][$field]['Type'];
            $typeAllowed = ['char', 'varchar', 'tinyblob', 'tinytext', 'blob', 'text', 'mediumblob', 'mediumtext', 'longblob', 'longtext'];
            if (!in_array($type, $typeAllowed)) {
                throw new Exception('Le champ ' . $field . ' doit etre au format texte');
            }
            
            // On boucle sur tous les enregistrements pour chiffrer la colonne
            $requete = 'SELECT ' . $this->tableIdField() . ', ' . $list_fields[$this->tableName()][$field]['Field'] . ' FROM ' . $this->tableName() . ' ORDER BY ' . $this->tableIdField();
            $query2 = $this->db->pdo()->prepare($requete);
            $query2->execute();
            
            $requete = 'UPDATE ' . $this->tableName() . ' SET ';
            $requete .= ' ' . $list_fields[$this->tableName()][$field]['Field'] . '=:value';
            $requete .= ' WHERE ' . $this->tableIdField() . ' = :' . $this->tableIdField();
            $query = $this->db->pdo()->prepare($requete);
            
            while ($results = $query2->fetch(PDO::FETCH_ASSOC)) {
                $value = $this->db->dbCrypt()->encrypt_string($results[$list_fields[$this->tableName()][$field]['Field']]);
                if (strlen($value) > $list_fields[$this->tableName()][$field]['CharOctetLength']) {
                    throw new Exception('Le champ ' . $field . ' est trop petit, la chaine sera tronquee : ' . strlen($value) . ' > ' . $list_fields[$this->tableName()][$field]['CharOctetLength']);
                }
                
                $query->bindValue(':value', $value, PDO::PARAM_STR);
                $query->bindValue(':' . $this->tableIdField(), (int)$results[$this->tableIdField()], PDO::PARAM_INT);
                
                $query->execute();
                if (!$query) {
                    print_r($this->db->pdo()->errorInfo());
                }
            }
        }
        
        /**
         * Déchiffre les lignes de la colonne en paramètre.
         *
         * @param string $field
         *
         * @return void
         *
         * @throws Exception Si le champ n'existe pas dans la table.
         * @throws Exception Si le champ n'est pas de type texte.
         */
        public function decrypt_column(string $field): void
        {
            if (!$this->fieldExists($field)) {
                throw new Exception('Le champ ' . $field . ' n\'existe pas dans la table ' . $this->tableName());
            }
            
            // La colonne doit être en texte
            $list_fields = $this->fieldsList();
            $type = $list_fields[$this->tableName()][$field]['Type'];
            if ($type != 'char' && $type != 'varchar' && $type != 'tinyblob' && $type != 'tinytext' && $type != 'blob' && $type != 'text' && $type != 'mediumblob' && $type != 'mediumtext' && $type != 'longblob' && $type != 'longtext') {
                throw new Exception('Le champ ' . $field . ' doit etre au format texte');
            }
            
            // On boucle sur tous les enregistrements pour déchiffrer la colonne
            $requete = 'SELECT ' . $this->tableIdField() . ', ' . $list_fields[$this->tableName()][$field]['Field'] . ' FROM ' . $this->tableName() . ' ORDER BY ' . $this->tableIdField();
            $query2 = $this->db->pdo()->prepare($requete);
            $query2->execute();
            
            $requete = 'UPDATE ' . $this->tableName() . ' SET ';
            $requete .= ' ' . $list_fields[$this->tableName()][$field]['Field'] . '=:value';
            $requete .= ' WHERE ' . $this->tableIdField() . ' = :' . $this->tableIdField();
            $query = $this->db->pdo()->prepare($requete);
            
            while ($results = $query2->fetch(PDO::FETCH_ASSOC)) {
                $value = $this->db->dbCrypt()->decrypt_string($results[$list_fields[$this->tableName()][$field]['Field']]);
                $query->bindValue(':value', $value, PDO::PARAM_STR);
                $query->bindValue(':' . $this->tableIdField(), (int)$results[$this->tableIdField()], PDO::PARAM_INT);
                $query->execute();
                
                if (!$query) {
                    print_r($this->db->pdo()->errorInfo());
                }
            }
        }
        
        /**
         * Crée les champs de tri manquants des champs chiffrés.
         *
         * @return void
         */
        public function create_encrypted_fields_sortable(): void
        {
            foreach ($this->encryptedFieldsSortable() as $key => $value) {
                if ($value) {
                    $field = $key . '_tri';
                    if (!$this->fieldExists($field)) {
                        trigger_error('Creation du champ de tri ' . $field . ' sur la table ' . $this->tableName(), E_USER_NOTICE);
                        $query = $this->db->pdo()->prepare('ALTER TABLE ' . $this->tableName() . ' ADD ' . $field . ' BIGINT DEFAULT 0');
                        $query->execute();
                        $query = $this->db->pdo()->prepare('CREATE INDEX `IDX_' . $key . '_tri` ON `' . $this->tableName() . '` (`' . $key . '_tri`);');
                        $query->execute();
                    }
                }
            }
        }
        
        /**
         * Fonction utilitaire générique
         * Prend une chaîne en entrée et en retourne un tableau de mot unique, filter par taille minimale
         * Exemple avec : $query = "Prend une chaîne en entrée et en retourne un tableau", $min=4, $limit=3, $separator=' '
         *              : [ 'Prend', 'chaîne', 'entrée' ]
         *
         * @param string $query
         * @param int $min : taille minimale d'un mot
         * @param int $limit : nombre max de valeur à retourner
         * @param string $separator : le caractère de separation, espace ( ' ' ) par défaut
         *
         * @return string[] : peut être vide mais jamais null
         */
        public static function tokenize(string $query, int $min = 3, int $limit = 5, string $separator = ' '): array
        {
            $res = [];
            
            if (empty($query)) {
                return $res;
            }
            
            $vals = explode($separator, $query);
            foreach ($vals as $item) {
                $a = trim(empty($item) ? '' : $item);
                if (empty($a)) {
                    continue;
                }
                
                if (strlen($a) < $min) {
                    continue;
                }
                
                $res[] = $a;
                
                if (count($res) >= $limit) {
                    break;
                }
            }
            
            return $res;
        }
        
        /**
         * Indique le nom de la table lié au manager.
         *
         * @param $tableName
         *
         * @return void
         */
        protected function setTableName($tableName): void
        {
            $this->tableName = $tableName;
        }
        
        /**
         * Indique le nom de la classe du modèle lié au manager.
         *
         * @param $className
         *
         * @return void
         */
        protected function setClassName($className): void
        {
            $this->className = $className;
        }
        
        /**
         * Indique le nom de la colonne id de la table.
         *
         * @param $tableIdField
         *
         * @return void
         */
        protected function setTableIdField($tableIdField): void
        {
            $this->tableIdField = $tableIdField;
        }
        
        /**
         * Ajout d'un message d'erreur.
         *
         * @param $errorTxt
         *
         * @return void
         */
        protected function setErrorTxt($errorTxt): void
        {
            $this->errorTxt = $errorTxt;
        }
        
        /**
         * Paramètre les champs à crypter.
         *
         * @param array $encryptedFields
         *
         * @return void
         */
        protected function setEncryptedFields(array $encryptedFields): void
        {
            if (is_array($encryptedFields)) {
                $this->_encryptedFields = $encryptedFields;
            } else {
                $this->_encryptedFields = [];
            }
        }
        
        /**
         * Paramètre les champs cryptés triable.
         *
         * @param array $encryptedFieldsSortable
         *
         * @return void
         */
        protected function setEncryptedFieldsSortable(array $encryptedFieldsSortable): void
        {
            if (is_array($encryptedFieldsSortable)) {
                $this->_encryptedFieldsSortable = $encryptedFieldsSortable;
            } else {
                $this->_encryptedFieldsSortable = [];
            }
        }
        
        /**
         * Paramètre les champs cryptés triable sur lesquels on doit créer des colonnes pour le tri.
         *
         * @param $encryptedFieldsSortableCreate
         *
         * @return void
         */
        protected function setEncryptedFieldsSortableCreate($encryptedFieldsSortableCreate): void
        {
            if ($encryptedFieldsSortableCreate) {
                $this->_encryptedFieldsSortableCreate = true;
            } else {
                $this->_encryptedFieldsSortableCreate = false;
            }
        }
        
        /**
         * Vérifie si un champ est crypté.
         *
         * @param $field
         *
         * @return bool
         */
        protected function isEncrypted($field): bool
        {
            return array_key_exists($field, $this->encryptedFields());
        }
        
        /**
         * Vérifie si un champ est encrypté et triable.
         *
         * @param $field
         *
         * @return bool
         */
        protected function isEncryptedSortable($field): bool
        {
            return array_key_exists($field, $this->encryptedFieldsSortable());
        }
        
        /**
         * Ajoute les informations de la table actuelle dans l'attribut $_fieldsList.
         *
         * @return void
         */
        protected function recordFields(): void
        {
            $requete = 'SELECT COLUMN_NAME, COLUMN_DEFAULT, IS_NULLABLE, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, CHARACTER_OCTET_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE, COLUMN_KEY, EXTRA';
            $requete .= ' FROM information_schema.columns';
            $requete .= ' WHERE table_name=:tableName';
            $requete .= ' AND table_schema=DATABASE()';
            
            $query = $this->db->pdo()->prepare($requete);
            if ($query === false) {
                trigger_error('Erreur dans recordFields():prepare() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requete, E_USER_ERROR);
            }
            
            $query->bindValue(':tableName', $this->tableName(), PDO::PARAM_STR);
            
            $query->execute();
            if ($query === false) {
                trigger_error('Erreur dans recordFields():execute() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requete, E_USER_ERROR);
            }
            
            self::$_fieldsList[$this->tableName()] = [];
            
            while ($results = $query->fetch(PDO::FETCH_ASSOC)) {
                self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']] = [];
                self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['Field'] = $results['COLUMN_NAME'];                                         // Nom du champ
                self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['Type'] = strtolower($results['DATA_TYPE']);                                // Type de champs (bigint, varchar, int, date, decimal, ...)
                self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['Null'] = $results['IS_NULLABLE'];                                          // Peut être null (true | false)
                self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['Key'] = $results['COLUMN_KEY'];                                            // PRI, UNI, MUL
                self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['CharMaxLength'] = $results['CHARACTER_MAXIMUM_LENGTH'];                    // La taille max en caractères pour les chaînes
                self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['CharOctetLength'] = $results['CHARACTER_OCTET_LENGTH'];                    // La taille max en octets pour les chaînes
                self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['Precision'] = $results['NUMERIC_PRECISION'];                               // La précision pour les colonnes numériques
                self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['NumericScale'] = $results['NUMERIC_SCALE'];                                   // L'échelle pour les colonnes numériques
                self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['Default'] = $results['COLUMN_DEFAULT'];                                    // Valeur par défaut
                self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['Extra'] = $results['EXTRA'];                                               // auto_increment, ...
                self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['Encrypted'] = $this->isEncrypted($results['COLUMN_NAME']);                 // true | false
                self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['EncryptedSortable'] = $this->isEncryptedSortable($results['COLUMN_NAME']); // true | false
                
                if (strstr(strtolower($results['EXTRA']), 'auto_increment') === false) {
                    self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['auto_increment'] = false;
                } else {
                    self::$_fieldsList[$this->tableName()][$results['COLUMN_NAME']]['auto_increment'] = true;
                }
            }
        }
        
        /**
         * Récupère la liste des champs cryptés.
         *
         * @return array
         */
        protected function encryptedFields(): array
        {
            return $this->_encryptedFields;
        }
        
        /**
         * Récupère la liste des champs cryptés et triables.
         *
         * @return array
         */
        protected function encryptedFieldsSortable(): array
        {
            return $this->_encryptedFieldsSortable;
        }
        
        /**
         * Récupère la liste des champs cryptés et triables sur lesquels on doit créer des colonnes pour le tri.
         *
         * @return bool
         */
        protected function encryptedFieldsSortableCreate(): bool
        {
            return $this->_encryptedFieldsSortableCreate;
        }
        
        /**
         * Initialisation de la connexion à la base de données.
         *
         * @return void
         *
         * @throws Exception Si la clé de configuration du factory utilisé n'existe pas.
         * @throws Exception S'il y a erreur lors de la connexion de PDO.
         */
        private function _initDb(): void
        {
            $this->db = ConnectionManager::get($this->dbFactoryName);
        }
        
        /**
         * Ajoute un texte au debugTxt, possibilité de le reinitialiser.
         *
         * @param string $texte Le texte à ajouter.
         * @param bool $init Si false le texte est ajouté à la suite, si true le texte remplace le contenu actuel.
         *
         * @return void
         */
        private function setDebugTxt(string $texte, bool $reset = false): void
        {
            if ($reset) {
                $this->debugTxt = '';
            } elseif (strlen($this->debugTxt)) {
                $this->debugTxt .= "\n";
            }
            $this->debugTxt .= $texte;
        }
        
        /**
         * Initialisation les attributs de la classe manager enfant.
         *
         * @return void
         *
         * @throws Exception Si la clé de configuration du factory utilisé n'existe pas.
         * @throws Exception S'il y a erreur lors de la connexion de PDO.
         * @throws Exception Si l'attribut ID est associé à plusieurs propriétés.
         * @throws Exception Si l'attribut ID n'est pas associé à un attribut Field en premier.
         * @throws Exception Si l'attribut Encrypted n'est pas associé à un attribut Field en premier.
         */
        private function _initAttributes(): void
        {
            $reflectionClass = new ReflectionClass($this);
            $this->_initAttributesClass($reflectionClass);
            $this->_initDb();
            $this->_initAttibutesModel();
        }
        
        /**
         * Initialisation du nom de la table, classe modèle et de la connexion à la base de données depuis les attributs de classe du Manager.
         *
         * @param ReflectionClass $reflectionClass Classe de réfléxion du manager pour récupérer les attributs.
         *
         * @return void
         */
        private function _initAttributesClass(ReflectionClass $reflectionClass): void
        {
            foreach ($reflectionClass->getAttributes() as $attribute) {
                switch ($attribute->getName()) {
                    case DbFactory::class:
                        // Récupération de l'attribut DbFactory
                        $dbFactoryAttribute = $attribute->newInstance();
                        $this->dbFactoryName = $dbFactoryAttribute->factoryName;
                        break;
                    case ClassName::class:
                        $classNameAttribute = $attribute->newInstance();
                        $this->className = $classNameAttribute->value;
                        break;
                    case Table::class:
                        $tableAttribute = $attribute->newInstance();
                        $this->tableName = $tableAttribute->tableName;
                        break;
                }
            }
        }
        
        /**
         * Traitement des attributs du modèle.
         *
         * @return void
         *
         * @throws Exception Si l'attribut ID est associé à plusieurs propriétés.
         * @throws Exception Si l'attribut ID n'est pas associé à un attribut Field en premier.
         * @throws Exception Si l'attribut Encrypted n'est pas associé à un attribut Field en premier.
         */
        private function _initAttibutesModel(): void
        {
            if (isset($this->className)) {
                $reflectionClass = new ReflectionClass(new $this->className());
                foreach ($reflectionClass->getProperties() as $property) {
                    $propertyAttributes = $property->getAttributes();
                    foreach ($propertyAttributes as $attribute) {
                        switch ($attribute->getName()) {
                            case Field::class:
                                $fieldAttribute = $attribute->newInstance();
                                if (!$this->fieldExists($fieldAttribute->fieldName)) {
                                    throw new Exception("Le champ {$fieldAttribute->fieldName} n'existe pas dans la table {$this->tableName}");
                                }
                                $this->fields[$property->getName()] = $fieldAttribute->fieldName;
                                break;
                            case ID::class:
                                if (isset($this->tableIdField)) {
                                    throw new Exception('Il ne peut y avoir qu\'un seul attribut ID par classe');
                                }
                                if (!isset($this->fields[$property->getName()])) {
                                    throw new Exception("L'attribut ID du propriété {$property->getName()} doit être associé à un attribut Field en premier");
                                }
                                $this->tableIdField = $property->getName();
                                break;
                            case Encrypted::class:
                                if (!isset($this->fields[$property->getName()])) {
                                    throw new Exception("L'attribut Encrypted du propriété {$property->getName()} doit être associé à un attribut Field en premier");
                                }
                                $this->_encryptedFields[] = $property->getName();
                                break;
                        }
                    }
                }
            }
        }
        
        /**
         * Constructeur de requête.
         *
         * @param array $arg Tableau au format search de la précedente version du Manager.
         * @param bool $subRequest Indique si c'est une sous requête et dans ce cas ne pas réinitialiser les variables de construction de requête.
         *
         * @return string Requête préparée SQL.
         *
         * @throws ManagerNotFoundException
         */
        private function contruct_request(array $arg = [], bool $subRequest = false): string
        {
            if (!$subRequest) {
                $this->_ta_bind = [];
                $this->_count_bind = 0;
                $this->_ta_request_tables = [];
            }
            
            // Tableau de la structure des tables
            $this->_ta_request_tables = $this->fieldsList();
            if (!isset(self::$_fieldsList[$this->tableName()]) || !is_array(self::$_fieldsList[$this->tableName()])) {
                $this->recordFields();
            }
            
            $ta_tables = $this->fieldsList();
            
            $flag_count = isset($arg['special']) && $arg['special'] == 'count';
            
            // Vérification et traitement des colonnes à récupérer.
            $fields = '';
            if (array_key_exists('fields', $arg) && is_array($arg['fields']) && count($arg['fields'])) {
                foreach ($arg['fields'] as $ta_field) {
                    $fields .= ($fields != '' ? ',' : '');
                    
                    if (!array_key_exists($ta_field['table'], $ta_tables)) {
                        $this->recordTableField($ta_field['table']);
                        $ta_tables = $this->fieldsList();
                    }
                    if ($ta_field['field'] == '*') {
                        $fields .= '*';
                    } elseif (!array_key_exists($ta_field['field'], $ta_tables[$ta_field['table']])) {
                        trigger_error('Le champ : ' . $ta_field['field'] . ' est introuvable dans la table : ' . $ta_field['table'], E_USER_ERROR);
                    } else {
                        if (array_key_exists('function', $ta_field) && !empty($ta_field['function'])) {
                            if (!in_array($ta_field['function'], ['avg', 'count', 'distinct', 'max', 'min', 'sum'])) {
                                trigger_error('La fonction : ' . $ta_field['function'] . ' est inconnue', E_USER_ERROR);
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
            
            $request = 'SELECT ' . $fields . ' FROM ' . $this->tableName() . ' ';
            
            // jointures
            if (array_key_exists('join', $arg) && is_array($arg['join']) && count($arg['join'])) {
                foreach ($arg['join'] as $ta_join) {
                    if (!array_key_exists($ta_join['table'], $ta_tables)) {
                        $this->recordTableField($ta_join['table']);
                        $ta_tables = $this->fieldsList();
                    }
                    if (!array_key_exists($ta_join['on']['table1'], $ta_tables)) {
                        $this->recordTableField($ta_join['on']['table1']);
                        $ta_tables = $this->fieldsList();
                    }
                    if (!array_key_exists($ta_join['on']['table2'], $ta_tables)) {
                        $this->recordTableField($ta_join['on']['table2']);
                        $ta_tables = $this->fieldsList();
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
                    
                    // Contrôle du type de jointure
                    if (!array_key_exists('type', $ta_join) || !in_array($ta_join['type'], ['left', 'right', 'inner', 'full'])) {
                        $ta_join['type'] = 'inner';
                    }
                    
                    $request .= ' ' . strtoupper($ta_join['type']) . ' JOIN ' . $ta_join['table'] . ' ON ' . $ta_join['on']['table1'] . '.' . $ta_join['on']['field1'] . '=' . $ta_join['on']['table2'] . '.' . $ta_join['on']['field2'];
                }
            }
            
            // beforeWhere
            if (array_key_exists('beforeWhere', $arg)) {
                $request .= $arg['beforeWhere'] . ' ';
            }
            
            // search
            if (!array_key_exists('search', $arg) || !is_array($arg['search'])) {
                $arg['search'] = [];
            }
            
            $ta_where = [];
            foreach ($arg['search'] as $ta_search) {
                if (!array_key_exists($ta_search['table'], $ta_tables)) {
                    $this->recordTableField($ta_search['table']);
                    $ta_tables = $this->fieldsList();
                }
                if (!array_key_exists($ta_search['field'], $ta_tables[$ta_search['table']])) {
                    trigger_error('Le champ : ' . $ta_search['field'] . ' est introuvable dans la table : ' . $ta_search['table'], E_USER_ERROR);
                }
                if (!in_array($ta_search['operator'], array_map(fn($case) => $case->value, QueryOperator::cases()))) {
                    trigger_error('Operateur inconnu : ' . $ta_search['operator'], E_USER_ERROR);
                }
                if (in_array($ta_search['operator'], [QueryOperator::FULLTEXT->value, QueryOperator::FULLTEXT_LEFT->value, QueryOperator::FULLTEXT_BOTH->value, QueryOperator::FULLTEXT_RIGHT->value])) {
                    if (preg_match('#^id[0-9]{1,}$#', $ta_search['value'])) {
                        $this->_ta_bind[$this->_count_bind] = [];
                        $this->_ta_bind[$this->_count_bind]['table'] = $ta_search['table'];
                        $this->_ta_bind[$this->_count_bind]['field'] = $this->tableIdField();
                        $this->_ta_bind[$this->_count_bind]['value'] = (int)trim(substr($ta_search['value'], 2));
                        $ta_where[] = $ta_search['table'] . '.' . $this->tableIdField() . '=:bind' . $this->_count_bind++;
                    } else {
                        $recStr = preg_replace("/[[:punct:]]/u", " ", $ta_search['value']);
                        $recStr = preg_replace("/[[:space:]]{2,}/u", " ", $recStr);
                        $array_rec = explode(" ", trim($recStr));
                        $countMots = 0;
                        $condition_tmp = ' (';
                        foreach ($array_rec as $mot) {
                            if ($countMots > 0) {
                                $condition_tmp .= ' AND ';
                            }
                            $this->_ta_bind[$this->_count_bind] = [];
                            $this->_ta_bind[$this->_count_bind]['table'] = $ta_search['table'];
                            $this->_ta_bind[$this->_count_bind]['field'] = $ta_search['field'];
                            if ($ta_search['operator'] == QueryOperator::FULLTEXT->value) {
                                $this->_ta_bind[$this->_count_bind]['value'] = $mot;
                            } elseif ($ta_search['operator'] == QueryOperator::FULLTEXT_LEFT->value) {
                                $this->_ta_bind[$this->_count_bind]['value'] = '%' . $mot;
                            } elseif ($ta_search['operator'] == QueryOperator::FULLTEXT_BOTH->value) {
                                $this->_ta_bind[$this->_count_bind]['value'] = '%' . $mot . '%';
                            } elseif ($ta_search['operator'] == QueryOperator::FULLTEXT_RIGHT->value) {
                                $this->_ta_bind[$this->_count_bind]['value'] = $mot . '%';
                            }
                            
                            $condition_tmp .= $ta_search['table'] . '.' . $ta_search['field'] . ' LIKE :bind' . $this->_count_bind++;
                            $countMots++;
                        }
                        $condition_tmp .= ' )';
                        $ta_where[] = $condition_tmp;
                    }
                } elseif (in_array($ta_search['operator'], [QueryOperator::LIKE->value, QueryOperator::NOT_LIKE->value,
                    QueryOperator::LIKE_LEFT->value, QueryOperator::NOT_LIKE_LEFT->value, QueryOperator::LIKE_BOTH->value,
                    QueryOperator::NOT_LIKE_BOTH->value, QueryOperator::LIKE_RIGHT->value, QueryOperator::NOT_LIKE_RIGHT->value])) {
                    $this->_ta_bind[$this->_count_bind] = [];
                    $this->_ta_bind[$this->_count_bind]['table'] = $ta_search['table'];
                    $this->_ta_bind[$this->_count_bind]['field'] = $ta_search['field'];
                    if ($ta_search['operator'] == QueryOperator::LIKE->value) {
                        $this->_ta_bind[$this->_count_bind]['value'] = $ta_search['value'];
                    } elseif ($ta_search['operator'] == QueryOperator::LIKE_LEFT->value) {
                        $this->_ta_bind[$this->_count_bind]['value'] = '%' . $ta_search['value'];
                    } elseif ($ta_search['operator'] == QueryOperator::LIKE_BOTH->value) {
                        $this->_ta_bind[$this->_count_bind]['value'] = '%' . $ta_search['value'] . '%';
                    } elseif ($ta_search['operator'] == QueryOperator::LIKE_RIGHT->value) {
                        $this->_ta_bind[$this->_count_bind]['value'] = $ta_search['value'] . '%';
                    }
                    $not = '';
                    if (in_array($ta_search['operator'], [QueryOperator::NOT_LIKE->value, QueryOperator::NOT_LIKE_LEFT->value,
                        QueryOperator::NOT_LIKE_BOTH->value, QueryOperator::NOT_LIKE_RIGHT->value])) {
                        $not = ' NOT';
                    }
                    $ta_where[] = $ta_search['table'] . '.' . $ta_search['field'] . $not . ' LIKE :bind' . $this->_count_bind++;
                } elseif (in_array($ta_search['operator'], [QueryOperator::IN_ARRAY->value, QueryOperator::NOT_IN_ARRAY->value])) {
                    if (is_array($ta_search['value'])) {
                        $IDs = '';
                        if (count($ta_search['value'])) {
                            foreach ($ta_search['value'] as $value) {
                                $this->_ta_bind[$this->_count_bind] = [];
                                $this->_ta_bind[$this->_count_bind]['table'] = $ta_search['table'];
                                $this->_ta_bind[$this->_count_bind]['field'] = $ta_search['field'];
                                $this->_ta_bind[$this->_count_bind]['value'] = $value;
                                $IDs .= ':bind' . $this->_count_bind++ . ',';
                            }
                            $IDs = rtrim($IDs, ",");
                        } else {
                            trigger_error('Attention, la valeur pour in_array ne devrait pas être vide : '.$ta_search['table'] . '.' . $ta_search['field'], E_USER_ERROR);
                        }
                        $not = '';
                        if ($ta_search['operator'] == 'not_in_array') {
                            $not = ' NOT';
                        }
                        $ta_where[] = $ta_search['table'] . '.' . $ta_search['field'] . $not . ' IN (' . $IDs . ')';
                    }
                } elseif ($ta_search['operator'] == QueryOperator::IS_NULL->value) {
                    $ta_where[] = $ta_search['table'] . '.' . $ta_search['field'] . ' IS NULL';
                } elseif ($ta_search['operator'] == QueryOperator::IS_NOT_NULL->value) {
                    $ta_where[] = $ta_search['table'] . '.' . $ta_search['field'] . ' IS NOT NULL';
                } elseif ($ta_search['operator'] == QueryOperator::RAW->value) {
                    $ta_where[] = $ta_search['table'] . '.' . $ta_search['field'] . ' ' . $ta_search['value'];
                } elseif ($ta_search['operator'] == QueryOperator::MATCH_AGAINST->value || $ta_search['operator'] == QueryOperator::MATCH_AGAINST_BOOLEAN->value) {
                    $this->_ta_bind[$this->_count_bind] = [];
                    $this->_ta_bind[$this->_count_bind]['table'] = $ta_search['table'];
                    $this->_ta_bind[$this->_count_bind]['field'] = $ta_search['field'];
                    $this->_ta_bind[$this->_count_bind]['value'] = $ta_search['value'];
                    $maWhere = "MATCH({$ta_search['table']}.{$ta_search['field']}) AGAINST (:bind" . $this->_count_bind++;
                    if ($ta_search['operator'] == QueryOperator::MATCH_AGAINST_BOOLEAN->value) {
                        $maWhere .= ' IN BOOLEAN MODE';
                    }
                    $maWhere .= ')';
                    $ta_where[] = $maWhere;
                } elseif ($ta_search['operator'] == QueryOperator::FULLTEXT_MATCH_AGAINST->value || $ta_search['operator'] == QueryOperator::FULLTEXT_MATCH_AGAINST_BOOLEAN->value) {
                    $this->_ta_bind[$this->_count_bind] = [];
                    $this->_ta_bind[$this->_count_bind]['table'] = $ta_search['table'];
                    if (preg_match('#^id[0-9]{1,}$#', $ta_search['value'])) {
                        $this->_ta_bind[$this->_count_bind]['field'] = $this->tableIdField();
                        $this->_ta_bind[$this->_count_bind]['value'] = (int)trim(substr($ta_search['value'], 2));
                        $ta_where[] = $ta_search['table'] . '.' . $this->tableIdField() . '=:bind' . $this->_count_bind++;
                    } else {
                        $search_value = trim($ta_search['value']);
                        
                        // Suppression des caractères spéciaux
                        $processed_value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $search_value);
                        // Normalisation des espaces multiples
                        $processed_value = preg_replace("/[[:space:]]{2,}/u", " ", $processed_value);
                        
                        $arrayRec = explode(" ", $processed_value);
                        $match = '';
                        
                        foreach ($arrayRec as $mot) {
                            $mot = trim($mot);
                            if (empty($mot)) {
                                continue;
                            }
                            
                            $match .= " +$mot*";
                        }
                        $this->_ta_bind[$this->_count_bind]['field'] = $ta_search['field'];
                        $this->_ta_bind[$this->_count_bind]['value'] = $match;
                        $maWhere = "MATCH({$ta_search['table']}.{$ta_search['field']}) AGAINST (:bind" . $this->_count_bind++;
                        if ($ta_search['operator'] == QueryOperator::FULLTEXT_MATCH_AGAINST_BOOLEAN->value) {
                            $maWhere .= ' IN BOOLEAN MODE';
                        }
                        $maWhere .= ')';
                        $ta_where[] = $maWhere;
                    }
                } else {
                    $this->_ta_bind[$this->_count_bind] = [];
                    $this->_ta_bind[$this->_count_bind]['table'] = $ta_search['table'];
                    $this->_ta_bind[$this->_count_bind]['field'] = $ta_search['field'];
                    $this->_ta_bind[$this->_count_bind]['value'] = $ta_search['value'];
                    $ta_where[] = $ta_search['table'] . '.' . $ta_search['field'] . ' ' . $ta_search['operator'] . ' :bind' . $this->_count_bind++;
                }
            }
            
            // Sous-requêtes
            if (array_key_exists('subRequest', $arg) && is_array($arg['subRequest']) && count($arg['subRequest'])) {
                foreach ($arg['subRequest'] as $ta_subRequest) {
                    if (!array_key_exists($ta_subRequest['table'], $ta_tables)) {
                        $this->recordTableField($ta_subRequest['table']);
                        $ta_tables = $this->fieldsList();
                    }
                    if (!array_key_exists($ta_subRequest['field'], $ta_tables[$ta_subRequest['table']])) {
                        trigger_error('Le champ : ' . $ta_subRequest['field'] . ' est introuvable dans la table : ' . $ta_subRequest['table'], E_USER_ERROR);
                    }
                    if (!in_array($ta_subRequest['operator'], QueryOperator::ALLOWED_SUBQUERY_OPERATORS)) {
                        trigger_error('Operateur inconnu : ' . $ta_subRequest['operator'], E_USER_ERROR);
                    }
                    if (!array_key_exists('subRequests', $arg) || !is_array($arg['subRequests']) || !array_key_exists($ta_subRequest['subRequest'], $arg['subRequests'])) {
                        trigger_error('Sous-requête inconnue', E_USER_ERROR);
                    }
                    
                    // Operator
                    $operator = str_replace('_', ' ', $ta_subRequest['operator']);
                    
                    // On passe temporairement sur la table de la sous-requête
                    $tmp_table = $this->tableName();
                    $this->setTableName($ta_subRequest['fromTable']);
                    $ta_where[] = $ta_subRequest['table'] . '.' . $ta_subRequest['field'] . ' ' . $operator . ' (' . $this->contruct_request($arg['subRequests'][$ta_subRequest['subRequest']], true) . ')';
                    $this->setTableName($tmp_table);
                }
            }
            
            // Séquencement des clauses WHERE
            $count = 0;
            if (count($ta_where)) {
                $request .= ' WHERE ';
                if (!isset($arg['sequence']) || !strlen($arg['sequence'])) {
                    foreach ($ta_where as $where) {
                        if ($count++) {
                            $request .= ' AND ';
                        }
                        $request .= $where;
                    }
                } else {
                    $chaine_where = $arg['sequence'];
                    // Vérification des caractères de la séquence
                    $verif_sequence = preg_replace('#WHERE[0-9]{1,}|[\(]|[\)]|[ ]|OR|AND#', '', $chaine_where);
                    if (strlen($verif_sequence)) {
                        trigger_error('Caractères indésirables dans la séquence de WHERE : ' . $verif_sequence, E_USER_ERROR);
                    }
                    foreach ($ta_where as $where) {
                        $count++;
                        $chaine_where = preg_replace('#WHERE' . $count . '\b#', $where, $chaine_where);
                    }
                    $request .= $chaine_where;
                }
            }
            
            // afterWhere
            if (array_key_exists('afterWhere', $arg)) {
                $request .= ' ' . $arg['afterWhere'] . ' ';
            }
            
            // Group by
            if (array_key_exists('groupBy', $arg) && is_array($arg['groupBy']) && count($arg['groupBy'])) {
                $request .= ' GROUP BY ';
                $count = 0;
                foreach ($arg['groupBy'] as $ta_groupBy) {
                    if (!array_key_exists($ta_groupBy['table'], $ta_tables)) {
                        $this->recordTableField($ta_groupBy['table']);
                        $ta_tables = $this->fieldsList();
                    }
                    if (!array_key_exists($ta_groupBy['field'], $ta_tables[$ta_groupBy['table']])) {
                        trigger_error('Le champ : ' . $ta_groupBy['field'] . ' est introuvable dans la table : ' . $ta_groupBy['table'], E_USER_ERROR);
                    }
                    if ($count++) {
                        $request .= ', ';
                    }
                    $request .= $ta_groupBy['table'] . '.' . $ta_groupBy['field'];
                }
            }
            
            if (!$flag_count) {
                // Tris
                if (array_key_exists('sort', $arg)) {
                    if (is_array($arg['sort'])) {
                        $count = 0;
                        foreach ($arg['sort'] as $ta_sort) {
                            if (!array_key_exists($ta_sort['table'], $ta_tables)) {
                                $this->recordTableField($ta_sort['table']);
                                $ta_tables = $this->fieldsList();
                            }
                            
                            // Si le champ est chiffré et qu'il a une colonne de tri, elle est utilisée
                            if ($this->isEncrypted($ta_sort['field']) && $this->isEncryptedSortable($ta_sort['field'])) {
                                $ta_sort['field'] = $ta_sort['field'] . '_tri';
                            }
                            
                            if (!array_key_exists($ta_sort['field'], $ta_tables[$ta_sort['table']])) {
                                trigger_error('Le champ : ' . $ta_sort['field'] . ' est introuvable dans la table : ' . $ta_sort['table'], E_USER_ERROR);
                            }
                            $request .= $count++ ? ', ' : ' ORDER BY ';
                            $request .= $ta_sort['table'] . '.' . $ta_sort['field'];
                            if (array_key_exists('reverse', $ta_sort) && $ta_sort['reverse']) {
                                $request .= ' DESC';
                            }
                        }
                    }
                }
                
                // Limites
                if (!array_key_exists('limit', $arg) || !$arg['limit']) {
                    $arg['limit'] = 0;
                }
                
                if (!array_key_exists('start', $arg) || !$arg['start']) {
                    $arg['start'] = 0;
                }
                
                // LIMIT doit être > 0 si OFFSET est utilisé
                if ($arg['limit'] == 0 && $arg['start'] > 0) {
                    trigger_error('limit doit être > 0 si start est > 0', E_USER_ERROR);
                }
                
                if ($arg['limit']) {
                    $request .= ' LIMIT ' . (int)$arg['limit'];
                }
                
                if ($arg['start']) {
                    $request .= ' OFFSET ' . (int)$arg['start'];
                }
            }
            
            if ($flag_count) {
                $request = 'SELECT COUNT(1) FROM (' . addslashes($request) . ') x';
            }
            
            $this->_ta_request_tables = array_merge($ta_tables, $this->_ta_request_tables);
            
            return $request;
        }
        
        /**
         * Décrypte les valeurs des champs cryptés de la table.
         *
         * @param array $data Tableau associatif des valeurs à décrypter.
         *
         * @return void
         */
        private function _descryptData(array &$data = []): void
        {
            // Champs à déchiffrer
            $taFieldsToDecrypt = array_intersect_key($data, array_flip($this->encryptedFields()));
            foreach ($taFieldsToDecrypt as $key => $fieldToDecrypt) {
                if (!empty($data[$key])) {
                    $data[$key] = $this->db->dbCrypt()->decrypt_string($data[$key]);
                }
            }
        }
        
        /**
         * Vérification et formatage des champs à récupérer par la requête SQL.
         *
         * @param string[]|string $fields
         *
         * @return string
         *
         * @throws Exception Si le champ spécifié n'existe pas.
         */
        private function _traitFieldsParamsQuery(array|string $fields = '*'): string
        {
            if (is_array($fields)) {
                $fieldsNotExists = array_filter($fields, fn($field) => !$this->fieldExists($field));
                if (!empty($fieldsNotExists)) {
                    throw new Exception('Champs inconnus : "' . implode(', ', $fieldsNotExists) . '" dans la table : "' . $this->tableName() . '"');
                }
                $fields = implode(', ', array_map(fn($field) => "`$field`", $fields));
            } elseif ($fields !== '*') {
                if (!$this->fieldExists($fields)) {
                    throw new Exception("Le champ spécifié '$fields' n'existe pas.");
                }
                $fields = "`$fields`";
            }
            
            return $fields;
        }
        
        /**
         * Bind des paramètres de la requête findBy et countBy.
         *
         * @param PDOStatement $stmt
         *
         * @return void
         */
        private function _bindParamsStmt(PDOStatement $stmt): void
        {
            foreach ($this->_ta_bind as $key => $infos_bind) {
                $type = $this->_ta_request_tables[$infos_bind['table']][$infos_bind['field']]['Type'];
                $this->_bind_query($stmt, ':bind' . $key, $type, $infos_bind['value']);
                $this->setDebugTxt('bind:' . $key . ' = ' . $infos_bind['value'] . ' (' . $type . ')');
            }
        }
        
        /**
         * Réalise le mapping de valeur d'un objet PDOStatement par rapport au type de champ.
         *
         * @param PDOStatement $stmt
         * @param string $bind Nom du bind dans la requête préparée.
         * @param string $type Type de la colonne.
         * @param ?string $value Valeur à mettre dans la colonne.
         *
         * @return void
         */
        private function _bind_query(PDOStatement $stmt, string $bind, string $type, null|string|array $value): void
        {
            if (in_array($type, array('bigint', 'int', 'tinyint', 'smallint', 'mediumint', 'year'))) {
                $stmt->bindValue($bind, is_null($value) ? $value : (int)$value, PDO::PARAM_INT);
            } elseif (in_array($type, array('time', 'date', 'datetime', 'timestamp', 'char', 'varchar'))) {
                $stmt->bindValue($bind, $value);
            } elseif ($type == 'enum') {
                $stmt->bindValue($bind, $value);
            } elseif (in_array($type, array('tinyblob', 'tinytext', 'blob', 'text', 'mediumblob', 'mediumtext', 'longblob', 'longtext'))) {
                $stmt->bindValue($bind, $value, PDO::PARAM_LOB);
            } elseif ($type == 'float') {
                $stmt->bindValue($bind, is_null($value) ? $value : (float)$value);
            } elseif ($type == 'double') {
                $stmt->bindValue($bind, is_null($value) ? $value : (double)$value);
            } elseif ($type == 'decimal') {
                $stmt->bindValue($bind, $value);
            } elseif($type == 'json') {
                $stmt->bindValue($bind, is_null($value) ? $value : json_encode($value));
            } else {
                trigger_error('Champ de type inconnu : ' . $type . ' dans la classe : ' . $this->className());
                $stmt->bindValue($bind, $value);
            }
        }
        
        /**
         * Sauvegarde d'un objet modele du manager dans la base de données.
         *
         * @param object $object Instance du modèle à sauvegarder.
         * @param bool $ignore Indique si on doit faire un insert ignore au lieu d'un insert.
         *
         * @return object Instance du modèle sauvegardé avec l'ID de la ligne.
         *
         * @throws Exception Si une erreur survient lors de la préparation.
         * @throws Exception Si une erreur survient lors de l'exécution de la requête.
         */
        protected function _add(object $object, bool $ignore = false): object
        {
            $taTables = $this->modelFieldsList();
            $fields = $values = $fieldsToPersist = array();

            // On n'inclut dans l'INSERT que les champs réellement renseignés (valeur non null).
            // Les colonnes omises reçoivent la valeur DEFAULT définie en base
            // (NULL, '', littéral ou expression telle que CURRENT_TIMESTAMP).
            // Un champ null = "non renseigné" : le setter du modèle refuse les null.
            foreach ($taTables as $infosField) {
                if (!$infosField['auto_increment'] && !is_null($object->{$infosField['Field']}())) {
                    $fieldsToPersist[] = $infosField;
                    $fields[] = $infosField['Field'];
                    $values[] = ':' . $infosField['Field'];
                }
            }
            $requete = ($ignore ? 'INSERT IGNORE INTO ' : 'INSERT INTO ') . $this->tableName();
            $requete .= empty($fields)
                ? ' () VALUES ()'
                : ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ')';
            $this->setDebugTxt($requete, true);
            
            if (false === $stmt = $this->db->pdo()->prepare($requete)) {
                $this->setErrorTxt('Erreur dans add():prepare() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requete);
                throw new Exception($this->errorTxt());
            }
            
            // Boucle sur les champs renseignés pour les bind
            foreach ($fieldsToPersist as $infosField) {
                $this->_bindParamsStmtPersist($object, $infosField, $stmt);
            }
            
            if (false === $stmt->execute()) {
                $this->setErrorTxt('Erreur dans add():execute() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requete);
                throw new Exception($this->errorTxt());
            }
            
            if ($ignore && !$stmt->rowCount()) {
                throw new Exception('Aucune ligne ajoutée');
            }
            
            $object->{'set' . ucfirst($this->tableIdField)}(intval($this->db->pdo()->lastInsertId()));
            
            return $object;
        }
        
        /**
         * Met à jour la ligne correspondante à l'objet modèle du manager dans la base de données.
         *
         * @param object $object Instance du modèle à mettre à jour.
         * @param string|string[] $taFields Liste des champs à mettre à jour.
         *
         * @return mixed
         *
         * @throws Exception Si une erreur survient lors de la préparation.
         * @throws Exception Si une erreur survient lors de l'exécution de la requête.
         */
        protected function _update(object $object, string|array $taFieldsUpdate = ''): object
        {
            $taFieldsUpdate = is_array($taFieldsUpdate) ? $taFieldsUpdate : (strlen($taFieldsUpdate) ? [$taFieldsUpdate] : []);

            // Vérifie que les champs explicitement demandés existent (détection de typo).
            foreach ($taFieldsUpdate as $field) {
                if (!$this->fieldExists($field)) {
                    trigger_error('Le champ ' . $field . ' n\'existe pas dans la table ' . $this->tableName(), E_USER_ERROR);
                }
            }

            // Boucle sur les champs pour les variables de bind.
            // On ne met à jour que les champs renseignés (valeur non null) : un champ null
            // signifie "non renseigné" (le setter du modèle refuse les null) et est laissé inchangé en base.
            $fieldsTable = $this->fieldsList()[$this->tableName()];
            $fieldsQueryUpdate = [];
            $fieldsToPersist = [];
            foreach ($fieldsTable as $infosField) {
                if (empty($taFieldsUpdate) || in_array($infosField['Field'], $taFieldsUpdate)) {
                    if (!$infosField['auto_increment'] && !is_null($object->{$infosField['Field']}())) {
                        $fieldsToPersist[] = $infosField;
                        $fieldsQueryUpdate[] = $infosField['Field'] . '=:' . $infosField['Field'];
                    }
                }
            }

            // Aucun champ renseigné à mettre à jour : rien à faire.
            if (empty($fieldsQueryUpdate)) {
                return $object;
            }

            $requete = 'UPDATE ' . $this->tableName() . ' SET ' . implode(',', $fieldsQueryUpdate);
            $requete .= ' WHERE ' . $this->tableIdField() . ' = :' . $this->tableIdField();
            $this->setDebugTxt($requete, true);
            
            if (false === $stmt = $this->db->pdo()->prepare($requete)) {
                $this->setErrorTxt('Erreur dans update():prepare() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requete);
                throw new Exception($this->errorTxt());
            }
            
            // Boucle sur les champs renseignés pour les bind
            foreach ($fieldsToPersist as $infosField) {
                $this->_bindParamsStmtPersist($object, $infosField, $stmt);
            }
            
            // Ajout du champ ID
            $field = $this->tableIdField();
            $stmt->bindValue(':' . $this->tableIdField(), $object->$field(), PDO::PARAM_INT);
            $this->setDebugTxt('bind:' . $this->tableIdField() . ' = ' . $object->$field() . ' (INTEGER)');

            if (false === $stmt->execute()) {
                $this->setErrorTxt('Erreur dans update():execute() - ' . implode(' - ', $this->db->pdo()->errorInfo()) . ' - ' . $requete);
                throw new Exception($this->errorTxt());
            }
            
            return $object;
        }
        
        /**
         * Bind des paramètres de la requête persist (add et update).
         *
         * @param object $object
         * @param array $infosField
         * @param PDOStatement $stmt
         *
         * @return void
         */
        private function _bindParamsStmtPersist(object $object, array $infosField, PDOStatement $stmt): void
        {
            if (!$infosField['auto_increment']) {
                if (in_array($infosField['Field'], $this->encryptedFields()) && !empty($object->{$infosField['Field']}())) {
                    $value = $this->db->dbCrypt()->encrypt_string($object->{$infosField['Field']}());
                } else {
                    // Le champ est garanti non null par les appelants (_add/_update filtrent les null).
                    $value = $object->{$infosField['Field']}();
                }
                $this->_bind_query($stmt, ':' . $infosField['Field'], $infosField['Type'], $value);
                $this->setDebugTxt('bind:' . $infosField['Field'] . ' = ' . var_export($value, true) . ' (' . $infosField['Type'] . ')');
            }
        }
        
        /**
         * @param DbConnect|string|null $connexion
         *
         * @return void
         *
         * @throws Exception
         */
        private function changeConnexion(null|DbConnect|string $connexion = null): void
        {
            if (empty($connexion)) {
                return;
            }
            
            if (is_object($connexion)) {
                $this->db = $connexion;
            } elseif (is_string($connexion)) {
                $this->db = ConnectionManager::get($connexion);
            } else {
                throw new Exception('Le paramètre de connexion doit être une instance de DbConnect ou une chaîne de caractères.');
            }
        }
        
        /**
         * @param DbConnect|string|null $connexion
         *
         * @param callable $callback
         *
         * @return mixed
         */
        private function withConnexion(null|DbConnect|string $connexion, callable $callback)
        {
            $previousDb = $this->db;
            
            if (!empty($connexion)) {
                $this->changeConnexion($connexion);
            }
            
            try {
                return $callback();
            } finally {
                $this->db = $previousDb;
            }
        }
        
        /**
         * Depuis un nom de table cherche un Manager puis renseigne la liste des champs de la table dans le Manager.
         *
         * @param string $table
         *
         * @return void
         *
         * @throws ManagerNotFoundException
         */
        private function recordTableField(string $table): void
        {
            $table = preg_replace('/ /', '', ucwords(preg_replace('/_/', ' ', $table)));
            $classe = 'src\manager\\' . $table . 'Manager';
            
            try {
                if (!class_exists($classe)) {
                    throw new ManagerNotFoundException("La classe '$classe' n'est pas un Manager valide.");
                }
            } catch (Throwable) {
                throw new ManagerNotFoundException("La classe '$classe' n'est pas un Manager valide.");
            }
            
            $obj = new $classe();
            
            if (!$obj instanceof Manager) {
                throw new ManagerNotFoundException("La classe '$classe' n'est pas un Manager valide.");
            }
            
            // On vérifie si ça n'a pas encore été chargé
            if (isset(self::$_fieldsList[$this->tableName()])) {
                unset($obj);
                return;
            }
            
            $obj->recordFields();
            unset($obj);
        }
    }
    