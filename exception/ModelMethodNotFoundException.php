<?php

    namespace processid\manager\exception;
    
    use Exception;
    
    /**
     * Exception levée lorsqu'une méthode n'est pas trouvée dans un modèle.
     *
     * @see \processid\manager\Model::__call()
     */
    class ModelMethodNotFoundException extends Exception
    {
    }
    