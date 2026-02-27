<?php
    
    namespace processid\manager;
    
    /**
     * Class model standard qui crée dynamiquement les setters et les getters pour les attributs passer dans le constructeur
     */
    class StandardModel
    {
        protected array $ta_setters;
        protected array $ta_getters;
        private array $data = [];
        
        public function __construct(array $attributes = array())
        {
            $this->createSettersGetters($attributes);
        }
        
        /**
         * Crée dynamiquement les setters et les getters
         *
         * @param $attributes
         * @return void
         */
        protected function createSettersGetters($attributes): void
        {
            $this->ta_setters = [];
            $this->ta_getters = [];
            
            foreach ($attributes as $key => $value) {
                $property = $this->toCamelCase($key);
                $this->data[$key] = $value;
                
                // Générer dynamiquement les setters et getters
                $camelCaseAttribute = ucfirst($this->toCamelCase($property));
                $this->ta_setters['set' . ucfirst($camelCaseAttribute)] = $key;
                $this->ta_getters['get' . ucfirst($camelCaseAttribute)] = $key;
            }
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
         */
        function __call($name, $arg)
        {
            if (array_key_exists($name, $this->ta_setters)) {
                // Setter
                $value = $arg[0];
                if (is_null($value)) {
                    return $this;
                }
                $this->data[$this->ta_setters[$name]] = $value;
                
                return $this;
            } elseif (array_key_exists($name, $this->ta_getters)) {
                // Getter
                return $this->data[$this->ta_getters[$name]] ?? null;
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
            $key = str_replace('_', ' ', $key);
            $key = ucwords($key);
            $key = str_replace(' ', '', $key);
            return lcfirst($key);
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
            return isset($this->ta_setters[$method]) || isset($this->ta_getters[$method]);
        }
        
        /**
         * @return array
         */
        public function asArray(): array
        {
            $array_return = [];
            
            foreach ($this->ta_getters as $key => $value) {
                $array_return[$value] = $this->{$key}();
            }
            
            return $array_return;
        }
    }
