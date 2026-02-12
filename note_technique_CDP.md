# NOTE TECHNIQUE DU SYSTÈME E-PRÉSENCE
## Annexe à la déclaration CDP

---

**RÉPUBLIQUE DU SÉNÉGAL**
Un Peuple – Un But – Une Foi

**DIRECTION GÉNÉRALE DE LA PLANIFICATION ET DES POLITIQUES ÉCONOMIQUES (DGPPE)**

---

## 1. PRÉSENTATION GÉNÉRALE DU SYSTÈME

### 1.1 Description fonctionnelle

Le système **e-Présence** est une application web responsive permettant la gestion dématérialisée des feuilles d'émargement pour les réunions, ateliers, formations et événements organisés par la DGPPE.

**Architecture :** Application web client-serveur
**Mode de déploiement :** Hébergement cloud sécurisé
**Accès :** Via navigateur web (ordinateur, tablette, smartphone)

### 1.2 Fonctionnalités principales

#### Pour les organisateurs (agents DGPPE) :
- Création de compte (sous validation administrative)
- Création de feuilles d'émargement
- Génération automatique de QR code unique par feuille
- Suivi en temps réel des signatures
- Export des données (PDF, Excel, JSON)
- Gestion du cycle de vie des feuilles (active, clôturée, archivée)

#### Pour les participants :
- Accès via QR code ou lien direct (sans compte)
- Formulaire d'émargement simplifié
- Signature électronique tactile
- Confirmation immédiate d'enregistrement

## 2. ARCHITECTURE TECHNIQUE

### 2.1 Technologies utilisées

| Composant | Technologie | Version |
|-----------|-------------|---------|
| Langage backend | PHP | 8.x |
| Base de données | PostgreSQL | 14+ |
| Frontend | HTML5, CSS3, JavaScript | Standards W3C |
| Framework CSS | Bootstrap | 5.3 |
| Signature électronique | Canvas HTML5 + signature_pad.js | 4.x |
| QR Code | qrcode.js | 1.x |
| Génération PDF | TCPDF | 6.x |
| Serveur web | Apache/Nginx | - |
| Certificat SSL | Let's Encrypt ou commercial | TLS 1.3 |

### 2.2 Structure de la base de données

#### Table `users` (Comptes organisateurs)
```sql
- id : Identifiant unique
- email : Adresse email (identifiant de connexion)
- password : Mot de passe chiffré (bcrypt, coût 12)
- first_name : Prénom
- last_name : Nom
- structure : Structure d'appartenance
- function_title : Fonction
- role : Rôle (user, admin)
- is_structure_admin : Statut super-utilisateur (booléen)
- status : Statut du compte (pending, active, suspended)
- created_at : Date de création
- last_login : Dernière connexion
```

#### Table `sheets` (Feuilles d'émargement)
```sql
- id : Identifiant unique
- user_id : Créateur de la feuille
- title : Titre de la réunion/événement
- description : Description
- event_date : Date de l'événement
- event_time : Heure de l'événement
- location : Lieu
- unique_code : Code unique (32 caractères)
- status : Statut (active, closed, archived)
- created_at : Date de création
- closed_at : Date de clôture
- closed_by : Utilisateur ayant clôturé
```

#### Table `signatures` (Émargements)
```sql
- id : Identifiant unique
- sheet_id : Référence à la feuille
- first_name : Prénom du participant
- last_name : Nom du participant
- email : Email du participant
- phone : Téléphone principal
- phone_secondary : Téléphone secondaire
- function_title : Fonction
- structure : Structure d'appartenance
- signature_data : Image de la signature (base64)
- ip_address : Adresse IP (IPv4/IPv6)
- user_agent : Navigateur utilisé
- signed_at : Date et heure de signature
```

## 3. MESURES DE SÉCURITÉ DÉTAILLÉES

### 3.1 Sécurité des données

#### Chiffrement
- **Mots de passe :** Algorithme bcrypt avec coût 12 (impossible à déchiffrer)
- **Communications :** HTTPS obligatoire (TLS 1.2 minimum)
- **Session PHP :** Cookie httpOnly et Secure
- **Base de données :** Connexion chiffrée (SSL/TLS)

#### Protection contre les attaques
- **Injection SQL :** Requêtes préparées (PDO) systématiques
- **XSS (Cross-Site Scripting) :** Échappement de toutes les sorties HTML
- **CSRF :** Jeton CSRF unique par session et par formulaire
- **Force brute :** Limitation des tentatives de connexion
- **Clickjacking :** Headers X-Frame-Options et CSP

### 3.2 Contrôle d'accès

#### Niveaux d'habilitation
1. **Utilisateur standard :**
   - Voir uniquement ses propres feuilles
   - Créer, modifier, clôturer ses feuilles
   - Exporter ses données

2. **Super-utilisateur de structure :**
   - Voir toutes les feuilles de sa structure
   - Pas de modification des feuilles des autres

3. **Super-utilisateur Direction Générale :**
   - Voir toutes les feuilles de la DGPPE
   - Vue consolidée par structure

4. **Administrateur système :**
   - Gestion des comptes utilisateurs
   - Validation des inscriptions
   - Accès à toutes les fonctionnalités
   - Promotion/rétrogradation des utilisateurs

### 3.3 Traçabilité

#### Journalisation (logs)
- Connexions (réussies et échouées)
- Création/modification/suppression de feuilles
- Actions administratives sensibles
- Conservation : 1 an maximum

