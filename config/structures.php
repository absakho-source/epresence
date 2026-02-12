<?php
/**
 * e-Présence - Liste des organisations
 * Basé sur la liste GECOD (organisations sénégalaises et partenaires)
 */

// Organisations organisées par catégorie
$ORGANIZATIONS = [
    'Présidence de la République' => [
        'Présidence de la République - PR',
        'Cabinet du Président de la République',
        'Inspection générale d\'État - IGE',
        'Office national de lutte contre la Corruption - OFNAC',
        'Comité d\'Orientation stratégique du Pétrole et du Gaz - COS-PETROGAZ',
        'Délégation générale aux Affaires religieuses - DEGAR',
        'Délégation générale au renseignement national',
        'Secrétariat général de la Présidence de la République - SG/PR',
        'Médiateur de la République',
        'Commission de Protection des Données Personnelles - CDP',
        'Commission nationale des Droits de l\'Homme - CNDH',
        'Conseil national de Régulation de l\'Audiovisuel - CNRA',
        'Commission Electorale Nationale Autonome - CENA',
        'Autorité de Régulation des Télécommunications et des Postes - ARTP',
        'Autorité de Régulation de la Commande publique - ARCOP',
        'Commission de Régulation du Secteur de l\'Energie - CRSE',
        'Agence sénégalaise d\'études spatiales',
    ],
    'Primature' => [
        'Primature - PM',
        'Cabinet du Premier Ministre',
        'Secrétariat général du Gouvernement - SGG',
        'Direction générale de la Surveillance et du Contrôle de l\'Occupation du Sol - DSCOS',
        'Direction des Archives du Sénégal',
        'Direction de l\'Imprimerie nationale',
        'Haute Autorité du WAQF',
        'Agence Nationale pour la relance des Activités en Casamance - ANRAC',
    ],
    'Ministère de la Justice' => [
        'Ministère de la Justice - MJ',
        'Direction générale de l\'Administration pénitentiaire - DGAP',
        'Direction des Affaires civiles et du Sceau',
        'Direction des Droits humains',
    ],
    'Ministère de l\'Énergie, du Pétrole et des Mines' => [
        'Ministère de l\'Énergie, du Pétrole et des Mines - MEPM',
        'Direction générale de l\'Energie - DGE',
        'Direction générale des Hydrocarbures - DGH',
        'Direction générale des Mines et de la Géologie - DGMG',
    ],
    'Ministère des Affaires étrangères' => [
        'Ministère de l\'Intégration africaine, des Affaires étrangères et des Sénégalais de l\'Extérieur - MAESE',
        'Direction des Sénégalais de l\'Extérieur - DSE',
        'Délégation générale au Pèlerinage aux Lieux Saints de l\'Islam',
    ],
    'Ministère des Forces Armées' => [
        'Ministère des Forces Armées - MFA',
        'État-Major général des Armées - EMGA',
        'Haut-Commandement de la Gendarmerie nationale - HCGN',
    ],
    'Ministère de l\'Intérieur' => [
        'Ministère de l\'Intérieur et de la Sécurité publique - MISP',
        'Direction générale de la Police nationale - DGPN',
        'Direction générale de l\'Administration territoriale - DGAT',
        'Direction générale des Elections - DGE',
        'Brigade nationale des Sapeurs-Pompiers - BNSP',
    ],
    'Ministère de l\'Économie, du Plan et de la Coopération' => [
        'Ministère de l\'Économie, du Plan et de la Coopération - MEPC',
    ],
    'Cabinet MEPC' => [
        'Services propres du Cabinet',
        'Inspection interne',
        'Cellule Attractivité et Compétitivité',
        'Cellule des Statistiques et Données',
        'Cellule de Communication',
        'Comité paritaire public-privé des Zones économiques spéciales',
        'Unité nationale d\'Appui aux Partenariats Public-Privé',
    ],
    'Secrétariat général MEPC' => [
        'Services propres du Secrétariat général',
        'Cellule de l\'Évaluation et de la Performance',
        'Cellule de Passation des Marchés publics',
        'Cellule des Affaires juridiques',
        'Cellule de l\'Informatique',
        'Cellule du Genre et de l\'Équité',
        'Cellule de Coordination du Contrôle de Gestion',
        'Bureau des Archives et de la Documentation',
        'Bureau du Courrier commun',
    ],
    'Direction Générale de la Planification et des Politiques Économiques' => [
        'Direction Générale - DGPPE',
        'Direction de l\'Administration et du Personnel - DAP/DGPPE',
        'Direction de la Planification - DP',
        'Direction de la Prévision et des Études Économiques - DPEE',
        'Direction du Développement du Capital Humain - DDCH',
        'Centre d\'Études de Politiques pour le Développement - CEPOD',
        'Cellule de Suivi de l\'Intégration - CSI',
        'Unité de Coordination et de Suivi de la Politique Économique - UCSPE',
        'Services Régionaux de la Planification - SRP',
    ],
    'Direction Générale de la Coopération, des Financements Extérieurs et du Développement du Secteur Privé' => [
        'Direction Générale - DGCFEDSP',
        'Direction de l\'Administration et du Personnel - DAP/DGCFEDSP',
        'Direction de la Coopération économique et financière - DCEF',
        'Direction des Financements et des Partenariats Public-Privé - DFPPP',
        'Direction du Suivi-Evaluation des Performances des Projets et Programmes - DSEPPP',
        'Direction du Développement du Secteur Privé - DDSP',
    ],
    'Direction de l\'Administration Générale et de l\'Équipement' => [
        'DAGE - Services propres',
    ],
    'Direction des Ressources Humaines' => [
        'DRH - Services propres',
    ],
    'Autres structures MEPC' => [
        'ANSD - Agence Nationale de la Statistique et de la Démographie',
        'FONGIP - Fonds de Garantie des Investissements prioritaires',
    ],
    'Ministère des Finances et du Budget' => [
        'Ministère des Finances et du Budget - MFB',
        'Inspection générale des Finances - IGF',
        'Direction générale de la Comptabilité publique et du Trésor - DGCPT',
        'Direction générale des Douanes - DGD',
        'Direction générale des Impôts et des Domaines - DGID',
        'Direction générale du Budget - DGB',
    ],
    'Ministère de l\'Enseignement supérieur' => [
        'Ministère de l\'Enseignement supérieur, de la Recherche et de l\'Innovation - MESRI',
        'Direction générale de l\'Enseignement supérieur - DGES',
        'Direction générale de la Recherche et de l\'Innovation - DGRI',
    ],
    'Ministère des Transports' => [
        'Ministère des Transports terrestres et aériens - MTTA',
        'Direction générale des Transports routiers - DGTR',
        'Agence nationale de l\'Aviation civile et de la Météorologie - ANACIM',
    ],
    'Autres Ministères' => [
        'Ministère de la Communication, des Télécommunications et du Numérique - MCTN',
        'Ministère de l\'Éducation nationale - MEN',
        'Ministère de l\'Agriculture, de la Souveraineté alimentaire et de l\'Élevage - MASAE',
        'Ministère de l\'Hydraulique et de l\'Assainissement - MHA',
        'Ministère de la Santé et de l\'Hygiène publique - MSHP',
        'Ministère de la Famille, de l\'Action sociale et des Solidarités - MFASS',
        'Ministère de l\'Emploi et de la Formation professionnelle et technique - MEFPT',
        'Ministère de l\'Environnement et de la Transition écologique - METE',
        'Ministère de l\'Urbanisme, des Collectivités territoriales et de l\'Aménagement des Territoires - MUCTAT',
        'Ministère de l\'Industrie et du Commerce - MIC',
        'Ministère des Pêches et de l\'Économie maritime - MPEM',
        'Ministère de la Fonction publique, du Travail et de la Réforme du Service public - MFPTRSP',
        'Ministère de la Jeunesse et des Sports - MJS',
        'Ministère de la Microfinance, de l\'Économie sociale et solidaire - MMESS',
        'Ministère du Tourisme et de l\'Artisanat - MTA',
        'Ministère de la Culture, du Patrimoine historique et de l\'Artisanat - MCPHA',
        'Ministère des Infrastructures et des Transports maritimes et portuaires - MITMP',
    ],
    'Institutions de la République' => [
        'Assemblée nationale - AN',
        'Conseil économique, social et environnemental - CESE',
        'Cour suprême',
        'Cour des Comptes',
        'Conseil constitutionnel',
        'Haut Conseil des Collectivités territoriales - HCCT',
    ],
    'Partenaires Techniques et Financiers' => [
        'Banque mondiale - BM',
        'Fonds monétaire international - FMI',
        'Banque africaine de Développement - BAD',
        'Banque centrale des États de l\'Afrique de l\'Ouest - BCEAO',
        'Banque ouest-africaine de Développement - BOAD',
        'Programme des Nations Unies pour le Développement - PNUD',
        'Organisation des Nations Unies - ONU',
        'Union européenne - UE',
        'Agence française de Développement - AFD',
        'Coopération allemande - GIZ',
        'Agence des États-Unis pour le Développement international - USAID',
        'Agence japonaise de Coopération internationale - JICA',
        'Banque islamique de Développement - BID',
        'Commission de l\'Union africaine - UA',
        'Commission de la CEDEAO - CEDEAO',
        'Commission de l\'UEMOA - UEMOA',
        'Organisation internationale de la Francophonie - OIF',
        'Organisation mondiale de la Santé - OMS',
        'UNICEF',
        'Organisation mondiale du Commerce - OMC',
    ],
    'Secteur Parapublic' => [
        'Société nationale d\'Électricité - SENELEC',
        'Société nationale des Eaux du Sénégal - SONES',
        'Sénégalaise des Eaux - SDE',
        'Port autonome de Dakar - PAD',
        'Aéroport international Blaise Diagne - AIBD',
        'Société africaine de Raffinage - SAR',
        'PETROSEN',
        'Société nationale La Poste',
        'Société nationale des Télécommunications du Sénégal - SONATEL',
        'Caisse des Dépôts et Consignations - CDC',
        'Fonds souverain d\'Investissements stratégiques - FONSIS',
    ],
    'Universités' => [
        'Université Cheikh Anta Diop de Dakar - UCAD',
        'Université Gaston Berger de Saint-Louis - UGB',
        'Université Assane Seck de Ziguinchor - UASZ',
        'Université Alioune Diop de Bambey - UADB',
        'Université de Thiès - UT',
        'Université Amadou Mahtar Mbow - UAM',
        'Université du Sine Saloum El Hadj Ibrahima Niass - USSEIN',
        'Université Iba Der Thiam de Thiès - UIDT',
    ],
];

