-- Migration 005: Support pour l'archivage automatique
-- Ajout de champs pour tracer la date et raison d'archivage

-- Ajout de la colonne archived_at
ALTER TABLE sheets ADD COLUMN IF NOT EXISTS archived_at TIMESTAMP DEFAULT NULL;

-- Ajout de la colonne archived_reason
ALTER TABLE sheets ADD COLUMN IF NOT EXISTS archived_reason VARCHAR(20) DEFAULT NULL;

-- Commentaires
COMMENT ON COLUMN sheets.archived_at IS 'Date d''archivage de la feuille';
COMMENT ON COLUMN sheets.archived_reason IS 'Raison de l''archivage: auto (automatique > 1 an), manual (manuel)';
