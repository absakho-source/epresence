-- Migration: Ajouter la table pour les documents attachés aux feuilles
-- Date: 2026-01-22
-- Description: Permet d'attacher des documents (agenda, TDR, rapports) aux feuilles d'émargement

CREATE TABLE IF NOT EXISTS sheet_documents (
    id SERIAL PRIMARY KEY,
    sheet_id INTEGER REFERENCES sheets(id) ON DELETE CASCADE,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size INTEGER NOT NULL,
    document_type VARCHAR(50) DEFAULT 'other',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Index pour accélérer les requêtes par sheet_id
CREATE INDEX IF NOT EXISTS idx_sheet_documents_sheet_id ON sheet_documents(sheet_id);

-- Types de documents possibles:
-- 'agenda' : Ordre du jour / Agenda de la réunion
-- 'tdr' : Termes de référence
-- 'report' : Rapport / Compte-rendu
-- 'other' : Autre document
