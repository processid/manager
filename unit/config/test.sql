-- Suppression des tables en premier (permet de régler les problèmes de dépendance sur les foreigns keys)
DROP TABLE IF EXISTS orders_tests;
DROP TABLE IF EXISTS users_tests;

-- création de la table 'users_tests' pour les tests
CREATE TABLE users_tests
(
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255)        NOT NULL,
    email      VARCHAR(255) UNIQUE NOT NULL,
    status     ENUM ('active', 'inactive', 'pending') DEFAULT 'pending',
    created_at TIMESTAMP                              DEFAULT CURRENT_TIMESTAMP
);

-- création de la table 'orders' pour les tests
CREATE TABLE orders_tests
(
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT            NOT NULL,
    total      DECIMAL(10, 2) NOT NULL,
    status     ENUM ('paid', 'unpaid', 'cancelled') DEFAULT 'unpaid',
    created_at TIMESTAMP                            DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users_tests (id) ON DELETE CASCADE
);

-- Insertion de données dans la table 'users_tests'
INSERT INTO users_tests (name, email, status)
VALUES ('Alice', 'alice@example.com', 'active'),
       ('Bob', 'bob@example.com', 'inactive'),
       ('Charlie', 'charlie@example.com', 'pending'),
       ('Diana', 'diana@example.com', 'active');

-- Insertion de données dans la table 'orders'
INSERT INTO orders_tests (user_id, total, status)
VALUES (1, 100.00, 'paid'),
       (2, 50.00, 'unpaid'),
       (1, 200.00, 'paid'),
       (3, 75.50, 'cancelled');