<?php
    
    namespace processid\manager\unit;
    
    // Require des fichiers requis
    require_once __DIR__ . '/TestUnit.php';
    
    use processid\manager\exception\ModelMethodNotFoundException;
    use processid\manager\unit\src\Users;
    
    class ModelTest extends TestUnit
    {
        /**
         * Test de création des getters et setters depuis les attributs de classe annotées Field.
         *
         * @covers \processid\manager\Model::createSettersGetters
         * @covers \processid\manager\Model::hydrate
         *
         * @return void
         */
        public function testCreateSettersGetters(): void
        {
            $data = [
                'id' => 1,
                'name' => 'John Doe',
                'email' => 'john.doe@example.com',
                'status' => 'active',
                'created_at' => time()
            ];
            
            // Création de l'objet avec hydratation
            $user = new Users($data);
            
            // Test des getters générés dynamiquement
            $this->assertEquals(1, $user->getId());
            $this->assertEquals('John Doe', $user->getName());
            $this->assertEquals('John Doe', $user->name());
            $this->assertEquals($data['created_at'], $user->created_at());
            
            // Test des setters générés dynamiquement
            $user->setName('Jane Doe')->setEmail('jane.doe@example.com')->setCreated_at(time());
            $this->assertEquals('Jane Doe', $user->getName());
            $this->assertEquals('jane.doe@example.com', $user->getEmail());
        }
        
        /**
         * Test d'hydratation avec un clé sans getter et setters.
         *
         * @covers \processid\manager\Model::createSettersGetters
         * @covers \processid\manager\Model::hydrate
         *
         * @return void
         */
        public function testGetterNotFoundException(): void
        {
            $this->expectException(ModelMethodNotFoundException::class);
            
            // Création de l'objet avec hydratation
            $user = new Users();
            $user->setPassword('123456789');
        }
        
        /**
         * Test de la fonction asArray du Model.
         *
         * @covers \processid\manager\Model::asArray
         *
         * @return void
         */
        public function testAsArray(): void
        {
            $data = [
                'id' => 2,
                'name' => 'Alice',
                'email' => 'alice@example.com',
                'status' => 'inactive',
                'created_at' => time()
            ];
            
            // Création de l'objet avec hydratation
            $user = new Users($data);
            
            // Conversion en tableau
            $arrayData = $user->asArray();
            
            // Vérification que les données retournées correspondent aux données initiales
            $this->assertArrayHasKey('id', $arrayData);
            $this->assertArrayHasKey('name', $arrayData);
            $this->assertArrayHasKey('email', $arrayData);
            $this->assertArrayHasKey('status', $arrayData);
            $this->assertArrayHasKey('created_at', $arrayData);
            
            $this->assertEquals($data['id'], $arrayData['id']);
            $this->assertEquals($data['name'], $arrayData['name']);
            $this->assertEquals($data['email'], $arrayData['email']);
            $this->assertEquals($data['status'], $arrayData['status']);
            $this->assertEquals($data['created_at'], $arrayData['created_at']);
        }
    }
