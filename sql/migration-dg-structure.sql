-- Migration: Unifier les codes de structure Direction générale
-- Date: 2026-01-22
-- Description: Remplace tous les anciens codes DG (DG-COORD, DG-CT1, DG-CT2, DG-CT3) par le code unique "DG"

-- Mettre à jour les utilisateurs avec les anciens codes
UPDATE users
SET structure = 'DG'
WHERE structure IN ('DG-COORD', 'DG-CT1', 'DG-CT2', 'DG-CT3');

-- Vérification (à exécuter séparément pour voir les résultats)
-- SELECT id, first_name, last_name, structure FROM users WHERE structure = 'DG';
