<?php

    namespace processid\manager\unit;

    // Require des fichiers requis
    require_once __DIR__ . '/TestUnit.php';

    use Exception;
    use processid\manager\ConnectionManager;
    use processid\manager\DbConnect;
    use processid\manager\Manager;
    use processid\manager\unit\src\DynamicTableManager;
    use processid\manager\unit\src\InvalidFieldManager;
    use processid\manager\unit\src\SecretManager;
    use processid\manager\unit\src\UsersManager;
    use ReflectionClass;
    use ReflectionMethod;
    use ReflectionProperty;
    use RuntimeException;

    /**
     * Cache des métadonnées par classe (Manager::__construct()) et cache APCu optionnel des colonnes (recordFields()).
     *
     * Les tests APCu sont ignorés si APCu n'est pas actif dans le processus (en CLI : php -d apc.enable_cli=1).
     */
    class ManagerCacheTest extends TestUnit
    {
        /** @var array Configuration des connexions, conservée pour la reconfigurer après ConnectionManager::clear(). */
        private static array $dbConfigData = [];

        /**
         * Initialisation de la base de données.
         *
         * @return void
         */
        public static function setUpBeforeClass(): void
        {
            $dbConfigFile = __DIR__ . '/config/DbConfiguration.json';

            if (!file_exists($dbConfigFile)) {
                throw new Exception("Le fichier de configuration de la base de données est introuvable : " . $dbConfigFile);
            }

            $dbConfigData = json_decode(file_get_contents($dbConfigFile), true);

            if ($dbConfigData === null) {
                throw new RuntimeException("Erreur lors de la lecture de la configuration de la base de données : " . json_last_error_msg());
            }

            self::$dbConfigData = $dbConfigData;
            self::configureConnections();

            // Réinitialisation de la base de données
            ConnectionManager::get()->pdo()->exec(file_get_contents(__DIR__ . '/config/test.sql'));
        }

        /**
         * Chaque test part de caches vides et du cache APCu désactivé.
         *
         * @return void
         */
        protected function setUp(): void
        {
            Manager::disableColumnCache();
            Manager::clearMetadataCache();
        }

        /**
         * @return void
         */
        protected function tearDown(): void
        {
            Manager::disableColumnCache();
            Manager::clearMetadataCache();
        }

        /**
         * @return array
         */
        public static function managersProvider(): array
        {
            return [
                'UsersManager'  => [UsersManager::class],
                'SecretManager' => [SecretManager::class],
            ];
        }

        /**
         * Une instance servie par le cache est identique, propriété par propriété (privées comprises), à une instance
         * initialisée à froid ; la connexion est le même objet.
         *
         * @dataProvider managersProvider
         * @covers \processid\manager\Manager::__construct
         *
         * @param string $class
         *
         * @return void
         */
        public function testCachedInstanceEqualsColdInstance(string $class): void
        {
            $cold = new $class();
            $this->assertArrayHasKey($class, self::metadataCache(), 'Premier new : métadonnées non mémorisées');

            $warm = new $class();

            $properties = self::instanceProperties(new ReflectionClass($class));
            $this->assertArrayHasKey(Manager::class . '::db', $properties);
            $this->assertArrayHasKey(Manager::class . '::tableIdField', $properties);

            foreach ($properties as $name => $property) {
                $initialized = $property->isInitialized($cold);
                $this->assertSame($initialized, $property->isInitialized($warm), "Initialisation de $name");
                if ($initialized) {
                    $this->assertSame($property->getValue($cold), $property->getValue($warm), "Valeur de $name");
                }
            }
        }

        /**
         * Le cache est relu : une entrée modifiée est reprise par le new suivant (échoue si l'initialisation est rejouée).
         *
         * @covers \processid\manager\Manager::__construct
         *
         * @return void
         */
        public function testCacheIsRead(): void
        {
            new UsersManager();
            $cache = self::metadataCache();
            $cache[UsersManager::class]['after']['tableName'] = 'poisoned_table';
            self::setMetadataCache($cache);

            $this->assertSame('poisoned_table', (new UsersManager())->tableName());

            Manager::clearMetadataCache();
            $this->assertSame('users_tests', (new UsersManager())->tableName());
        }

        /**
         * Le second new réutilise l'entrée sans la réécrire.
         *
         * @covers \processid\manager\Manager::__construct
         *
         * @return void
         */
        public function testCacheIsNotRewrittenBySecondConstruction(): void
        {
            new UsersManager();
            $first = self::metadataCache();

            new UsersManager();
            $this->assertSame($first, self::metadataCache());
        }

        /**
         * La connexion est redemandée à ConnectionManager::get() à chaque construction : après une reconfiguration, une
         * instance servie par le cache reçoit la nouvelle connexion.
         *
         * @covers \processid\manager\Manager::__construct
         *
         * @return void
         */
        public function testConnectionIsRequestedOnEachConstruction(): void
        {
            $first = new UsersManager();
            $previousDb = self::dbOf($first);
            $this->assertSame(ConnectionManager::get(), $previousDb);

            ConnectionManager::clear();
            self::configureConnections();

            $second = new UsersManager();
            $this->assertArrayHasKey(UsersManager::class, self::metadataCache());
            $this->assertNotSame($previousDb, self::dbOf($second), 'Connexion reprise d\'un cache au lieu de ConnectionManager::get()');
            $this->assertSame(ConnectionManager::get(), self::dbOf($second));
        }

        /**
         * Une exception pendant l'initialisation ne laisse rien en cache : chaque construction relève l'exception.
         *
         * @covers \processid\manager\Manager::__construct
         *
         * @return void
         */
        public function testExceptionDuringInitIsNotCached(): void
        {
            for ($i = 0; $i < 2; $i++) {
                try {
                    new InvalidFieldManager();
                    $this->fail('Exception attendue : champ du modèle absent de la table');
                } catch (Exception $e) {
                    $this->assertStringContainsString('column_not_in_table', $e->getMessage());
                }
                $this->assertArrayNotHasKey(InvalidFieldManager::class, self::metadataCache());
            }
        }

        /**
         * Une propriété renseignée par instance avant parent::__construct() n'est pas écrasée par le cache.
         *
         * @covers \processid\manager\Manager::__construct
         *
         * @return void
         */
        public function testInstanceSpecificStateIsPreserved(): void
        {
            $this->assertSame('users_tests', (new DynamicTableManager('users_tests'))->tableName());
            $this->assertSame('secrets_tests', (new DynamicTableManager('secrets_tests'))->tableName());
            $this->assertSame('users_tests', (new DynamicTableManager('users_tests'))->tableName());
        }

        /**
         * clearMetadataCache() vide le cache par classe et la liste des colonnes du processus.
         *
         * @covers \processid\manager\Manager::clearMetadataCache
         *
         * @return void
         */
        public function testClearMetadataCache(): void
        {
            $manager = new UsersManager();
            $this->assertNotEmpty(self::metadataCache());
            $this->assertArrayHasKey('users_tests', self::fieldsListCache());

            Manager::clearMetadataCache();
            $this->assertSame([], self::metadataCache());
            $this->assertSame([], self::fieldsListCache());

            // L'instance existante relit les colonnes à la demande, un nouveau new réinitialise le cache.
            $this->assertTrue($manager->fieldExists('email'));
            new UsersManager();
            $this->assertArrayHasKey(UsersManager::class, self::metadataCache());
        }

        /**
         * Le cache APCu des colonnes est désactivé par défaut.
         *
         * @covers \processid\manager\Manager::isColumnCacheEnabled
         *
         * @return void
         */
        public function testColumnCacheIsDisabledByDefault(): void
        {
            $this->assertFalse((new ReflectionProperty(Manager::class, '_columnCacheEnabled'))->getDefaultValue());
            $this->assertFalse(Manager::isColumnCacheEnabled());
        }

        /**
         * Sans APCu, l'activation échoue et le cache reste désactivé.
         *
         * @covers \processid\manager\Manager::enableColumnCache
         *
         * @return void
         */
        public function testEnableColumnCacheFailsWithoutApcu(): void
        {
            if (self::apcuAvailable()) {
                $this->markTestSkipped('APCu actif dans ce processus.');
            }

            $this->assertFalse(Manager::enableColumnCache());
            $this->assertFalse(Manager::isColumnCacheEnabled());
            $this->assertFalse(Manager::clearColumnCache());
        }

        /**
         * Cache désactivé : APCu n'est ni lu (une entrée piégée est ignorée) ni écrit.
         *
         * @covers \processid\manager\Manager::recordFields
         *
         * @return void
         */
        public function testDisabledColumnCacheDoesNotUseApcu(): void
        {
            self::requireApcu($this);

            $key = self::keyFor('', 'users_tests');
            apcu_store($key, [self::fakeColumnRow('column_from_apcu')]);
            try {
                $manager = new UsersManager();
                $this->assertFalse($manager->fieldExists('column_from_apcu'), 'Entrée APCu lue alors que le cache est désactivé');
                $this->assertTrue($manager->fieldExists('email'));
                $this->assertSame([self::fakeColumnRow('column_from_apcu')], apcu_fetch($key), 'Entrée APCu réécrite alors que le cache est désactivé');
            } finally {
                apcu_delete($key);
            }
        }

        /**
         * Cache activé : la première lecture stocke les colonnes, la suivante (nouveau processus simulé) les relit depuis
         * APCu sans requête (une entrée modifiée dans APCu est reprise telle quelle).
         *
         * @covers \processid\manager\Manager::enableColumnCache
         * @covers \processid\manager\Manager::recordFields
         *
         * @return void
         */
        public function testEnabledColumnCacheIsStoredThenRead(): void
        {
            self::requireApcu($this);

            $this->assertTrue(Manager::enableColumnCache(60, 'manager-unit-test'));
            $this->assertTrue(Manager::isColumnCacheEnabled());
            Manager::clearColumnCache();
            $key = self::keyFor('manager-unit-test', 'users_tests');

            try {
                $cold = new UsersManager();
                $expected = $cold->modelFieldsList();
                $rows = apcu_fetch($key, $success);
                $this->assertTrue($success, 'Colonnes non stockées dans APCu');
                $this->assertEqualsCanonicalizing(['id', 'name', 'email', 'status', 'created_at'], array_column($rows, 'COLUMN_NAME'));

                // Nouveau « processus » : le cache du processus est vidé, APCu est conservé et piégé.
                Manager::clearMetadataCache();
                $rows[] = self::fakeColumnRow('column_from_apcu');
                apcu_store($key, $rows, 60);

                $warm = new UsersManager();
                $this->assertTrue($warm->fieldExists('column_from_apcu'), 'Colonnes relues en base au lieu d\'APCu');
                $this->assertSame($expected, $warm->modelFieldsList());
            } finally {
                Manager::clearColumnCache();
            }
        }

        /**
         * Une table sans colonne (absente) n'est pas mémorisée dans APCu.
         *
         * @covers \processid\manager\Manager::recordFields
         *
         * @return void
         */
        public function testEmptyColumnListIsNotStored(): void
        {
            self::requireApcu($this);

            Manager::enableColumnCache(60, 'manager-unit-test');
            $key = self::keyFor('manager-unit-test', 'table_that_does_not_exist');
            try {
                $this->assertFalse((new DynamicTableManager('table_that_does_not_exist'))->fieldExists('id'));
                $this->assertFalse(apcu_exists($key));
            } finally {
                Manager::clearColumnCache();
            }
        }

        /**
         * clearColumnCache() ne supprime que les entrées du préfixe configuré.
         *
         * @covers \processid\manager\Manager::clearColumnCache
         *
         * @return void
         */
        public function testClearColumnCacheOnlyRemovesConfiguredPrefix(): void
        {
            self::requireApcu($this);

            $otherKey = self::keyFor('manager-unit-test-other', 'users_tests');
            apcu_store($otherKey, [self::fakeColumnRow('id')]);
            try {
                Manager::enableColumnCache(60, 'manager-unit-test');
                new UsersManager();
                $key = self::keyFor('manager-unit-test', 'users_tests');
                $this->assertTrue(apcu_exists($key));

                $this->assertTrue(Manager::clearColumnCache());
                $this->assertFalse(apcu_exists($key));
                $this->assertTrue(apcu_exists($otherKey));
            } finally {
                apcu_delete($otherKey);
            }
        }

        /**
         * La clé distingue le préfixe, l'hôte, la base et la table.
         *
         * @covers \processid\manager\Manager::columnCacheKey
         *
         * @return void
         */
        public function testColumnCacheKeyIsDistinctPerPrefixHostDatabaseAndTable(): void
        {
            $reference = self::columnCacheKey('app', 'host1', 'db1', 'users');

            $this->assertSame($reference, self::columnCacheKey('app', 'host1', 'db1', 'users'));
            $this->assertNotSame($reference, self::columnCacheKey('app', 'host1', 'db2', 'users'));
            $this->assertNotSame($reference, self::columnCacheKey('app', 'host2', 'db1', 'users'));
            $this->assertNotSame($reference, self::columnCacheKey('other', 'host1', 'db1', 'users'));
            $this->assertNotSame($reference, self::columnCacheKey('app', 'host1', 'db1', 'orders'));
        }

        /**
         * @return void
         */
        private static function configureConnections(): void
        {
            ConnectionManager::setConfig(self::$dbConfigData);
            ConnectionManager::setDefaultProfile('test');
        }

        /**
         * @return array
         */
        private static function metadataCache(): array
        {
            return (new ReflectionProperty(Manager::class, '_metadataCache'))->getValue();
        }

        /**
         * @param array $cache
         *
         * @return void
         */
        private static function setMetadataCache(array $cache): void
        {
            (new ReflectionProperty(Manager::class, '_metadataCache'))->setValue(null, $cache);
        }

        /**
         * @return array
         */
        private static function fieldsListCache(): array
        {
            return (new ReflectionProperty(Manager::class, '_fieldsList'))->getValue();
        }

        /**
         * @param Manager $manager
         *
         * @return DbConnect
         */
        private static function dbOf(Manager $manager): DbConnect
        {
            return (new ReflectionProperty(Manager::class, 'db'))->getValue($manager);
        }

        /**
         * Propriétés d'instance de toute la hiérarchie (privées des parents comprises), indexées « Classe::nom ».
         *
         * @param ReflectionClass $class
         *
         * @return ReflectionProperty[]
         */
        private static function instanceProperties(ReflectionClass $class): array
        {
            $properties = [];
            for ($current = $class; $current !== false; $current = $current->getParentClass()) {
                foreach ($current->getProperties() as $property) {
                    if ($property->isStatic() || $property->getDeclaringClass()->getName() !== $current->getName()) {
                        continue;
                    }
                    $properties[$current->getName() . '::' . $property->getName()] = $property;
                }
            }

            return $properties;
        }

        /**
         * @param string $prefix
         * @param string $host
         * @param string $database
         * @param string $table
         *
         * @return string
         */
        private static function columnCacheKey(string $prefix, string $host, string $database, string $table): string
        {
            return (new ReflectionMethod(Manager::class, 'columnCacheKey'))->invoke(null, $prefix, $host, $database, $table);
        }

        /**
         * Clé APCu des colonnes d'une table pour la connexion par défaut.
         *
         * @param string $prefix
         * @param string $table
         *
         * @return string
         */
        private static function keyFor(string $prefix, string $table): string
        {
            $db = ConnectionManager::get();

            return self::columnCacheKey($prefix, (string)$db->host(), (string)$db->database(), $table);
        }

        /**
         * Ligne information_schema.columns factice.
         *
         * @param string $name
         *
         * @return array
         */
        private static function fakeColumnRow(string $name): array
        {
            return [
                'COLUMN_NAME'              => $name,
                'COLUMN_DEFAULT'           => null,
                'IS_NULLABLE'              => 'YES',
                'DATA_TYPE'                => 'varchar',
                'CHARACTER_MAXIMUM_LENGTH' => '255',
                'CHARACTER_OCTET_LENGTH'   => '1020',
                'NUMERIC_PRECISION'        => null,
                'NUMERIC_SCALE'            => null,
                'COLUMN_KEY'               => '',
                'EXTRA'                    => '',
            ];
        }

        /**
         * @return bool
         */
        private static function apcuAvailable(): bool
        {
            return function_exists('apcu_enabled') && apcu_enabled();
        }

        /**
         * @param TestUnit $test
         *
         * @return void
         */
        private static function requireApcu(TestUnit $test): void
        {
            if (!self::apcuAvailable()) {
                $test->markTestSkipped('APCu non actif dans ce processus (en CLI : php -d apc.enable_cli=1).');
            }
        }
    }
