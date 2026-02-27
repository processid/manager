<?php
    
    namespace processid\manager\attributes;
    
    use Attribute;
    
    /**
     * Cette attribute une <b>attribute de propriété</b> utilisé sur les <b>class qui hérite de la class Manager</b>.
     * Définit si le champ est clé primaire
     * L'attribut Field doit être présent avant l'utilisation de cet attribut
     *
     * @see \processid\manager\attributes\Field
     */
    #[Attribute]
    class ID
    {
        public function __construct()
        {
        
        }
    }