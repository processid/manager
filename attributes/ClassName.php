<?php
    
    namespace processid\manager\attributes;
    
    use Attribute;
    
    /**
     * Cette attribute une <b>attribute de class</b> utilisé sur les <b>class qui hérite de la class Manager</b>.
     * Définit le nom de la classe modèle associée à la classe Manager
     */
    #[Attribute]
    class ClassName
    {
        public function __construct(
            public string $value
        )
        {
        }
    }