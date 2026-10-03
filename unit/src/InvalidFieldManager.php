<?php

    namespace processid\manager\unit\src;

    use processid\manager\attributes\ClassName;
    use processid\manager\attributes\Table;
    use processid\manager\Manager;

    /**
     * @Nota : pour test (le constructeur lève une exception : champ du modèle absent de la table)
     */
    #[ClassName(InvalidField::class)]
    #[Table('users_tests')]
    class InvalidFieldManager extends Manager
    {
    }
