-- Migration 003: Support multi-day events
-- Permet aux feuilles d'émargement de couvrir plusieurs jours
-- Les participants peuvent signer pour chaque jour de l'événement

-- Ajout de la date de fin pour les événements multi-jours
-- Si end_date = event_date ou NULL, c'est un événement d'une journée
ALTER TABLE sheets ADD COLUMN IF NOT EXISTS end_date DATE;

-- Copier event_date vers end_date pour les feuilles existantes
UPDATE sheets SET end_date = event_date WHERE end_date IS NULL;

-- Ajout du jour signé dans les signatures
-- Pour les signatures existantes, on utilise la date de l'événement
ALTER TABLE signatures ADD COLUMN IF NOT EXISTS signed_for_date DATE;

-- Mettre à jour les signatures existantes avec la date de l'événement
UPDATE signatures sig
SET signed_for_date = (SELECT event_date FROM sheets WHERE id = sig.sheet_id)
WHERE signed_for_date IS NULL;

-- Index pour améliorer les requêtes par date
CREATE INDEX IF NOT EXISTS idx_signatures_signed_for_date ON signatures(signed_for_date);

-- Commentaires
COMMENT ON COLUMN sheets.end_date IS 'Date de fin pour les événements multi-jours. Si égal à event_date, événement d''une journée.';
COMMENT ON COLUMN signatures.signed_for_date IS 'Date pour laquelle le participant a signé (utile pour les événements multi-jours).';
