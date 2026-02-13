-- Migration 004: Ajout du nombre de participants attendus
-- Permet de calculer le taux de présence (signatures / attendus)

-- Ajout de la colonne expected_participants
ALTER TABLE sheets ADD COLUMN IF NOT EXISTS expected_participants INTEGER DEFAULT NULL;

-- Commentaire
COMMENT ON COLUMN sheets.expected_participants IS 'Nombre de participants attendus pour calculer le taux de présence. NULL si non défini.';
