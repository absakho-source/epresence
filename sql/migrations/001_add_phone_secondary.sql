-- Migration: Ajouter la colonne phone_secondary à la table signatures
-- Date: 2026-01-23

-- Ajouter la colonne phone_secondary si elle n'existe pas
ALTER TABLE signatures ADD COLUMN IF NOT EXISTS phone_secondary VARCHAR(20);

-- Vérification
SELECT column_name, data_type
FROM information_schema.columns
WHERE table_name = 'signatures'
AND column_name = 'phone_secondary';
