<?php
    
    namespace processid\manager\unit\src;
    
    use processid\manager\attributes\Field;
    use processid\manager\attributes\ID;
    use processid\manager\Model;
    
    /**
     * Classe de test lié à la table 'users_tests'.
     */
    class Users extends Model
    {
        #[Field('id')]
        #[ID]
        protected int $id;
        
        #[Field('name')]
        protected string $name;
        
        #[Field('email')]
        protected string $email;
        
        #[Field('status')]
        protected string $status;
        
        #[Field('created_at')]
        protected string $created_at;
        
        protected string $password;
    }