/**
 * Obtenir la liste plate des structures (juste les noms)
 */
function getStructuresList() {
    global $ORGANIZATIONS;
    $list = [];
    foreach ($ORGANIZATIONS as $category => $structures) {
        foreach ($structures as $name) {
            $list[] = $name;
        }
    }
    return $list;
}

/**
 * Obtenir les structures groupées par catégorie
 */
function getStructuresGrouped() {
    global $ORGANIZATIONS;
    return $ORGANIZATIONS;
}

/**
 * Rechercher une structure par nom partiel
 */
function searchStructure($query) {
    $list = getStructuresList();
    $results = [];
    $query = mb_strtolower($query);
    foreach ($list as $name) {
        if (mb_strpos(mb_strtolower($name), $query) !== false) {
            $results[] = $name;
        }
    }
    return $results;
}

/**
 * Obtenir le nom d'une structure (retourne la valeur telle quelle car on utilise des noms)
 */
function getStructureName($structure) {
    if (empty($structure)) {
        return '';
    }
    // Les structures sont maintenant stockées par leur nom complet
    return $structure;
}

/**
 * Obtenir la catégorie d'une structure par son nom
 */
function getStructureCategory($structure) {
    global $ORGANIZATIONS;
    if (empty($structure)) {
        return null;
    }
    // Normaliser d'abord (convertir les anciens acronymes)
    $normalizedStructure = normalizeStructureName($structure);
    foreach ($ORGANIZATIONS as $category => $structures) {
        if (in_array($normalizedStructure, $structures) || in_array($structure, $structures)) {
            return $category;
        }
    }
    return null;
}

