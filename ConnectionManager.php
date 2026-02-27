<?php
    
    namespace processid\manager;
    
    use RuntimeException;
    
    /**
     * Gestionnaire central des connexions à la base de données.
     */
    class ConnectionManager
    {
        /**
         * Clé = nom du profil (ex: main, read_only)
         * Valeur = tableau des paramètres de connexion
         *
         * @var array<string, array>
         */
        private static array $configs = [];
        
        /**
         * Liste des connexions déjà instanciées.
         * Clé = nom du profil
         * Valeur = instance DbConnect
         *
         * @var array<string, DbConnect>
         */
        private static array $connections = [];
        
        /**
         * Profile de connexion par défaut utilisé lorsque aucun nom de profil n'est spécifié.
         *
         * @var string
         */
        private static string $defaultProfile = 'main';
        
        /**
         * Définit la configuration d'un profil de connexion.
         *
         * Cette méthode doit être appelée au démarrage de l'application
         * (bootstrap, index.php, kernel, etc.).
         *
         * @param array  $config Paramètres nécessaires à DbConnect
         *
         * @return void
         */
        public static function setConfig(array $config): void
        {
            self::$configs = $config;
        }
        
        /**
         * Définit le profil de connexion par défaut.
         *
         * @param string $profileName
         *
         * @return void
         */
        public static function setDefaultProfile(string $profileName): void
        {
            self::$defaultProfile = $profileName;
        }
        
        /**
         * Retourne une instance DbConnect pour le profil demandé.
         *
         * Si la connexion n'existe pas encore, elle est créée à partir
         * de la configuration enregistrée.
         *
         * @param ?string $name Nom du profil de connexion
         *
         * @throws RuntimeException Si aucune configuration n'a été définie
         *                          pour le profil demandé
         *
         * @return DbConnect
         */
        public static function get(?string $name = null): DbConnect
        {
            $name = $name ?? self::$defaultProfile;
            
            // Si la connexion n'a pas encore été créée
            if (!isset(self::$connections[$name])) {
                
                // Vérifie qu'une configuration existe pour ce profil
                if (!isset(self::$configs[$name])) {
                    throw new RuntimeException(
                        "Aucune configuration définie pour la connexion '$name'"
                    );
                }
                
                // Création et mise en cache de la connexion
                self::$connections[$name] = new DbConnect(self::$configs[$name]);
            }
            
            return self::$connections[$name];
        }
        
        /**
         * Supprime toutes les connexions et configurations enregistrées.
         *
         * Utile principalement pour les tests unitaires
         * afin de repartir d'un état propre.
         *
         * @return void
         */
        public static function clear(): void
        {
            self::$connections = [];
            self::$configs = [];
        }
    }
    