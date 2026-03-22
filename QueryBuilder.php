<?php
    
    namespace processid\manager;
    
    use Exception;
    use include\common\lib\vendor\processid\manager\exception\ManagerNotFoundException;
    use InvalidArgumentException;
    use LogicException;
    use processid\manager\enum\QueryFunction;
    use processid\manager\enum\QueryOperator;
    use processid\manager\enum\QuerySequenceType;
    
    /**
     * Class QueryBuilder
     *
     * Permet de construire les arguments de requêtes pour la fonction findBy et countBy du Manager.
     */
    class QueryBuilder
    {
        /** @var Manager Manager de base du builder, il sera utilisé par défaut pour trouver la table à utiliser si ce n'est pas spécifié dans les fonctions. */
        private Manager $manager;
        /** @var array|string fields de l'arg, sera buildé grâce à la fonction fields. */
        private array|string $fields = '*';
        /** @var string[][] va stocker les fields qui ont déjà été validés pour éviter des doublons de validation, au format [table] = [field1, fields2] */
        private array $validesFields = [];
        private array $searches = [];
        private array $sorts = [];
        private int $start = 0;
        private int $limit = 0;
        private string $sequence = '';
        /** @var string[] Gestion des conditions groupés dans la séquence. */
        private array $groupStack = [];
        private string $beforeWhere = '';
        private string $afterWhere = '';
        private string $special = '';
        /** @var int Index de la recherche actuelle pour builder la séquence. */
        private int $sequenceIndex = 1;
        /** @var int Nombre d'appels aux fonctions search, on va builder la séquence en faisant la difference entre sequenceIndex au besoin. */
        private int $searchIndex = 1;
        
        public function __construct(Manager $manager)
        {
            $this->manager = $manager;
        }
        
        /**
         * Retourne le tableau d'arguments construit.
         *
         * @return array
         *
         * @throws LogicException Si une parenthèse de condition a été ouverte et n'a pas été fermée.
         */
        public function build(): array
        {
            if (!empty($this->groupStack)) {
                throw new LogicException('Une parenthèse de condition a été ouverte mais n\'a pas été fermée.');
            }
            
            // Dans le cas où on a fait des séquences spéciales, on vérifie si on a une marque
            if (!empty($this->sequence)) {
                $this->buildMissingSequence();
            }
            
            return [
                'fields' => $this->fields,
                'special' => $this->special,
                'search' => $this->searches,
                'sort' => $this->sorts,
                'start' => $this->start,
                'limit' => $this->limit,
                'sequence' => $this->sequence,
                'beforeWhere' => $this->beforeWhere,
                'afterWhere' => $this->afterWhere,
            ];
        }
        
        /**
         * Modification du DbConnect pour le manager.
         *
         * @param DbConnect|string $db
         *
         * @return $this
         *
         * @throws Exception
         */
        public function setDb(DbConnect|string $db): QueryBuilder
        {
            $this->manager->setDb($db);
            
            return $this;
        }
        
        /**
         * Appel la fonction findBy du manager depuis l'arg qu'on a construit.
         *
         * @param ?string $classname
         *
         * @return array
         *
         * @throws Exception Si la classe spécifiée n'existe pas.
         * @throws Exception Si une erreur survient lors de la préparation ou de l'exécution de la requête.
         * @throws LogicException Si une parenthèse de condition a été ouverte et n'a pas été fermée.
         *
         * @see Manager::findBy()
         */
        public function run(?string $classname = null): array
        {
            return $this->manager->findBy($this->build(), $classname);
        }
        
        /**
         * Appel la fonction countBy du manager depuis l'arg qu'on a construit.
         *
         * @return int
         *
         * @throws Exception Si une erreur survient lors de la préparation ou de l'exécution de la requête.
         * @throws LogicException Si une parenthèse de condition a été ouverte et n'a pas été fermée.
         *
         * @see Manager::countBy()
         */
        public function count(): int
        {
            return $this->manager->countBy($this->build());
        }
        
        /**
         * Met à jour les données du resultat de l'arg par rapport au paramètre dataUpdate.
         *
         * @param array $dataUpdate
         * @return int
         */
        public function update(array $dataUpdate): int
        {
            return $this->manager->updateBy($this->build(), $dataUpdate);
        }
        
        /**
         * Supprime les données du resultat de l'arg.
         *
         * @return int
         */
        public function delete(): int
        {
            return $this->manager->deleteBy($this->build());
        }
        
        /**
         * Ajoute un champ à sélectionner.
         *
         * @param string $field Nom du champ
         * @param string|Manager|null $table Nom de la table (par défaut la table du manager)
         * @param string|null $alias Alias du champ.
         * @param QueryFunction|null $function Fonction à appliquer sur le champ.
         *
         * @return $this
         *
         * @throws ManagerNotFoundException Si le nom de la classe passé en paramètre n'est pas trouvée ou n'hérite pas de Manager.
         */
        public function field(string $field, Manager|string|null $table = null, ?string $alias = null, ?QueryFunction $function = null): self
        {
            if (!is_array($this->fields)) {
                $this->fields = [];
            }
            
            // Déterminer le manager à utiliser
            $manager = $this->resolveManager($table);
            
            // Vérifier si le champ est valide
            if (!$this->isFieldValid($field, $manager)) {
                throw new InvalidArgumentException("Le champ '$field' n'est pas pris en charge par le manager ou la table spécifiée.");
            }
            
            // ajoute le champ valide dans la cache
            $this->addCacheValideField($this->getTableName($manager), $field);
            
            // Ajouter le champ à la liste avec les informations nécessaires
            $this->fields[] = [
                'table' => $this->getTableName($manager),
                'field' => $field,
                'alias' => $alias,
                'function' => is_null($function) ? '' : $function->value
            ];
            
            return $this;
        }
        
        /**
         * Spécifie la limite et l'offset de la requête.
         *
         * @param int $limit
         * @param int $start
         *
         * @return QueryBuilder
         */
        public function limit(int $limit = 0, int $start = 0): QueryBuilder
        {
            if ($limit < 0) {
                throw new InvalidArgumentException('La limite doit être un entier positif.');
            }
            
            if ($start < 0) {
                throw new InvalidArgumentException('L\'offset doit être un entier positif.');
            }
            
            $this->limit = $limit;
            $this->start = $start;
            
            return $this;
        }
        
        /**
         * Ajoute un tri.
         *
         * @param string $field Nom du champ
         * @param bool $reverse Ordre décroissant (par défaut faux)
         * @param Manager|string|null $table Nom de la table (par défaut la table du manager)
         *
         * @return $this
         *
         * @throws ManagerNotFoundException Si le nom de la classe passé en paramètre n'est pas trouvée ou n'hérite pas de Manager.
         */
        public function sort(string $field, bool $reverse = false, Manager|string|null $table = null): self
        {
            // Déterminer le manager à utiliser
            $manager = $this->resolveManager($table);
            
            // Vérifier si le champ est valide
            if (!$this->isFieldValid($field, $manager)) {
                throw new InvalidArgumentException("Le champ '$field' n'est pas pris en charge par le manager ou la table spécifiée.");
            }
            
            // ajoute le champ valide dans la cache
            $this->addCacheValideField($this->getTableName($manager), $field);
            
            $this->sorts[] = [
                'table' => $this->getTableName($manager),
                'field' => $field,
                'reverse' => $reverse,
            ];
            
            return $this;
        }
        
        /**
         * Ajoute un critère de recherche.
         *
         * @param string $field Nom du champ
         * @param mixed $value Valeur du critère
         * @param QueryOperator $operator Opérateur (par défaut '=')
         * @param Manager|string|null $table Nom de la table (par défaut la table du manager)
         *
         * @return $this
         *
         * @throws ManagerNotFoundException Si le nom de la classe passé en paramètre n'est pas trouvée ou n'hérite pas de Manager.
         */
        public function search(string $field, mixed $value, QueryOperator $operator = QueryOperator::EQUAL, Manager|string|null $table = null): QueryBuilder
        {
            // Déterminer le manager à utiliser
            $manager = $this->resolveManager($table);
            
            // Vérifier si le champ est valide
            if (!$this->isFieldValid($field, $manager)) {
                throw new InvalidArgumentException("Le champ '$field' n'est pas pris en charge par le manager ou la table spécifiée.");
            }
            
            // ajoute le champ valide dans la cache
            $this->addCacheValideField($this->getTableName($manager), $field);
            
            //@NOTA : il faut utiliser la valeur de retour de $manager, verif si c'est un obj
            
            $this->searches[] = [
                'table' => $this->getTableName($manager),
                'field' => $field,
                'value' => $value,
                'operator' => $operator->value,
            ];
            
            // Incrémentation de searchIndex
            $this->searchIndex++;
            
            return $this;
        }
        
        /**
         * Ajout d'une séquence de requête.
         *
         * @param QuerySequenceType $type
         *
         * @return void
         */
        public function addSequence(QuerySequenceType $type = QuerySequenceType::AND): void
        {
            //si le dernier élément de la séquence n'est pas une parenthèse ouvrante, alors on ajoute un espace
            $prefix = (!empty($this->sequence) && !str_ends_with($this->sequence, '(')) ? ' ' . $type->value . ' ' : '';
            
            $this->sequence .= $prefix . 'WHERE' . $this->sequenceIndex;
            $this->sequenceIndex++;
        }
        
        /**
         * Ajoute un critère de recherche avec une condition OR.
         *
         * @param string $field
         * @param mixed $value
         * @param QueryOperator $operator
         * @param Manager|string|null $table
         *
         * @return $this
         *
         * @throws ManagerNotFoundException Si le nom de la classe passé en paramètre n'est pas trouvée ou n'hérite pas de Manager
         */
        public function orSearch(string $field, mixed $value, QueryOperator $operator = QueryOperator::EQUAL, Manager|string|null $table = null): QueryBuilder
        {
            $this->buildMissingSequence();
            $this->search($field, $value, $operator, $table);
            $this->addSequence(QuerySequenceType::OR);
            
            return $this;
        }
        
        /**
         * Ajoute un critère de recherche avec une condition AND.
         *
         * @param string $field
         * @param mixed $value
         * @param QueryOperator $operator
         * @param Manager|string|null $table
         *
         * @return $this
         *
         * @throws ManagerNotFoundException Si le nom de la classe passé en paramètre n'est pas trouvée ou n'hérite pas de Manager.
         */
        public function andSearch(string $field, mixed $value, QueryOperator $operator = QueryOperator::EQUAL, Manager|string|null $table = null): QueryBuilder
        {
            $this->buildMissingSequence();
            $this->search($field, $value, $operator, $table);
            $this->addSequence();
            
            return $this;
        }
        
        /**
         * Permet d'ouvir une parathèse pour un groupe de conditions.
         * WHERE1 AND (WHERE2 OR WHERE3)
         *
         * @param QuerySequenceType $type condition avant la parenthèse dans le cas où on ajoute la parenthèse après des conditions.
         *
         * @return $this
         */
        public function openGroup(QuerySequenceType $type = QuerySequenceType::AND): QueryBuilder
        {
            // On va ajouter les séquences manquantes avant
            $this->buildMissingSequence();
            
            if (!empty($this->sequence) && !str_ends_with($this->sequence, '(')) {
                $this->sequence .= ' ' . $type->value;
            }
            
            $this->groupStack[] = '('; // Ajouter un groupe à la pile
            $this->sequence .= ' ('; // Ouvrir un groupe dans la séquence
            
            return $this;
        }
        
        /**
         * Permet de fermer une parenthèse pour un groupe de conditions.
         *
         * @return $this
         */
        public function closeGroup(): QueryBuilder
        {
            $this->buildMissingSequence();
            if (empty($this->groupStack)) {
                throw new LogicException('Aucune parenthèse de condition ouverte.');
            }
            array_pop($this->groupStack); // Retirer le dernier groupe de la pile
            $this->sequence .= ') '; // Fermer un groupe dans la séquence
            
            return $this;
        }
        
        /**
         * Définit une séquence personnalisée pour les clauses WHERE.
         *
         * @param string $sequence
         *
         * @return $this
         */
        public function sequence(string $sequence): QueryBuilder
        {
            $this->sequence = $sequence;
            
            return $this;
        }
        
        /**
         * Ajout de jointures dans la requête.
         *
         * @param string $field Nom de la colonne dans la table à joindre.
         * @param Manager|string $onTable
         * @param string|null $onField
         * @param Manager|string|null $table Nom de la table actuelle.
         * @param string $method Méthode de jointure (INNER, LEFT, RIGHT)
         *
         * @return $this
         *
         * @throws ManagerNotFoundException Si le nom de la classe passé en paramètre n'est pas trouvé ou n'hérite pas de Manager.
         */
        public function join(string $field, Manager|string $onTable, ?string $onField = null, Manager|string|null $table = null, string $method = 'INNER'): self
        {
            // récupération des managers pour les tables
            $localManager = $this->resolveManager($table);
            $joinManager = $this->resolveManager($onTable);
            
            // validation des fields
            if (is_null($onField)) {
                $onField = $field;
            }
            
            // Validation field de la jointure
            if (!$this->isFieldValid($field, $joinManager)) {
                throw new InvalidArgumentException("Le champ '$field' n'est pas pris en charge par le manager ou la table spécifiée.");
            }
            
            // Validation on field
            if (!$this->isFieldValid($onField, $localManager)) {
                throw new InvalidArgumentException("Le champ '$field' n'est pas pris en charge par le manager ou la table spécifiée.");
            }
            
            // ajoute le champ valide dans la cache
            $this->addCacheValideField($this->getTableName($joinManager), $field);
            $this->addCacheValideField($this->getTableName($localManager), $onField);
            
            // Construire la clause JOIN
            $joinClause = sprintf(
                '%s JOIN %s ON %s.%s = %s.%s',
                strtoupper($method), // INNER, LEFT, RIGHT, etc.
                $this->getTableName($joinManager),  // Nom de la table cible
                $this->getTableName($localManager), // Table locale
                $onField, // Champ local
                $this->getTableName($joinManager), // Table cible
                $field  // Champ cible
            );
            
            // Ajouter la jointure à la liste
            $this->beforeWhere .= (empty($this->beforeWhere) ? '' : ' ') . $joinClause;
            
            return $this;
        }
        
        /**
         * Ajoute une clause GROUP BY.
         * On peut l'appeler plusieurs fois pour faire un group by sur plusieurs colonnes.
         * Il doit idéalement être lancé avant le build, car on a besoin que le champ utilisé soit déjà utilisé (field, join, etc).
         *
         * @param string $field
         * @param Manager|string|null $table
         *
         * @return $this
         *
         * @throws ManagerNotFoundException Si le nom de la classe passé en paramètre n'est pas trouvée ou n'hérite pas de Manager.
         * @throws LogicException Si le champ n'a pas été sélectionné.
         */
        public function groupBy(string $field, Manager|string|null $table = null): self
        {
            // Préfixe avec le nom de la table si fourni
            $manager = $this->resolveManager($table);
            
            if (!in_array($field, $this->validesFields[$this->getTableName($manager)])) {
                throw new LogicException("Le champ '$field' n'a pas été sélectionné et ne peut pas être utilisé dans un GROUP BY.");
            }
            
            $groupByClause = $this->getTableName($manager) . '.' . $field;
            
            // Vérifie si 'afterWhere' existe déjà, sinon initialise
            $afterWhere = $this->afterWhere;
            if (empty($afterWhere)) {
                $this->afterWhere = 'GROUP BY ' . $groupByClause;
            } else {
                // Ajoute une virgule et concatène
                $this->afterWhere .= ', ' . $groupByClause;
            }
            
            return $this;
        }
        
        /**
         * @return string
         */
        public function getBeforeWhere(): string
        {
            return $this->beforeWhere;
        }
        
        /**
         * @param string $beforeWhere
         */
        public function setBeforeWhere(string $beforeWhere): void
        {
            $this->beforeWhere = $beforeWhere;
        }
        
        /**
         * @param string $afterWhere
         */
        public function setAfterWhere(string $afterWhere): QueryBuilder
        {
            $this->afterWhere = $afterWhere;
            
            return $this;
        }
        
        /**
         * @return string
         */
        public function getAfterWhere(): string
        {
            return $this->afterWhere;
        }
        
        /**
         * Réinitialise les paramètres de la requête.
         *
         * @return void
         */
        public function flush(): void
        {
            $this->fields = '*';
            $this->validesFields = [];
            $this->searches = [];
            $this->sorts = [];
            $this->start = 0;
            $this->limit = 0;
            $this->sequence = '';
            $this->groupStack = [];
            $this->beforeWhere = '';
            $this->afterWhere = '';
            $this->special = '';
            $this->sequenceIndex = 1;
            $this->searchIndex = 1;
        }
        
        /**
         * Récupération du Manager depuis la table ou l'instance passée en paramètre.
         *
         * @param Manager|string|null $table
         *
         * @return Manager|string
         *
         * @throws ManagerNotFoundException Si le nom de la classe passé en paramètre n'est pas trouvée ou n'hérite pas de Manager.
         */
        private function resolveManager(Manager|string|null $table): Manager|string
        {
            if ($table === null) {
                // Par défaut, utilise le manager de l'instance actuelle
                return $this->manager;
            }
            
            if (is_object($table)) {
                if (!($table instanceof Manager)) {
                    // Si c'est déjà un manager, on le retourne tel quel
                    throw new ManagerNotFoundException("La classe '$table' n'est pas un Manager valide.");
                }
                
                return $table;
            }
            
            if (class_exists($table)) {
                // Si c'est une classe valide, on vérifie si c'est un Manager
                $manager = new $table();
                if (!$manager instanceof Manager) {
                    throw new ManagerNotFoundException("La classe '$table' n'est pas un Manager valide.");
                }
                
                return $manager;
            }
            
            if (str_contains($table, '\\') || preg_match('/^[A-Z]/', $table)) {
                // Si le nom commence par une majuscule ou contient \ on considère que c'est une classe manquante
                throw new ManagerNotFoundException("Impossible de trouver ou d'instancier le Manager '$table'.");
            }
            
            // Sinon, c'est le nom d'une table
            return $table;
        }
        
        /**
         * Retourne le nom de la table depuis le retour de resolveManager.
         *
         * @param Manager|string $manager
         *
         * @return string
         */
        private function getTableName(Manager|string $manager): string
        {
            if (is_string($manager)) {
                return $manager;
            }
            
            return $manager->tableName();
        }
        
        /**
         * Ajoute un field valid.
         *
         * @param string $table
         * @param string $field
         *
         * @return void
         */
        private function addCacheValideField(string $table, string $field): void
        {
            if (!isset($this->validesFields[$table])) {
                $this->validesFields[$table] = [];
            }
            
            if (!in_array($field, $this->validesFields[$table])) {
                $this->validesFields[$table][] = $field;
            }
        }
        
        /**
         * Validation du champ.
         *
         * @param string $field
         * @param Manager|string $manager
         *
         * @return bool
         */
        private function isFieldValid(string $field, Manager|string $manager): bool
        {
            // Vérification de la cache
            if (isset($this->validesFields[$this->getTableName($manager)]) && in_array($field, $this->validesFields[$this->getTableName($manager)])) {
                return true;
            }
            
            if (is_string($manager)) {
                // Si le manager est une chaîne représentant une table, considérer le champ comme valide
                return true;
            }
            
            if (in_array($field, array_keys($manager->modelFieldsList()))) {
                // Si le champ est dans la liste des champs du modèle, on considère le champ comme valide
                return true;
            }
            
            return false;
        }
        
        /**
         * Build de la séquence si search a déjà été appelé.
         *
         * @return void
         */
        private function buildMissingSequence(): void
        {
            while ($this->sequenceIndex < $this->searchIndex) {
                $this->addSequence();
            }
        }
    }
    