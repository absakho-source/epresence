<?php
/**
 * e-Présence - Liste des structures DGPPE
 * Basé sur l'organigramme de la Direction Générale de la Planification et des Politiques Économiques
 */

// Structures organisées par catégorie
$DGPPE_STRUCTURES = array(
    'Cabinet du Directeur Général' => array(
        'DG' => 'Cabinet du Directeur Général',
        'COORD' => 'Coordonnateur',
        'CT1' => 'Conseiller Technique 1',
        'CT2' => 'Conseiller Technique 2',
        'CT3' => 'Conseiller Technique 3',
        'BC' => 'Bureau du Courrier',
        'BI' => 'Bureau Informatique',
        'BA' => 'Bureau des Archives',
        'BCOM' => 'Bureau de la Communication',
    ),
    'Direction de la Planification (DP)' => array(
        'DP' => 'Direction de la Planification',
        'DP-DPG' => 'Division de la Planification Générale',
        'DP-DPS' => 'Division de la Planification Sectorielle',
        'DP-DPR' => 'Division de la Planification Régionale',
        'DP-DAF' => 'Division Administrative et Financière',
    ),
    'Direction de la Prévision et des Études Économiques (DPEE)' => array(
        'DPEE' => 'Direction de la Prévision et des Études Économiques',
        'DPEE-DAC' => 'Division de l\'Analyse Conjoncturelle',
        'DPEE-DPM' => 'Division des Projections Macroéconomiques',
        'DPEE-DEPE' => 'Division des Études et Politiques Économiques',
    ),
    'Direction du Développement du Capital Humain (DDCH)' => array(
        'DDCH' => 'Direction du Développement du Capital Humain',
        'DDCH-DP' => 'Division de la Population',
        'DDCH-DPS' => 'Division de la Planification Sociale',
    ),
    'Direction de l\'Administration et du Personnel (DAP)' => array(
        'DAP' => 'Direction de l\'Administration et du Personnel',
        'DAP-DFL' => 'Division des Finances et de la Logistique',
        'DAP-DRHAS' => 'Division des Ressources Humaines et de l\'Action Sociale',
    ),
    'Centre d\'Études de Politiques pour le Développement (CEPOD)' => array(
        'CEPOD' => 'Centre d\'Études de Politiques pour le Développement',
        'CEPOD-EXP' => 'Experts CEPOD',
        'CEPOD-AR' => 'Assistants de Recherche CEPOD',
    ),
    'Cellule de Suivi de l\'Intégration (CSI)' => array(
        'CSI' => 'Cellule de Suivi de l\'Intégration',
        'CSI-DIR' => 'Division Intégration Régionale',
    ),
    'Unité de Coordination et de Suivi de la Politique Économique (UCSPE)' => array(
        'UCSPE' => 'Unité de Coordination et de Suivi de la Politique Économique',
        'UCSPE-CPR' => 'Division Croissance et Réduction de la Pauvreté',
        'UCSPE-PS' => 'Division Politiques Sociales et Services',
        'UCSPE-BG' => 'Division Bonne Gouvernance',
    ),
    'Services Régionaux de la Planification (SRP)' => array(
        'SRP-DK' => 'SRP Dakar',
        'SRP-TH' => 'SRP Thiès',
        'SRP-SL' => 'SRP Saint-Louis',
        'SRP-LG' => 'SRP Louga',
        'SRP-MT' => 'SRP Matam',
        'SRP-KL' => 'SRP Kaolack',
        'SRP-KF' => 'SRP Kaffrine',
        'SRP-FK' => 'SRP Fatick',
        'SRP-DL' => 'SRP Diourbel',
        'SRP-TB' => 'SRP Tambacounda',
        'SRP-KD' => 'SRP Kédougou',
        'SRP-KK' => 'SRP Kolda',
        'SRP-SD' => 'SRP Sédhiou',
        'SRP-ZG' => 'SRP Ziguinchor',
    ),
    'Autres' => array(
        'EXTERNE' => 'Partenaire externe',
        'AUTRE' => 'Autre structure',
    ),
);

/**
 * Obtenir la liste plate des structures (code => nom)
 */
function getStructuresList() {
    global $DGPPE_STRUCTURES;
    $list = array();
    foreach ($DGPPE_STRUCTURES as $category => $structures) {
        foreach ($structures as $code => $name) {
            $list[$code] = $name;
        }
    }
    return $list;
}

/**
 * Obtenir les structures groupées par catégorie
 */
function getStructuresGrouped() {
    global $DGPPE_STRUCTURES;
    return $DGPPE_STRUCTURES;
}

/**
 * Obtenir le nom d'une structure par son code
 */
function getStructureName($code) {
    $list = getStructuresList();
    return isset($list[$code]) ? $list[$code] : $code;
}

/**
 * Vérifier si un code de structure est valide
 */
function isValidStructure($code) {
    $list = getStructuresList();
    return isset($list[$code]);
}
