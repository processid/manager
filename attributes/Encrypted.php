<?php
    
    namespace processid\manager\attributes;
    
    use Attribute;
    
    /**
     * Cette attribute une <b>attribute de propriété</b> utilisé sur les <b>class qui hérite de la class Manager</b>.
     * Définit si un champ est crypté, et la valeur du champ sera cryptée en base de données
     * L'attribut Field doit être présent avant l'utilisation de cet attribut
     *
     * @see \processid\manager\attributes\Field
     * @see \processid\encrypt\EncryptOpenSSL
     */
    #[Attribute]
    class Encrypted
    {
        public function __construct()
        {
        
        }
    }