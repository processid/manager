<?php
    
    namespace processid\manager\enum;
    
    /**
     * Enum QueryOperator
     *
     * Enumération des Opération possibles dans les conditions de la fonction where du QueryBuilder.
     *
     * @package processid\manager
     */
    enum QueryOperator: string
    {
        public const ALLOWED_SUBQUERY_OPERATORS = ['<', '>', '<=', '>=', '=', '!=', 'in', 'not_in'];
        
        case LESS = '<';
        case GREATER = '>';
        case LESS_OR_EQUAL = '<=';
        case GREATER_OR_EQUAL = '>=';
        case EQUAL = '=';
        case NOT_EQUAL = '!=';
        case IN_ARRAY = 'in_array';
        case NOT_IN_ARRAY = 'not_in_array';
        case FULLTEXT = 'fulltext';
        case FULLTEXT_LEFT = '%fulltext';
        case FULLTEXT_BOTH = '%fulltext%';
        case FULLTEXT_RIGHT = 'fulltext%';
        case LIKE = 'like';
        case NOT_LIKE = 'not_like';
        case LIKE_LEFT = '%like';
        case NOT_LIKE_LEFT = '%not_like';
        case LIKE_BOTH = '%like%';
        case NOT_LIKE_BOTH = '%not_like%';
        case LIKE_RIGHT = 'like%';
        case NOT_LIKE_RIGHT = 'not_like%';
        case IS_NULL = 'is_null';
        case IS_NOT_NULL = 'is_not_null';
        case RAW = 'raw';
        case MATCH_AGAINST = 'match_against';
        case MATCH_AGAINST_BOOLEAN = 'match_against_boolean';
        case FULLTEXT_MATCH_AGAINST = 'fulltext_match_against';
        case FULLTEXT_MATCH_AGAINST_BOOLEAN = 'fulltext_match_against_boolean';
    }