/**
 * Obtenir toutes les structures d'une même catégorie
 */
function getStructureCodesInCategory($structure) {
    global $ORGANIZATIONS;
    $category = getStructureCategory($structure);
    if ($category && isset($ORGANIZATIONS[$category])) {
        return $ORGANIZATIONS[$category];
    }
    return [$structure];
}

/**
 * Vérifier si deux structures appartiennent à la même catégorie
 */
function areStructuresInSameCategory($struct1, $struct2) {
    $cat1 = getStructureCategory($struct1);
    $cat2 = getStructureCategory($struct2);
    return $cat1 !== null && $cat1 === $cat2;
}

/**
 * Structures du Ministère pour le formulaire d'inscription
 * Organisées par catégorie pour affichage groupé
 */
$MEPC_STRUCTURES = [
    'Cabinet MEPC' => [
        'Services propres du Cabinet',
        'Inspection interne',
        'Cellule Attractivité et Compétitivité',
        'Cellule des Statistiques et Données',
        'Cellule de Communication',
        'Comité paritaire public-privé des Zones économiques spéciales',
        'Unité nationale d\'Appui aux Partenariats Public-Privé',
    ],
    'Secrétariat général MEPC' => [
        'Services propres du Secrétariat général',
        'Cellule de l\'Évaluation et de la Performance',
        'Cellule de Passation des Marchés publics',
        'Cellule des Affaires juridiques',
        'Cellule de l\'Informatique',
        'Cellule du Genre et de l\'Équité',
        'Cellule de Coordination du Contrôle de Gestion',
        'Bureau des Archives et de la Documentation',
        'Bureau du Courrier commun',
    ],
    'Direction Générale de la Planification et des Politiques Économiques' => [
        'Direction Générale - DGPPE',
        'Direction de l\'Administration et du Personnel - DAP/DGPPE',
        'Direction de la Planification - DP',
        'Direction de la Prévision et des Études Économiques - DPEE',
        'Direction du Développement du Capital Humain - DDCH',
        'Centre d\'Études de Politiques pour le Développement - CEPOD',
        'Cellule de Suivi de l\'Intégration - CSI',
        'Unité de Coordination et de Suivi de la Politique Économique - UCSPE',
        'Services Régionaux de la Planification - SRP',
    ],
    'Direction Générale de la Coopération, des Financements Extérieurs et du Développement du Secteur Privé' => [
        'Direction Générale - DGCFEDSP',
        'Direction de l\'Administration et du Personnel - DAP/DGCFEDSP',
        'Direction de la Coopération économique et financière - DCEF',
        'Direction des Financements et des Partenariats Public-Privé - DFPPP',
        'Direction du Suivi-Evaluation des Performances des Projets et Programmes - DSEPPP',
        'Direction du Développement du Secteur Privé - DDSP',
    ],
    'Direction de l\'Administration Générale et de l\'Équipement' => [
        'DAGE - Services propres',
    ],
    'Direction des Ressources Humaines' => [
        'DRH - Services propres',
    ],
    'Autres structures' => [
        'ANSD - Agence Nationale de la Statistique et de la Démographie',
        'FONGIP - Fonds de Garantie des Investissements prioritaires',
    ],
];

