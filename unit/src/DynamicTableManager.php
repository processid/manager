<?php

    namespace processid\manager\unit\src;

    use processid\manager\Manager;

    /**
     * @Nota : pour test (table choisie par instance, renseignée avant parent::__construct())
     */
    class DynamicTableManager extends Manager
    {
        public function __construct(string $table)
        {
            $this->tableName = $table;
            parent::__construct();
        }
    }
