-- Script pour créer un utilisateur administrateur
-- Mot de passe: admin123 (hashé avec bcrypt)
-- IMPORTANT: Changez ce mot de passe en production !

INSERT INTO users (email, password, first_name, last_name, structure)
VALUES (
    'admin@dgppe.gouv.sn',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Admin',
    'DGPPE',
    'DGPPE'
);

-- Le hash ci-dessus correspond au mot de passe: password
-- Pour générer un nouveau hash en PHP: echo password_hash('votre_mot_de_passe', PASSWORD_DEFAULT);