/**
 * Obtenir les structures MEPC pour le formulaire d'inscription (groupées)
 */
function getMEPCStructuresGrouped() {
    global $MEPC_STRUCTURES;
    return $MEPC_STRUCTURES;
}

/**
 * Obtenir les structures MEPC sous forme de liste plate
 */
function getMEPCStructures() {
    global $MEPC_STRUCTURES;
    $list = [];
    foreach ($MEPC_STRUCTURES as $category => $structures) {
        foreach ($structures as $structure) {
            $list[] = $structure;
        }
    }
    return $list;
}

/**
 * Obtenir les structures DGPPE pour le formulaire d'inscription (rétrocompatibilité)
 * @deprecated Utiliser getMEPCStructures() à la place
 */
function getDGPPEStructures() {
    return getMEPCStructures();
}

/**
 * Vérifie si une structure est une structure "chef" de sa catégorie
 * (Services propres pour la plupart, Direction Générale pour DGPPE)
 * Les utilisateurs de ces structures peuvent voir toutes les feuilles de leur catégorie
 *
 * @param string $structure Le nom de la structure
 * @return bool True si c'est une structure chef
 */
function isCategorySuperStructure($structure) {
    if (empty($structure)) {
        return false;
    }

    $normalized = normalizeStructureName($structure);

    // DGPPE : Direction Générale
    if (strpos($normalized, 'Direction Générale') === 0 ||
        $normalized === 'Direction Générale - DG') {
        return true;
    }

    // Toutes les autres catégories : Services propres
    if (strpos($normalized, 'Services propres') !== false ||
        strpos($normalized, '- Services propres') !== false) {
        return true;
    }

    return false;
}

