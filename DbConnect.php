<?php
    /*
    // Connexion à la base de données avec PDO et des fonctionnalités de chiffrement
    // -------------------
    // -- Instanciation --
    // -------------------
    $arg = array (
        'type'=>'mysql'
        ,'host'=>$db_host
        ,'database'=>$db_base
        ,'user'=>$db_user
        ,'pass'=>$db_pass
        ,'key_aes256'=><Clef générée avec \ProcessID\Encrypt\EncryptOpenSSL::generate_key_aes256();>
        ,'key_hash512'=><Clef générée avec \ProcessID\Encrypt\EncryptOpenSSL::generate_key_hash512();>
        ,'method'=>'aes-256-cbc'
    );
    $dbConnect = new DbConnect($arg);

    // Il est préférable d'effacer $arg pour ne pas propager les identifiants et clefs de chiffrement de la base de données:
    unset($arg);

    // DbConnect() fournit:
    // - Une instance de PDO : $this->pdo()
    // - Une instance de \processid\encrypt\EncryptOpenSSL : $this->dbCrypt() qui apporte encrypt_string() et decrypt_string() pré-configurés avec key_aes256 et key_hash512

    */
    
    namespace processid\manager;
    
    use Exception;
    use PDO;
    use processid\encrypt\EncryptOpenSSL;
    use processid\traits\Hydrate;
    
    class DbConnect
    {
        use Hydrate;
        
        private $_type;
        private $_host;
        private $_database;
        private $_user;
        private $_pass;
        private $_pdo;
        private $_dbCrypt;
        private $_key_aes256;
        private $_key_hash512;
        private $_method;
        
        public function __construct($donnees)
        {
            $this->hydrate($donnees);
            $this->connect();
            $this->encrypt();
        }
        
        public function type(): string
        {
            return $this->_type;
        }
        
        public function setType($type): void
        {
            if ($type == 'mysql') {
                $this->_type = $type;
            }
        }
        
        public function setHost($host): void
        {
            if (is_string($host)) {
                $this->_host = $host;
            }
        }
        
        public function setDatabase($database): void
        {
            if (is_string($database)) {
                $this->_database = $database;
            }
        }
        
        public function setUser($user): void
        {
            if (is_string($user)) {
                $this->_user = $user;
            }
        }
        
        public function setPass($pass): void
        {
            if (is_string($pass)) {
                $this->_pass = $pass;
            }
        }
        
        public function setKey_aes256($key_aes256): void
        {
            if (is_string($key_aes256)) {
                $this->_key_aes256 = $key_aes256;
            }
        }
        
        public function setKey_hash512($key_hash512): void
        {
            if (is_string($key_hash512)) {
                $this->_key_hash512 = $key_hash512;
            }
        }
        
        public function setMethod($method): void
        {
            if (is_string($method)) {
                $this->_method = $method;
            }
        }
        
        private function key_aes256(): string
        {
            return $this->_key_aes256;
        }
        
        private function key_hash512(): string
        {
            return $this->_key_hash512;
        }
        
        private function method(): string
        {
            return $this->_method;
        }
        
        function pdo(): PDO
        {
            return $this->_pdo;
        }
        
        function dbCrypt(): EncryptOpenSSL
        {
            return $this->_dbCrypt;
        }
        
        function connect(string $charset = 'utf8'): void
        {
            try {
                $dsn = $this->_type . ':host=' . $this->_host . ';dbname=' . $this->_database . ';charset=' . $charset;
                $options = array(
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . $charset . ' COLLATE ' . $charset . '_unicode_ci',
                );
                
                $this->_pdo = new PDO($dsn, $this->_user, $this->_pass, $options);
                $this->_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
                $this->_pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                
            } catch (Exception $e) {
                die('Erreur : ' . $e->getMessage());
            }
        }
        
        /**
         * Déconnexion de la base de données
         * @return void
         * @version 1.9.0
         */
        public function deconnect(): void
        {
            $this->_pdo = null;
        }
        
        /**
         * Vérifie si la connexion est établie
         * @return bool true si la connexion est établie, false sinon
         * @version 1.9.0
         */
        public function isConnected(): bool
        {
            if ($this->_pdo) {
                return true;
            }
            return false;
        }
        
        /**
         * @return void
         */
        function encrypt(): void
        {
            if (strlen($this->key_aes256())) {
                if (strlen($this->key_hash512()) && strlen($this->method())) {
                    $this->_dbCrypt = new EncryptOpenSSL($this->key_aes256(), $this->key_hash512(), $this->method());
                }
            }
        }
    }
