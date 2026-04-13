<?php
    
    namespace include\common\lib\vendor\processid\manager\unit;
    
    // Require des fichiers requis
    require_once __DIR__ . '/TestUnit.php';
    
    use Closure;
    use Exception;
    use LogicException;
    use processid\manager\ConnectionManager;
    use processid\manager\enum\QueryOperator;
    use processid\manager\enum\QuerySequenceType;
    use processid\manager\QueryBuilder;
    use processid\manager\unit\src\UsersManager;
    use processid\manager\unit\TestUnit;
    use RuntimeException;
    
    class QueryBuilderTest extends TestUnit
    {
        /**
         * Initialisation de la base de données
         *
         * @return void
         */
        public static function setUpBeforeClass(): void
        {
            echo 'Initialisation de la base de données ===========================================' . PHP_EOL;
            // Connexion à la base de données de test
            self::__initQueryBuilder();
            
            // Réinitialisation de la base de données
            $sql = file_get_contents(__DIR__ . '/config/test.sql');
            $pdo = ConnectionManager::get()->pdo();
            $pdo->exec($sql);
        }
        
        /**
         * Test des fonctions build/run du QueryBuilder.
         *
         * @param array $results Résultat de la fonction run.
         * @param int $count Nombre de lignes.
         * @param string $name Nom de la première ligne.
         *
         * @covers \processid\manager\QueryBuilder::build
         * @covers \processid\manager\QueryBuilder::run
         * @dataProvider runDataProvider
         *
         * @return void
         */
        public function testRun(Closure $fct, int $count, string $name): void
        {
            $results = $fct();
            
            $this->assertCount($count, $results);
            $this->assertEquals($name, $results[0]->getName());
        }
        
        /**
         * Test de la fonction build avec une parenthèse non fermé dans les conditions.
         *
         * @covers \processid\manager\QueryBuilder::build
         *
         * @return void
         */
        public function testBuildWithNotClosedConditionException(): void
        {
            $this->expectException(LogicException::class);
            (new QueryBuilder(new UsersManager()))
                ->field('id')
                ->field('name')
                ->search('status', 'inactive')
                ->openGroup(QuerySequenceType::OR)
                ->andSearch('name', 'Charlie')
                ->andSearch('status', 'pending')
                ->sort('id')
                ->build();
        }
        
        /**
         * Provider pour la fonction Run et Build.
         *
         * @return array[]
         */
        public static function runDataProvider(): array
        {
            self::__initQueryBuilder();
            
            return [
                'Single Condition' => [
                    fn() => ((new QueryBuilder(new UsersManager()))
                        ->field('id')
                        ->field('name')
                        ->search('id', 1)
                        ->limit(10)
                        ->run()),
                    1,
                    'Alice'
                ],
                'Multiple Conditions' => [
                    fn() => ((new QueryBuilder(new UsersManager()))
                        ->field('id')
                        ->field('name')
                        ->search('status', 'active')
                        ->search('email', '@example.com', QueryOperator::LIKE_BOTH)
                        ->sort('id', true)
                        ->limit(10)
                        ->run()),
                    2,
                    'Diana'
                ],
                'Limit and Pagination' => [
                    fn() => ((new QueryBuilder(new UsersManager()))
                        ->field('id')
                        ->field('name')
                        ->search('status', 'active')
                        ->limit(1)
                        ->sort('id', true)
                        ->run()),
                    1,
                    'Diana'
                ],
                'Test With Complex Condition' => [
                    // SELECT id, name FROM users WHERE status = 'inactive' OR (name = 'Charlie' OR status = 'pending') ORDER BY id ASC
                    fn() => (new QueryBuilder(new UsersManager()))
                        ->field('id')
                        ->field('name')
                        ->search('status', 'inactive')
                        ->openGroup(QuerySequenceType::OR)
                        ->andSearch('name', 'Charlie')
                        ->andSearch('status', 'pending')
                        ->closeGroup()
                        ->sort('id')
                        ->run(),
                    2,
                    'Bob'
                ]
            ];
        }
        
        /**
         * Initialisation du queryBuilder
         *
         * @return void
         */
        private static function __initQueryBuilder(): void
        {
            // Connexion à la base de données de test
            $dbConfigFile = __DIR__ . '/config/DbConfiguration.json';
            
            if (!file_exists($dbConfigFile)) {
                throw new Exception("Le fichier de configuration de la base de données est introuvable : " . $dbConfigFile);
            }
            
            $dbConfigData = file_get_contents($dbConfigFile);
            $dbConfigData = json_decode($dbConfigData, true);
            
            if ($dbConfigData === null) {
                throw new RuntimeException("Erreur lors de la lecture de la configuration de la base de données : " . json_last_error_msg());
            }
            
            ConnectionManager::setConfig($dbConfigData);
            ConnectionManager::setDefaultProfile('test');
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
