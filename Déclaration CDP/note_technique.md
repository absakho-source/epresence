# NOTE TECHNIQUE DU LOGICIEL

## e-Présence - Plateforme d'Émargement Électronique

---

### INFORMATIONS GÉNÉRALES

| Élément | Description |
|---------|-------------|
| **Nom du logiciel** | e-Présence |
| **Sous-titre** | Plateforme d'Émargement Électronique |
| **Version** | 1.0 |
| **Date de création** | Février 2026 |
| **Auteur** | Dr. Aboubekrine SAKHO |
| **Type** | Application Web |

---

### 1. DESCRIPTION GÉNÉRALE

**e-Présence** est une plateforme web innovante d'émargement électronique conçue pour digitaliser et moderniser la gestion des présences lors de réunions, ateliers, formations et événements professionnels au sein du Ministère de l'Économie, du Plan et de la Coopération (MEPC).

Le logiciel permet de remplacer les traditionnelles feuilles de présence papier par un système numérique sécurisé, accessible via QR code ou lien web, permettant aux participants de signer électroniquement depuis leur téléphone mobile ou ordinateur.

---

### 2. FONCTIONNALITÉS PRINCIPALES

#### 2.1 Pour les Organisateurs

- **Gestion de compte sécurisée** : Inscription et authentification avec mot de passe chiffré
- **Création de feuilles d'émargement** : Interface intuitive pour créer des événements avec titre, date, heure, lieu et description
- **Génération automatique de QR Code** : Chaque feuille génère un QR code unique scannable
- **Lien partageable** : URL unique pour partage par email, messagerie ou affichage
- **Tableau de bord en temps réel** : Visualisation des signatures au fur et à mesure
- **Export multi-format** : Téléchargement en PDF (format officiel), Excel et JSON
- **Impression optimisée** : Document A4 paysage prêt à imprimer avec toutes les signatures
- **Historique complet** : Archivage et consultation de toutes les feuilles créées

#### 2.2 Pour les Participants

- **Accès simplifié** : Scan du QR code ou clic sur le lien partagé
- **Formulaire responsive** : Interface adaptée mobile et desktop
- **Informations collectées** : Nom, prénom, email, téléphone(s), fonction, structure
- **Signature électronique tactile** : Zone de signature avec le doigt (mobile) ou souris (PC)
- **Confirmation immédiate** : Message de validation après signature

#### 2.3 Pour les Administrateurs

- **Gestion multi-structures** : Organisation hiérarchique selon l'organigramme du MEPC (Cabinet, Directions Générales, Directions techniques)
- **Trois niveaux de droits** :
  - *Utilisateur simple* : Gère ses propres feuilles d'émargement
  - *Responsable de structure* : Voit et supervise les feuilles de sa Direction/Structure
  - *Administrateur global* : Gestion des utilisateurs, validation des inscriptions, statistiques globales
- **Validation des inscriptions** : Processus de validation avant accès à la plateforme
- **Supervision transversale** : Visibilité sur les feuilles selon la hiérarchie organisationnelle
- **Statistiques détaillées** : Vue d'ensemble par structure et catégorie

---

### 3. ARCHITECTURE TECHNIQUE

#### 3.1 Technologies Utilisées

| Composant | Technologie | Version |
|-----------|-------------|---------|
| **Langage serveur** | PHP | 8.x |
| **Base de données** | PostgreSQL | 14+ |
| **Frontend** | HTML5, CSS3, JavaScript ES6 | - |
| **Framework CSS** | Bootstrap | 5.3 |
| **Signature électronique** | signature_pad.js | 4.1.7 |
| **Génération QR Code** | qrcode.js | 1.0.0 |

#### 3.2 Structure des Fichiers

```
e-presence/
├── index.php                 # Point d'entrée
├── config/                   # Configuration
│   ├── database.php          # Connexion BDD
│   ├── config.php            # Paramètres généraux
│   └── structures.php        # Hiérarchie organisationnelle
├── assets/                   # Ressources statiques
│   ├── css/                  # Feuilles de style
│   ├── js/                   # Scripts JavaScript
│   └── img/                  # Images et logos
├── includes/                 # Composants PHP réutilisables
│   ├── header.php            # En-tête HTML
│   ├── footer.php            # Pied de page
│   ├── auth.php              # Authentification
│   └── functions.php         # Fonctions utilitaires
├── pages/                    # Pages de l'application
│   ├── auth/                 # Authentification
│   ├── dashboard/            # Tableau de bord
│   ├── sign/                 # Signature publique
│   ├── export/               # Exports PDF/Excel/JSON
│   └── admin/                # Administration
└── api/                      # Points d'entrée API
    ├── signature.php         # API signatures
    └── sheet.php             # API feuilles
```

