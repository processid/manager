<?php
    
    namespace processid\manager\unit\src;
    
    use processid\manager\attributes\ClassName;
    use processid\manager\attributes\Table;
    use processid\manager\Manager;
    
    /**
     * @Nota : pour test
     */
    #[ClassName(Users::class)]
    #[Table('users_tests')]
    class UsersManager extends Manager
    {
    }
