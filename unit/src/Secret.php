<?php

    namespace processid\manager\unit\src;

    use processid\manager\attributes\Encrypted;
    use processid\manager\attributes\Field;
    use processid\manager\attributes\ID;
    use processid\manager\Model;

    /**
     * Classe de test liée à la table 'secrets_tests' (champ chiffré).
     */
    class Secret extends Model
    {
        #[Field('id')]
        #[ID]
        protected int $id;

        #[Field('label')]
        protected string $label;

        #[Field('secret')]
        #[Encrypted]
        protected string $secret;
    }