#### 3.3 Modèle de Données

**Table `users`** - Utilisateurs du système
- Identifiant unique, email, mot de passe chiffré, nom, prénom
- Structure d'appartenance, fonction
- Rôle (utilisateur, administrateur), statut responsable de structure
- Horodatage création/modification, validation

**Table `sheets`** - Feuilles d'émargement
- Identifiant unique, code unique (UUID)
- Titre, description, date/heure de l'événement, heure de fin
- Lieu, statut (active/clôturée/archivée)
- Référence optionnelle au créateur (user_id, peut être NULL)
- **Nom et structure du créateur stockés directement** (creator_name, creator_structure)
- **Les feuilles restent rattachées à leur structure d'origine** même si l'utilisateur quitte ou est supprimé

**Table `signatures`** - Émargements des participants
- Identifiant unique, référence à la feuille
- Informations du signataire (nom, prénom, email, téléphone(s), fonction, structure)
- Données de signature (image encodée base64)
- Métadonnées (adresse IP, user agent, horodatage)

**Table `sheet_documents`** - Documents joints aux feuilles
- Identifiant unique, référence à la feuille
- Nom original, nom stocké, type MIME, taille
- Type de document (agenda, TDR, rapport, autre)

---

### 4. SÉCURITÉ

#### 4.1 Mesures Implémentées

- **Authentification sécurisée** : Mots de passe hashés avec bcrypt
- **Protection CSRF** : Jetons de sécurité sur tous les formulaires
- **Injection SQL** : Requêtes préparées (PDO) exclusivement
- **XSS** : Échappement systématique des données affichées
- **Sessions sécurisées** : Gestion PHP native avec régénération d'ID
- **Validation des entrées** : Contrôle côté serveur de toutes les données

#### 4.2 Confidentialité des Données

- Données stockées localement (pas de cloud externe)
- Accès restreint selon les droits utilisateur
- Signatures électroniques non réutilisables (liées à un événement unique)

---

### 5. ORIGINALITÉ ET INNOVATION

#### 5.1 Points Différenciants

1. **Solution 100% web** : Aucune installation requise côté utilisateur
2. **Mobile-first** : Conception prioritaire pour smartphones
3. **QR Code natif** : Génération et lecture sans application tierce
4. **Signature tactile** : Vraie signature manuscrite numérisée
5. **Multi-export** : PDF officiel, Excel pour analyse, JSON pour intégration
6. **Architecture modulaire** : Facilement adaptable à différentes organisations

#### 5.2 Cas d'Usage

- Réunions de travail et comités
- Formations et ateliers
- Événements institutionnels
- Assemblées générales
- Visites de terrain

---

### 6. PROPRIÉTÉ INTELLECTUELLE ET LICENCE D'UTILISATION

#### 6.1 Droits d'Auteur

Ce logiciel est une création originale de **Dr. Aboubekrine SAKHO**. Tous les droits de propriété intellectuelle sont réservés, incluant mais non limités à :

- Le code source dans son intégralité
- L'architecture logicielle
- L'interface utilisateur et le design
- Les algorithmes et processus métier
- La documentation associée

#### 6.2 Licence d'utilisation

Le logiciel e-Présence est mis à disposition du **Ministère de l'Économie, du Plan et de la Coopération (MEPC)** dans le cadre d'une licence d'utilisation non exclusive.

- **Propriété intellectuelle** : Reste entièrement détenue par Dr. Aboubekrine SAKHO
- **Droits accordés au MEPC** : Utilisation du logiciel pour ses activités internes
- **Restrictions** : Toute reproduction, modification, distribution ou utilisation commerciale sans autorisation écrite préalable de l'auteur est strictement interdite

---

### 7. ÉVOLUTIONS PRÉVUES

- Intégration de notifications par email/SMS
- Tableau de bord statistique avancé
- API REST pour intégrations tierces
- Application mobile native (iOS/Android)
- Signature avec certificat électronique qualifié

---

**Document établi le** : <?= date('d/m/Y') ?>

**Auteur** : Dr. Aboubekrine SAKHO

**Signature** : _______________________
