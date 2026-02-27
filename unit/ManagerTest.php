<?php
    
    namespace processid\manager\unit;
    
    // Require des fichiers requis
    require_once __DIR__ . '/TestUnit.php';
    
    use Exception;
    use processid\manager\ConnectionManager;
    use processid\manager\enum\QueryOperator;
    use processid\manager\Manager;
    use processid\manager\unit\src\Users;
    use processid\manager\unit\src\UsersManager;
    use RuntimeException;
    
    class ManagerTest extends TestUnit
    {
        private Manager $manager;
        
        /**
         * Initialisation de la base de données
         *
         * @return void
         */
        public static function setUpBeforeClass(): void
        {
            echo 'Initialisation de la base de données ===========================================' . PHP_EOL;
            $dbConfigFile = __DIR__ . '/config/DbConfiguration.json';
            
            if (!file_exists($dbConfigFile)) {
                throw new Exception("Le fichier de configuration de la base de données est introuvable : " . $dbConfigFile);
            }
            
            $dbConfigData = file_get_contents($dbConfigFile);
            $dbConfigData = json_decode($dbConfigData, true);
            
            if ($dbConfigData === null) {
                throw new RuntimeException("Erreur lors de la lecture de la configuration de la base de données : " . json_last_error_msg());
            }
            
            // Connexion à la base de données de test
            ConnectionManager::setConfig($dbConfigData);
            ConnectionManager::setDefaultProfile('test');
            
            // Réinitialisation de la base de données
            $sql = file_get_contents(__DIR__ . '/config/test.sql');
            $pdo = ConnectionManager::get()->pdo();
            $pdo->exec($sql);
        }
        
        /**
         * Instanciation du manager avant chaque fonction.
         *
         * @return void
         */
        protected function setUp(): void
        {
            // Instanciation du Manager
            $this->manager = new UsersManager();
        }
        
        /**
         * Test de la fonciton tableName pour la récupération du nom de la table lié au manager.
         *
         * @covers \processid\manager\Manager::tableName
         *
         * @return void
         */
        public function testTableName(): void
        {
            $this->assertEquals('users_tests', $this->manager->tableName());
        }
        
        /**
         * Test de la fonction className pour la récupération du nom de la classe modèle lié au manager.
         *
         * @covers \processid\manager\Manager::className
         *
         * @return void
         */
        public function testClassName(): void
        {
            $this->assertEquals(Users::class, $this->manager->className());
        }
        
        /**
         * Test de la fonction findById pour la recherche d'un utilisateur par ID.
         *
         * @covers \processid\manager\Manager::findById
         *
         * @return void
         */
        public function testFindById(): void
        {
            // Recherche d'un utilisateur par ID
            $user = $this->manager->findById(1);
            
            // Vérification des données
            $this->assertInstanceOf(Users::class, $user);
            $this->assertEquals('Alice', $user->getName());
            $this->assertEquals('alice@example.com', $user->getEmail());
            $this->assertEquals('active', $user->getStatus());
        }
        
        /**
         * Test de la fonction fieldExists pour la vérification de l'existance d'un champ dans la table.
         *
         * @covers \processid\manager\Manager::fieldExists
         *
         * @return void
         */
        public function testFieldExists(): void
        {
            $this->assertTrue($this->manager->fieldExists('name'));
            $this->assertTrue($this->manager->fieldExists('email'));
            $this->assertFalse($this->manager->fieldExists('nonexistent_field'));
        }
        
        /**
         * Test de la fonction modelFieldsList pour la récupération de la liste des champs annoté Field du modèle.
         *
         * @covers \processid\manager\Manager::modelFieldsList
         *
         * @return void
         */
        public function testModelFieldsList(): void
        {
            $fields = $this->manager->modelFieldsList();
            
            $expectedFields = ['id', 'name', 'email', 'status', 'created_at'];
            
            $this->assertIsArray($fields);
            $this->assertEqualsCanonicalizing($expectedFields, array_keys($fields));
        }
        
        /**
         * Test de la fonction debugTxt pour la récupération de la dernière requête SQL exécutée.
         *
         * @covers \processid\manager\Manager::debugTxt
         *
         * @return void
         */
        public function testDebugTxt(): void
        {
            $this->manager->findById(1);
            $debugOutput = $this->manager->debugTxt();
            
            $this->assertIsString($debugOutput);
            $this->assertStringContainsString('FROM users_tests WHERE id ', $debugOutput);
        }
        
        /**
         *
         * Test de la fonction findByIds pour la recherche de plusieurs utilisateurs par ID.
         *
         * @covers \processid\manager\Manager::findByIds
         *
         * @return void
         */
        public function testFindByIds(): void
        {
            // IDs à rechercher
            $ids = [1, 3];
            
            // Appeler la méthode findByIds
            $results = $this->manager->findByIds($ids);
            
            // Vérifier que les résultats correspondent aux enregistrements attendus
            $this->assertCount(2, $results);
            
            $this->assertEquals('Alice', $results[0]->getName());
            $this->assertEquals('Charlie', $results[1]->getName());
        }
        
        /**
         * Test de la fonction countAll pour la récupération de tous les utilisateurs.
         *
         * @covers \processid\manager\Manager::countAll
         *
         * @return void
         */
        public function testCountAll(): void
        {
            // Appeler la méthode countAll
            $count = $this->manager->countAll();
            
            // Vérifier le total attendu (4 enregistrements dans la table users_tests)
            $this->assertEquals(4, $count, 'Le nombre total d\'enregistrements est incorrect.');
        }
        
        /**
         * Tests de la fonction findBy avec des conditions multiples.
         *
         * @param array $arg Arguments à passer à findBy.
         * @param int $count Nombre de lignes attendues.
         * @param string $name Nom dans la première ligne.
         *
         * @covers       \processid\manager\Manager::findBy
         * @dataProvider findByDataProvider
         *
         * @return void
         */
        public function testFindByWith(array $arg, int $count, string $name): void
        {
            $results = $this->manager->findBy($arg);
            
            $this->assertCount($count, $results);
            $this->assertEquals($name, $results[0]->getName());
        }
        
        /**
         * Création d'une ligne avec la méthode persist.
         *
         * @covers \processid\manager\Manager::persist
         *
         * @return void
         */
        public function testPersistCreateNew(): void
        {
            $data = [
                'name' => 'Eve',
                'email' => 'eve@example.com',
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $user = new Users($data);
            $this->manager->persist($user);
            
            $result = $this->manager->findById($user->getId());
            
            $this->assertNotNull($result, 'Le nouvel utilisateur n\'a pas été trouvé.');
            $this->assertEquals('Eve', $result->getName(), 'Le nom de l\'utilisateur est incorrect.');
            $this->assertEquals('eve@example.com', $result->getEmail(), 'L\'email de l\'utilisateur est incorrect.');
            $this->assertEquals('active', $result->getStatus(), 'Le statut de l\'utilisateur est incorrect.');
        }
        
        /**
         * Modification d'une ligne avec la méthode persist.
         *
         * @covers \processid\manager\Manager::persist
         *
         * @return void
         */
        public function testPersistUpdateExisting(): void
        {
            $user = $this->manager->findById(1); // Suppose que l'utilisateur avec ID 1 existe
            $this->assertNotNull($user, 'L\'utilisateur à mettre à jour n\'a pas été trouvé.');
            
            $user->setName('Alice Updated');
            $user->setEmail('alice.updated@example.com');
            $this->manager->persist($user);
            
            $updatedUser = $this->manager->findById(1);
            
            $this->assertEquals('Alice Updated', $updatedUser->getName(), 'Le nom de l\'utilisateur n\'a pas été mis à jour.');
            $this->assertEquals('alice.updated@example.com', $updatedUser->getEmail(), 'L\'email de l\'utilisateur n\'a pas été mis à jour.');
        }
        
        /**
         * Test de la fonction delete pour la suppression d'un utilisateur.
         *
         * @covers \processid\manager\Manager::delete
         *
         * @return void
         */
        public function testDeleteExisting(): void
        {
            $user = $this->manager->findById(2); // Suppose que l'utilisateur avec ID 2 existe
            $this->assertNotNull($user, 'L\'utilisateur à supprimer n\'a pas été trouvé.');
            
            $this->manager->delete($user->getId());
            
            $this->expectException(Exception::class);
            $this->manager->findById(2);
        }
        
        /**
         * Provider pour la fonction findBy
         *
         * @return array[]
         */
        public static function findByDataProvider(): array
        {
            return [
                'Single Condition' => [
                    [
                        "fields" => [
                            ["table" => "users_tests", "field" => "id"],
                            ["table" => "users_tests", "field" => "name"]
                        ],
                        "search" => [
                            ["table" => "users_tests", "field" => "id", "value" => 1, "operator" => "="]
                        ],
                        "start" => 0,
                        "limit" => 10
                    ],
                    1,
                    'Alice'
                ],
                'Multiple Conditions' => [
                    [
                        "fields" => [
                            ["table" => "users_tests", "field" => "id"],
                            ["table" => "users_tests", "field" => "name"]
                        ],
                        "search" => [
                            ["table" => "users_tests", "field" => "status", "value" => "active", "operator" => "="],
                            ["table" => "users_tests", "field" => "email", "value" => "@example.com", "operator" => QueryOperator::LIKE_BOTH->value]
                        ],
                        "sort" => [
                            ["table" => "users_tests", "field" => "id", "reverse" => true]
                        ],
                        "start" => 0,
                        "limit" => 10
                    ],
                    2,
                    'Diana'
                ],
                'Limit and Pagination' => [
                    [
                        "fields" => [
                            ["table" => "users_tests", "field" => "id"],
                            ["table" => "users_tests", "field" => "name"]
                        ],
                        "search" => [
                            ["table" => "users_tests", "field" => "status", "value" => "active", "operator" => "="]
                        ],
                        "start" => 1,
                        "limit" => 1
                    ],
                    1,
                    'Diana'
                ],
                'Test With Complex Condition' => [
                    [
                        "fields" => [
                            ["table" => "users_tests", "field" => "id"],
                            ["table" => "users_tests", "field" => "name"]
                        ],
                        "search" => [
                            ["table" => "users_tests", "field" => "status", "value" => "inactive", "operator" => "="],
                            ["table" => "users_tests", "field" => "name", "value" => "Charlie", "operator" => "="],
                            ["table" => "users_tests", "field" => "status", "value" => "pending", "operator" => "="]
                        ],
                        "sequence" => 'WHERE1 OR (WHERE2 AND WHERE3)'
                    ],
                    2,
                    'Bob'
                ],
                'Join' => [
                    [
                        "fields" => [
                            ["table" => "users_tests", "field" => "id"],
                            ["table" => "users_tests", "field" => "name"]
                        ],
                        "beforeWhere" => "INNER JOIN orders_tests ON orders_tests.user_id = users_tests.id",
                        "afterWhere" => " AND orders_tests.status = 'unpaid'",
                        "start" => 0,
                        "limit" => 10
                    ],
                    1,
                    'Bob'
                ]
            ];
        }
        
        /**
         * Reset de la db
         *
         * @return void
         */
        public static function tearDownAfterClass(): void
        {
            self::setUpBeforeClass();
        }
    }
