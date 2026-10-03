<?php

    namespace processid\manager\unit\src;

    use processid\manager\attributes\Field;
    use processid\manager\attributes\ID;
    use processid\manager\Model;

    /**
     * Classe de test liée à la table 'users_tests' avec un champ absent de la table (initialisation en erreur).
     */
    class InvalidField extends Model
    {
        #[Field('id')]
        #[ID]
        protected int $id;

        #[Field('column_not_in_table')]
        protected string $missing;
    }
