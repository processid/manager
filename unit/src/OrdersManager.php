<?php
    
    namespace include\common\lib\vendor\processid\manager\unit\src;
    
    use processid\manager\attributes\ClassName;
    use processid\manager\attributes\Table;
    use processid\manager\Manager;
    
    /**
     * @Nota : pour test
     */
    #[ClassName(Orders::class)]
    #[Table('orders_tests')]
    class OrdersManager extends Manager
    {
    }
