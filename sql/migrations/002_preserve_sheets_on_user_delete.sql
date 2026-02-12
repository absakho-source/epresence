-- =====================================================
-- Migration: Conserver les feuilles lors de la suppression d'utilisateur
-- Date: 2026-02-12
-- =====================================================

-- 1. Ajouter des colonnes pour stocker les infos du créateur directement dans la feuille
-- (Permet de conserver ces infos même si l'utilisateur est supprimé)
ALTER TABLE sheets ADD COLUMN IF NOT EXISTS creator_name VARCHAR(255);
ALTER TABLE sheets ADD COLUMN IF NOT EXISTS creator_structure VARCHAR(255);

-- 2. Remplir ces colonnes avec les données existantes
UPDATE sheets s
SET
    creator_name = CONCAT(u.first_name, ' ', u.last_name),
    creator_structure = u.structure
FROM users u
WHERE s.user_id = u.id
AND s.creator_name IS NULL;

-- 3. Modifier la contrainte de clé étrangère pour SET NULL au lieu de CASCADE
-- D'abord, supprimer l'ancienne contrainte
ALTER TABLE sheets DROP CONSTRAINT IF EXISTS sheets_user_id_fkey;

-- 4. Permettre NULL pour user_id
ALTER TABLE sheets ALTER COLUMN user_id DROP NOT NULL;

-- 5. Recréer la contrainte avec SET NULL
ALTER TABLE sheets
ADD CONSTRAINT sheets_user_id_fkey
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- 6. Créer un index sur creator_structure pour les recherches
CREATE INDEX IF NOT EXISTS idx_sheets_creator_structure ON sheets(creator_structure);
