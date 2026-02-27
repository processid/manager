<?php

    namespace include\common\lib\vendor\processid\manager\exception;
    
    use Exception;
    
    /**
     * Exception levée lorsqu'on ne trouve pas de Manager sur le query Builder.
     *
     * @see \processid\manager\Model::__call()
     */
    class ManagerNotFoundException extends Exception
    {
    }
    