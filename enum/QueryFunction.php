<?php
    
    namespace processid\manager\enum;
    
    /**
     * Enum QueryFunction
     *
     * Enumération des fonctions possible à utiliser par le fonction field du QueryBuilder.
     *
     * @package processid\manager
     */
    enum QueryFunction: string
    {
        case AVG = 'avg';
        case COUNT = 'count';
        case DISTINCT = 'distinct';
        case MAX = 'max';
        case MIN = 'min';
        case SUM = 'sum';
    }
