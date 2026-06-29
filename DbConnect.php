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
        ,'charset'=>'utf8mb4' // optionnel : jeu de caractères de la connexion (défaut 'utf8')
        ,'key_aes256'=><Clef générée avec \ProcessID\Encrypt\EncryptOpenSSL::generate_key_aes256();>
        ,'key_hash512'=><Clef générée avec \ProcessID\Encrypt\EncryptOpenSSL::generate_key_hash512();>
        ,'method'=>'aes-256-cbc'
    );
    // Les clefs 'key_aes256', 'key_hash512' et 'method' sont optionnelles : sans elles, le chiffrement est désactivé.
    $dbConnect = new DbConnect($arg);

    // Il est préférable d'effacer $arg pour ne pas propager les identifiants et clefs de chiffrement de la base de données:
    unset($arg);

    // DbConnect() fournit:
    // - Une instance de PDO : $this->pdo()
    // - Une instance de \processid\encrypt\EncryptOpenSSL : $this->dbCrypt() qui apporte encrypt_string() et decrypt_string() pré-configurés avec key_aes256 et key_hash512

    */
    
    namespace processid\manager;
    
    use PDO;
    use PDOException;
    use processid\encrypt\EncryptOpenSSL;
    use processid\traits\Hydrate;
    use RuntimeException;
    
    class DbConnect
    {
        use Hydrate;
        
        private $_type;
        private $_host;
        private $_database;
        private $_user;
        private $_pass;
        private $_charset = 'utf8';
        private $_pdo;
        private $_dbCrypt;
        private $_key_aes256 = '';
        private $_key_hash512 = '';
        private $_method = '';
        
        public function __construct($donnees)
        {
            $this->hydrate($donnees);
            $this->connect($this->_charset);
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

        public function setCharset($charset): void
        {
            if (is_string($charset)) {
                $this->_charset = $charset;
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
        
        /**
         * Ouvre la connexion PDO à la base de données.
         *
         * @param string $charset Jeu de caractères de la connexion (ex: 'utf8', 'utf8mb4').
         * @return void
         * @throws RuntimeException Si la connexion à la base de données échoue.
         */
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
                
            } catch (PDOException $e) {
                throw new RuntimeException('Connexion à la base de données impossible.', 0, $e);
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
         * Initialise le chiffrement transparent selon les clés fournies.
         *
         * Règle « tout ou rien » :
         * - aucune clé fournie  → chiffrement désactivé (opt-out) ;
         * - les trois clés      → chiffrement activé (opt-in) ;
         * - jeu de clés partiel → exception (configuration incohérente).
         *
         * @return void
         * @throws RuntimeException Si la configuration de chiffrement est incomplète.
         */
        function encrypt(): void
        {
            $aes    = (string) $this->key_aes256();
            $hash   = (string) $this->key_hash512();
            $method = (string) $this->method();

            if ($aes === '' && $hash === '' && $method === '') {
                return;
            }

            if ($aes === '' || $hash === '' || $method === '') {
                throw new RuntimeException('Configuration de chiffrement incomplète : key_aes256, key_hash512 et method doivent être fournis ensemble.');
            }

            $this->_dbCrypt = new EncryptOpenSSL($aes, $hash, $method);
        }
    }
