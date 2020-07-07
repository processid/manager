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
   // - Une instance de \ProcessID\Chiffrement\ChiffrementOpenSSL : $this->dbCrypt() qui apporte chiffre_chaine() et dechiffre_chaine() pré-configurés avec key_aes256 et key_hash512
   
*/   
   namespace ProcessID\Manager;
   
   use \PDO;
   use \ProcessID\Encrypt\EncryptOpenSSL;
   
   class DbConnect {
      
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
      
      public function __construct($donnees) {
         $this->hydrate($donnees);
         $this->connect();
         $this->encrypt();
      }
      
      protected function hydrate(array $donnees) {
         foreach ($donnees as $key => $value) {
            $method = 'set'.ucfirst($key);
            if (method_exists($this, $method)) {
               $this->$method($value);
            }
         }
      }
      
      function type() { return $this->_type; }
       
      function setType($type) {
         if ($type == 'mysql') {
            $this->_type = $type;
         }
      }
       
      function setHost($host) {
         if (is_string($host)) {
            $this->_host = $host;
         }
      }
      
      function setDatabase($database) {
         if (is_string($database)) {
            $this->_database = $database;
         }
      }
      
      function setUser($user) {
         if (is_string($user)) {
            $this->_user = $user;
         }
      }
      
      function setPass($pass) {
         if (is_string($pass)) {
            $this->_pass = $pass;
         }
      }
      
      function setKey_aes256($key_aes256) {
         if (is_string($key_aes256)) {
            $this->_key_aes256 = $key_aes256;
         }
      }
      
      function setKey_hash512($key_hash512) {
         if (is_string($key_hash512)) {
            $this->_key_hash512 = $key_hash512;
         }
      }
      
      function setMethod($method) {
         if (is_string($method)) {
            $this->_method = $method;
         }
      }
      
      private function key_aes256() {
         return $this->_key_aes256;
      }
      
      private function key_hash512() {
         return $this->_key_hash512;
      }
      
      private function method() {
         return $this->_method;
      }
      
      function pdo() {
         return $this->_pdo;
      }
      
      function dbCrypt() {
         return $this->_dbCrypt;
      }
      
      function connect() {
         try {
            $dsn = $this->_type . ':host=' . $this->_host . ';dbname=' . $this->_database . ';charset=utf8';
            $options = array(
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
            );
            
            $this->_pdo = new PDO($dsn, $this->_user, $this->_pass, $options);
            $this->_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
            
         } catch (Exception $e) {
            die('Erreur : ' . $e->getMessage());
         }
      }
      
      function encrypt() {
         if (strlen($this->key_aes256())) {
            if (strlen($this->key_hash512()) && strlen($this->method())) {
               $this->_dbCrypt = new EncryptOpenSSL($this->key_aes256(),$this->key_hash512(),$this->method());
            }
         }
      }
   }
?>
