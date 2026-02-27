<?php
    
    namespace processid\manager\enum;
    
    /**
     * Enum QuerySequenceType
     *
     * Enumération des types de liaison possibles dans les conditions multiples, utilisées surtour dans la fonction sequence du QueryBuilder.
     *
     * @package processid\manager
     */
    enum QuerySequenceType: string
    {
        case OR = 'OR';
        case AND = 'AND';
    }
