<?php
    
    namespace processid\manager\attributes;
    
    use Attribute;
    
    /**
     * Cette attribute une <b>attribute de propriété</b> utilisé sur les <b>class qui hérite de la class Manager</b>.
     * Définit si la propriété est un champ de la table en base de données
     */
    #[Attribute]
    class Field
    {
        public string $fieldName;
        
        public function __construct(string $name)
        {
            $this->fieldName = $name;
        }
    }