/**
 * Mapping des anciens acronymes DGPPE vers les noms complets avec acronymes
 */
$DGPPE_ACRONYMS_MAP = [
    'DG' => 'Direction Générale - DGPPE',
    'Direction générale' => 'Direction Générale - DGPPE',
    'Direction Générale' => 'Direction Générale - DGPPE',
    'DAP' => 'Direction de l\'Administration et du Personnel - DAP/DGPPE',
    'Direction de l\'Administration et du Personnel' => 'Direction de l\'Administration et du Personnel - DAP/DGPPE',
    'DP' => 'Direction de la Planification - DP',
    'Direction de la Planification' => 'Direction de la Planification - DP',
    'DPEE' => 'Direction de la Prévision et des Études Économiques - DPEE',
    'Direction de la Prévision et des Études Économiques' => 'Direction de la Prévision et des Études Économiques - DPEE',
    'DDCH' => 'Direction du Développement du Capital Humain - DDCH',
    'Direction du Développement du Capital Humain' => 'Direction du Développement du Capital Humain - DDCH',
    'CEPOD' => 'Centre d\'Études de Politiques pour le Développement - CEPOD',
    'Centre d\'Études de Politiques pour le Développement' => 'Centre d\'Études de Politiques pour le Développement - CEPOD',
    'CSI' => 'Cellule de Suivi de l\'Intégration - CSI',
    'Cellule de Suivi de l\'Intégration' => 'Cellule de Suivi de l\'Intégration - CSI',
    'UCSPE' => 'Unité de Coordination et de Suivi de la Politique Économique - UCSPE',
    'Unité de Coordination et de Suivi de la Politique Économique' => 'Unité de Coordination et de Suivi de la Politique Économique - UCSPE',
    'SRP' => 'Services Régionaux de la Planification - SRP',
    'Services Régionaux de la Planification' => 'Services Régionaux de la Planification - SRP',
];

/**
 * Normaliser le nom d'une structure (convertir les anciens acronymes en noms complets)
 */
function normalizeStructureName($structure) {
    global $DGPPE_ACRONYMS_MAP;
    if (empty($structure)) {
        return '';
    }
    // Si c'est un ancien acronyme, le convertir
    if (isset($DGPPE_ACRONYMS_MAP[$structure])) {
        return $DGPPE_ACRONYMS_MAP[$structure];
    }
    return $structure;
}
