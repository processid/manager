<?php
    
    namespace include\common\lib\vendor\processid\manager\unit\src;
    
    use processid\manager\attributes\Field;
    use processid\manager\attributes\ID;
    use processid\manager\Model;
    
    /**
     * Classe de test lié à la table 'orders_tests'.
     */
    class Orders extends Model
    {
        #[Field('id')]
        #[ID]
        protected int $id;
        
        #[Field('user_id')]
        protected int $user_id;
        
        #[Field('total')]
        protected int $total;
        
        #[Field('status')]
        protected string $status;
        
        #[Field('created_at')]
        protected string $created_at;
    }
