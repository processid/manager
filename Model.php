<?php
    
    namespace processid\manager;
    
    use DateTime;
    use Exception;
    use InvalidArgumentException;
    use processid\manager\attributes\Field;
    use processid\manager\exception\ModelMethodNotFoundException;
    use ReflectionClass;
    use ReflectionNamedType;
    use ReflectionProperty;
    use ValueError;
    
    /**
     * Classe de gestion d'un objet lié à une table dans la base de données.
     * C'est une classe fille de cette classe qui sera passée au manager.
     * Lors de l'ajout d'une colonne à une table, il faut créer la variable dans le modèle avant de créer la colonne en BD pour ne pas avoir d'erreur.
     * Lors de la suppression d'une colonne d'une table, il faut la supprimer en BD avant de supprimer la variable dans le modèle.
     *
     * @version 3
     * @package processid\model
     */
    abstract class Model
    {
        /** @var string[] Tableau contenant les setters qu'on va générer depuis les attributs Fields. Les attributs de classes doivent être protected. */
        protected array $ta_setters;
        /** @var string[] Tableau contenant les getters qu'on va générer depuis les attributs Fields. Les attributs de classes doivent être protected. */
        protected array $ta_getters;
        /** @var string[] Tableau contenant les getters qui vont retourner des données brutes (sans type) à persister dans la db. */
        protected array $ta_raw_getters;
        /** @var string[] Tableau contenant les types des propriétés annotés fields de la classe. */
        protected array $ta_types;
        protected static $ta_fields = [];
        
        /**
         * Hydrate les attributs de l'objet avec les données passées en paramètre.
         *
         * @param array $data
         */
        public function __construct(array $data = [])
        {
            $this->createSettersGetters();
            if (!empty($data)) {
                foreach ($data as $key => $value) {
                    if (is_null($value)) {
                        // Pour éviter de setter null dans les champs non nullables.
                        continue;
                    }
                    
                    $methodCamelCase = 'set' . ucfirst($this->toCamelCase($key));
                    
                    if (in_array($key, static::getAllsFields())) {
                        $this->$methodCamelCase($value);
                        continue;
                    }
                    
                    if (method_exists($this, $methodCamelCase)) {
                        $this->$methodCamelCase($value);
                    }
                }
            }
        }
        
        /**
         * Crée les setters et les getters pour les attributs de l'objet annotés avec l'attribut PHP8 Field.
         * Les setters sont générés au format setAttributeCamel() et setAttribute_camel().
         * Les getters sont générés au format getAttributeCamel()/isAttributeCamelBool et attribute_camel().
         *
         * @return void
         */
        protected function createSettersGetters(): void
        {
            $this->ta_setters = [];
            $this->ta_getters = [];
            
            // Utilisation de la réflexion pour obtenir les propriétés de la classe enfant
            $reflection = new ReflectionClass($this);
            
            foreach ($reflection->getProperties(ReflectionProperty::IS_PROTECTED) as $property) {
                
                // Récupère les attributs de la propriété
                $attributes = $property->getAttributes(Field::class);
                $propertyType = $property->getType();
                
                if (is_object($propertyType) && $propertyType instanceof ReflectionNamedType) {
                    $this->ta_types[$property->getName()] = $propertyType->getName();
                } else {
                    $this->ta_types[$property->getName()] = '';
                }
                
                // Si un attribut #[Field] est trouvé, ajoute un setter et un getter
                foreach ($attributes as $attribute) {
                    // Generation de l'ancien getter/setter
                    $this->ta_setters['set' . ucfirst($property->getName())] = $property->getName();
                    $this->ta_raw_getters[$property->getName()] = $property->getName();
                    
                    // Generation des setters et des getters standards
                    $camelCaseAttribute = ucfirst($this->toCamelCase($property->getName()));
                    $this->ta_setters['set' . ucfirst($camelCaseAttribute)] = $property->getName();
                    $type = $property->getType();
                    // gestion du type booléen pour le getter
                    if (is_object($type) && $type->getName() === 'bool') {
                        $this->ta_getters['is' . ucfirst($camelCaseAttribute)] = $property->getName();
                    } else {
                        $this->ta_getters['get' . ucfirst($camelCaseAttribute)] = $property->getName();
                    }
                }
            }
        }
        
        /**
         * Retourne l'objet sous forme de tableau associatif en prenant uniquement les attributs Fields.
         * On retourne la valeur depuis le getter standard.
         *
         * @param bool $excludeNull Indique si on exclu les valeurs NULL.
         *
         * @return array
         */
        public function asArray(bool $excludeNull = true): array
        {
            $array_return = [];
            
            // Utilisation de la réflexion pour obtenir les propriétés de la classe enfant
            $reflection = new ReflectionClass($this);
            
            foreach ($reflection->getProperties(ReflectionProperty::IS_PROTECTED) as $property) {
                // Récupère les attributs de la propriété
                $attributes = $property->getAttributes(Field::class);
                
                foreach ($attributes as $attribute) {
                    // On va chercher la valeur depuis le getter raw car utilisé principalement pour les retours d'APIS
                    if ($excludeNull && is_null($this->{$property->getName()}())) {
                        continue;
                    }
                    $array_return[$property->getName()] = $this->{$property->getName()}();
                }
            }
            
            return $array_return;
        }
        
        /**
         * Méthode magique pour gérer dynamiquement les appels aux getters et setters.
         * Cette méthode est appelée lorsqu'une méthode inaccessible ou inexistante est invoquée.
         * Elle vérifie si le nom de la méthode correspond à un setter ou un getter défini.
         *
         * @param $name
         * @param $arg
         *
         * @return mixed
         * @throws ModelMethodNotFoundException
         */
        function __call($name, $arg)
        {
            if (array_key_exists($name, $this->ta_setters)) {
                // Setter
                $property = $this->ta_setters[$name];
                $value = $arg[0];
                
                if (is_null($value)) {
                    return $this;
                }
                
                // Gestion des types spéciaux
                if (isset($this->ta_types[$property])) {
                    $type = $this->ta_types[$property];
                    
                    switch ($type) {
                        case 'DateTime':
                            if (!($value instanceof DateTime)) {
                                try {
                                    if (is_numeric($value) && (int)$value == $value && $value >= 0) {
                                        $value = (new DateTime())->setTimestamp((int)$value);
                                    } else {
                                        $value = new DateTime($value);
                                    }
                                } catch (Exception) {
                                    throw new InvalidArgumentException("La valeur pour {$name} n'est pas une date valide.");
                                }
                            }
                            break;
                        
                        case 'array':
                            if (!(is_array($value))) {
                                $value = json_decode($value, true);
                                if (!is_array($value)) {
                                    $value = [];
                                }
                            }
                            break;
                        // Ajouter d'autres cas si nécessaire
                        
                        default:
                            // Gestion des cas spéciaux
                            if (enum_exists($type)) {
                                // Gestion des énumérations natives PHP 8
                                if (!is_a($value, $type, true)) {
                                    try {
                                        $value = $type::from($value); // Convertir depuis une valeur (string, int, etc.)
                                    } catch (ValueError) {
                                        throw new InvalidArgumentException("La valeur pour {$name} n'est pas valide. Valeurs autorisées : " . implode(', ', array_map(fn($case) => $case->value, $type::cases())));
                                    }
                                }
                            }
                    }
                }
                
                $this->{$property} = $value;
                
                return $this;
            } elseif (array_key_exists($name, $this->ta_getters)) {
                // Getter
                return $this->{$this->ta_getters[$name]} ?? null;
            } elseif (array_key_exists($name, $this->ta_raw_getters)) {
                $property = $this->ta_raw_getters[$name];
                
                if (isset($this->ta_types[$property])) {
                    $type = $this->ta_types[$property];
                    
                    switch ($type) {
                        case 'DateTime':
                            return (!empty($this->{$property})) ? $this->{$property}->format('Y-m-d H:i:s') : null;
                        
                        case 'Date':
                            return (!empty($this->{$property})) ? $this->{$property}->format('Y-m-d') : null;
                        
                        // Ajouter d'autres cas si nécessaire
                        
                        default:
                            // Gestion des cas spéciaux
                            if (enum_exists($type)) {
                                // Gestion des énumérations natives PHP 8
                                return !empty($this->{$property}) ? $this->{$property}->value : null;
                            }
                    }
                }
                
                // Getter
                return $this->{$this->ta_raw_getters[$name]} ?? null;
            } else {
                throw new ModelMethodNotFoundException($name . '() introuvable dans la classe ' . get_class($this));
            }
        }
        
        /**
         * Converti un chaîne avec underscores au format camelCase.
         *
         * @param string $key
         *
         * @return string
         */
        protected function toCamelCase(string $key): string
        {
            $key = str_replace('_', ' ', strtolower($key));
            $key = ucwords($key);
            $key = str_replace(' ', '', $key);
            return lcfirst($key);
        }
        
        /**
         * Retourne la liste des noms des champs annotés avec #[Field].
         *
         * @return array
         */
        public static function getAllsFields(): array
        {
            if (!empty(static::$ta_fields[static::class])) {
                return static::$ta_fields[static::class];
            }
            
            $fieldNames = [];
            $reflection = new ReflectionClass(static::class);
            
            foreach ($reflection->getProperties(ReflectionProperty::IS_PROTECTED) as $property) {
                // Vérifie si la propriété est annotée avec #[Field]
                $attributes = $property->getAttributes(Field::class);
                if (!empty($attributes)) {
                    $fieldNames[] = $property->getName();
                }
            }
            
            static::$ta_fields[static::class] = $fieldNames;
            
            return static::$ta_fields[static::class];
        }
        
        /**
         * Vérifie si une méthode est accessible, y compris celles gérées par __call.
         *
         * @param string $method Le nom de la méthode.
         *
         * @return bool
         */
        public function hasMethod(string $method): bool
        {
            // Vérifie d'abord si la méthode est définie directement dans la classe
            if (method_exists($this, $method)) {
                return true;
            }
            
            // Vérifie si la méthode peut être gérée par __call (par exemple, dans les getters ou setters dynamiques)
            return isset($this->ta_setters[$method]) || isset($this->ta_getters[$method]) || isset($this->ta_raw_getters[$method]);
        }
    }
