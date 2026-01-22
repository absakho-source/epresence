-- =====================================================
-- e-Présence - Schéma de Base de Données PostgreSQL
-- =====================================================

-- Suppression des tables existantes (dans l'ordre des dépendances)
DROP TABLE IF EXISTS signatures CASCADE;
DROP TABLE IF EXISTS sheets CASCADE;
DROP TABLE IF EXISTS users CASCADE;

-- =====================================================
-- Table des utilisateurs (organisateurs)
-- =====================================================
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    function_title VARCHAR(255),
    structure VARCHAR(255),
    is_structure_admin BOOLEAN DEFAULT FALSE,
    role VARCHAR(20) DEFAULT 'user' CHECK (role IN ('user', 'admin')),
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'active', 'suspended')),
    approved_at TIMESTAMP,
    approved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Index pour recherche
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_status ON users(status);

-- =====================================================
-- Table des feuilles d'émargement
-- =====================================================
CREATE TABLE sheets (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    event_time TIME,
    end_time TIME,
    auto_close BOOLEAN DEFAULT FALSE,
    location VARCHAR(255),
    unique_code VARCHAR(32) UNIQUE NOT NULL,
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'closed', 'archived')),
    closed_at TIMESTAMP,
    closed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Index pour recherche
CREATE INDEX idx_sheets_unique_code ON sheets(unique_code);
CREATE INDEX idx_sheets_user_id ON sheets(user_id);
CREATE INDEX idx_sheets_status ON sheets(status);

-- =====================================================
-- Table des signatures (émargements)
-- =====================================================
CREATE TABLE signatures (
    id SERIAL PRIMARY KEY,
    sheet_id INT NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    structure VARCHAR(255),
    function_title VARCHAR(255),
    phone VARCHAR(20),
    phone_secondary VARCHAR(20),
    email VARCHAR(255) NOT NULL,
    signature_data TEXT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    signed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sheet_id) REFERENCES sheets(id) ON DELETE CASCADE
);

-- Index pour recherche
CREATE INDEX idx_signatures_sheet_id ON signatures(sheet_id);
CREATE INDEX idx_signatures_email ON signatures(email);

-- =====================================================
-- Fonction pour mettre à jour updated_at automatiquement
-- =====================================================
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Triggers pour updated_at
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_sheets_updated_at BEFORE UPDATE ON sheets
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
