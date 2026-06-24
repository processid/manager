<?php

    namespace processid\manager\unit\src;

    use processid\manager\attributes\ClassName;
    use processid\manager\attributes\Table;
    use processid\manager\Manager;

    /**
     * @Nota : pour test (champ chiffré via insertMultiple)
     */
    #[ClassName(Secret::class)]
    #[Table('secrets_tests')]
    class SecretManager extends Manager
    {
    }
