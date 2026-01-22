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
    structure VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Index pour recherche par email
CREATE INDEX idx_users_email ON users(email);

-- =====================================================
-- Table des feuilles d'émargement
-- =====================================================
CREATE TABLE sheets (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    event_time TIME,
    location VARCHAR(255),
    unique_code VARCHAR(32) UNIQUE NOT NULL,
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'closed', 'archived')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Index pour recherche par code unique et user_id
CREATE INDEX idx_sheets_unique_code ON sheets(unique_code);
CREATE INDEX idx_sheets_user_id ON sheets(user_id);
CREATE INDEX idx_sheets_status ON sheets(status);

-- =====================================================
-- Table des signatures (émargements)
-- Ordre des champs: Prénom, Nom, Structure, Fonction, Téléphone, Email, Signature
-- =====================================================
CREATE TABLE signatures (
    id SERIAL PRIMARY KEY,
    sheet_id INTEGER REFERENCES sheets(id) ON DELETE CASCADE,
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
    signed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Index pour recherche par sheet_id
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

-- Trigger pour sheets
CREATE TRIGGER update_sheets_updated_at
    BEFORE UPDATE ON sheets
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- =====================================================
-- Table des tokens de réinitialisation de mot de passe
-- =====================================================
CREATE TABLE IF NOT EXISTS password_resets (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    token VARCHAR(64) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Index pour recherche par token
CREATE INDEX IF NOT EXISTS idx_password_resets_token ON password_resets(token);
CREATE INDEX IF NOT EXISTS idx_password_resets_user_id ON password_resets(user_id);