#### Données de signature
- Horodatage précis (date et heure)
- Adresse IP du signataire
- User-Agent (type de navigateur)

### 3.4 Sauvegardes

- **Fréquence :** Quotidienne (base de données et fichiers)
- **Rétention :** 30 jours glissants
- **Stockage :** Serveurs séparés géographiquement
- **Chiffrement :** Sauvegardes chiffrées
- **Tests de restauration :** Mensuels

## 4. RESPECT DE LA VIE PRIVÉE

### 4.1 Principes appliqués

#### Minimisation des données
Seules les données strictement nécessaires sont collectées. Aucune donnée sensible (origine ethnique, opinions politiques, santé, etc.) n'est demandée.

#### Transparence
- Politique de confidentialité accessible sur la page de signature
- Information claire sur l'utilisation des données
- Mention légale sur toutes les pages

#### Consentement
- L'acte de signer constitue le consentement explicite
- Possibilité de refuser de signer (pas d'enregistrement)

#### Droit des personnes
- Procédure d'exercice des droits clairement indiquée
- Délai de réponse : 30 jours maximum
- Point de contact identifié

### 4.2 Limitations d'accès

#### Données des participants
- **QUI :** Seul l'organisateur et les administrateurs
- **QUOI :** Données d'émargement de leur réunion uniquement
- **DURÉE :** Pendant la durée légale de conservation

#### Données des organisateurs
- **QUI :** Administrateurs système uniquement
- **QUOI :** Données de compte et statistiques d'usage
- **POURQUOI :** Gestion des comptes et support technique

## 5. PROCÉDURES OPÉRATIONNELLES

### 5.1 Cycle de vie d'une feuille

```
Création → Active → Clôturée → Archivée → Suppression
   |         |         |          |           |
  J0       J+0 à J+X   J+X      J+5 ans   Au-delà
```

1. **Création :** Par un utilisateur autorisé
2. **Active :** Accepte les signatures (QR code valide)
3. **Clôturée :** N'accepte plus de signatures, consultable
4. **Archivée :** Conservation légale (5 ans)
5. **Suppression :** Destruction sécurisée après délai légal

### 5.2 Gestion des comptes

#### Création de compte
1. Inscription en ligne (formulaire)
2. Validation par administrateur
3. Notification par email
4. Activation du compte

#### Désactivation de compte
- Départ de l'agent : Suspension du compte
- Conservation des données historiques : 1 an
- Anonymisation après délai légal

### 5.3 Procédure de suppression

#### Suppression de données personnelles (sur demande)
1. Demande écrite à contact@dgppe.gouv.sn
2. Vérification d'identité
3. Suppression sous 30 jours (sauf obligation légale)
4. Confirmation par email

#### Suppression automatique
- Comptes non validés : 90 jours
- Comptes inactifs : 2 ans sans connexion
- Données de logs : 1 an

## 6. HÉBERGEMENT ET LOCALISATION

### 6.1 Serveur d'hébergement

**Option 1 : Hébergement national**
- Datacenter au Sénégal
- Garantie de souveraineté des données
- Conformité totale avec la législation sénégalaise

**Option 2 : Hébergement cloud sécurisé**
- Fournisseur certifié (ISO 27001, SOC 2)
- Localisation : Union Européenne (niveau de protection équivalent)
- Clause contractuelle de protection des données
- Aucun accès par pays tiers

### 6.2 Accès aux données

- **Équipe technique DGPPE :** Accès administrateur
- **Hébergeur :** Accès infrastructure uniquement (pas aux données)
- **Sous-traitants éventuels :** Accord de confidentialité obligatoire

## 7. PLAN DE CONTINUITÉ ET DE SÉCURITÉ

### 7.1 En cas d'incident de sécurité

1. **Détection :** Monitoring en temps réel
2. **Réaction :** Équipe d'intervention sous 2 heures
3. **Notification CDP :** Sous 72 heures si violation de données
4. **Notification personnes :** Si risque élevé pour leurs droits
5. **Analyse et corrections :** Rapport d'incident et mesures correctives

### 7.2 Disponibilité du service

- **Objectif de disponibilité :** 99% hors maintenance
- **Maintenance programmée :** Hors heures ouvrables
- **Procédure de restauration :** Testée mensuellement

## 8. FORMATION ET SENSIBILISATION

### 8.1 Personnel habilité

- Formation initiale : Protection des données personnelles
- Rappels annuels : Obligations légales
- Charte de confidentialité : Signée par tous les utilisateurs

### 8.2 Documentation utilisateur

- Guide d'utilisation disponible en ligne
- FAQ sur la protection des données
- Contacts pour toute question

## 9. ÉVOLUTIONS PRÉVUES

Le système e-Présence pourra évoluer avec les fonctionnalités suivantes (déclaration modificative ultérieure) :
- Signature électronique avancée (certificat numérique)
- Intégration avec l'annuaire LDAP de l'administration
- API pour interconnexion avec d'autres systèmes
- Module de statistiques avancées

Toute évolution substantielle fera l'objet d'une déclaration modificative auprès de la CDP.

---

**Date :** _____________ 2026

**Responsable technique :**

Nom : _________________________
Fonction : _____________________
Signature : ____________________

---

**Pour validation :**

**Le Directeur Général de la DGPPE**

Nom : _________________________
Signature : ____________________
