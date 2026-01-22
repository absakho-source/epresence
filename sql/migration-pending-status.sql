-- =====================================================
-- Migration: Ajout du statut 'pending' pour validation admin
-- e-Presence - DGPPE
-- =====================================================

-- 1. Modifier la contrainte de statut pour inclure 'pending'
ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check;
ALTER TABLE users ADD CONSTRAINT users_status_check
    CHECK (status IN ('pending', 'active', 'suspended'));

-- 2. Ajouter les colonnes pour le suivi de validation
ALTER TABLE users ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP;
ALTER TABLE users ADD COLUMN IF NOT EXISTS approved_by INT;

-- 3. Mettre le statut par défaut à 'pending' pour les nouveaux utilisateurs
ALTER TABLE users ALTER COLUMN status SET DEFAULT 'pending';

-- 4. Les utilisateurs existants restent actifs (ne pas les passer en pending)
-- UPDATE users SET status = 'active' WHERE status IS NULL;

-- 5. Index pour les requêtes sur les utilisateurs en attente
CREATE INDEX IF NOT EXISTS idx_users_pending ON users(status) WHERE status = 'pending';
