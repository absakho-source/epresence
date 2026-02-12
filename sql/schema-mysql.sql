-- =====================================================
-- e-Présence - Schéma de Base de Données MySQL
-- =====================================================

-- Suppression des tables existantes (dans l'ordre des dépendances)
DROP TABLE IF EXISTS signatures;
DROP TABLE IF EXISTS sheets;
DROP TABLE IF EXISTS users;

-- =====================================================
-- Table des utilisateurs (organisateurs)
-- =====================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    structure VARCHAR(255),
    role ENUM('user', 'admin') DEFAULT 'user',
    status ENUM('active', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index pour recherche par email
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_status ON users(status);

-- =====================================================
-- Table des feuilles d'émargement
-- =====================================================
CREATE TABLE sheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    event_time TIME,
    location VARCHAR(255),
    unique_code VARCHAR(32) UNIQUE NOT NULL,
    status ENUM('active', 'closed', 'archived') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index pour recherche par code unique et user_id
CREATE INDEX idx_sheets_unique_code ON sheets(unique_code);
CREATE INDEX idx_sheets_user_id ON sheets(user_id);
CREATE INDEX idx_sheets_status ON sheets(status);

-- =====================================================
-- Table des signatures (émargements)
-- Ordre des champs: Prénom, Nom, Structure, Fonction, Téléphone, Email, Signature
-- =====================================================
CREATE TABLE signatures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sheet_id INT NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    structure VARCHAR(255),
    function_title VARCHAR(255),
    phone VARCHAR(20),
    phone_secondary VARCHAR(20),
    email VARCHAR(255) NOT NULL,
    signature_data MEDIUMTEXT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    signed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sheet_id) REFERENCES sheets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index pour recherche par sheet_id
CREATE INDEX idx_signatures_sheet_id ON signatures(sheet_id);
CREATE INDEX idx_signatures_email ON signatures(email);

-- =====================================================
-- Créer le premier administrateur (à modifier après création)
-- Email: admin@economie.gouv.sn
-- Mot de passe: Admin@2025 (hashé avec bcrypt)
-- =====================================================
-- INSERT INTO users (email, password, first_name, last_name, structure, role)
-- VALUES ('admin@economie.gouv.sn', '$2y$12$...hash...', 'Admin', 'DGPPE', 'DGPPE', 'admin');
