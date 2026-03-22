<?php
    
    namespace processid\manager\attributes;
    
    use Attribute;
    
    /**
     * Cette attribute une <b>attribute de class</b> utilisé sur les <b>class qui hérite de la class Manager</b>.
     * Définit le nom de la factory de connexion à la base de données associée à la classe Manager
     */
    #[Attribute]
    class DbFactory
    {
        public string $factoryName;
        
        public function __construct(string $name = 'main')
        {
            $this->factoryName = $name;
        }
    }