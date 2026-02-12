<?php
/**
 * e-Présence MEPC - Export Excel (format HTML stylisé)
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/structures.php';
requireLogin();

$sheetId = intval($_GET['id'] ?? 0);
$currentUser = getCurrentUser();
$isStructureAdmin = !empty($currentUser['is_structure_admin']) && !empty($currentUser['structure']);

// Récupérer la feuille avec infos du créateur
$stmt = db()->prepare("
    SELECT s.*, u.first_name as creator_first_name, u.last_name as creator_last_name, u.structure as creator_structure
    FROM sheets s
    JOIN users u ON s.user_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$sheetId]);
$sheet = $stmt->fetch();

if (!$sheet) {
    die('Feuille non trouvée.');
}

// Vérifier les droits d'accès
$isOwner = ($sheet['user_id'] == getCurrentUserId());
$canViewAsStructureAdmin = false;
$canViewAsDGAdmin = false;
$canViewAsGlobalAdmin = isAdmin();

if (!$isOwner && $isStructureAdmin) {
    $userCategory = getStructureCategory($currentUser['structure']);
    $sheetCategory = getStructureCategory($sheet['creator_structure']);

    // Super-utilisateur de catégorie (Services propres ou Direction Générale)
    // peut voir TOUTES les feuilles de sa catégorie
    $isCategorySuperAdmin = isCategorySuperStructure($currentUser['structure']);

    if ($isCategorySuperAdmin && $userCategory === $sheetCategory) {
        $canViewAsDGAdmin = true;
    } else {
        // Vérifier si le créateur appartient à la même catégorie de structure
        $structureCodes = getStructureCodesInCategory($currentUser['structure']);
        $canViewAsStructureAdmin = in_array($sheet['creator_structure'], $structureCodes);
    }
}

if (!$isOwner && !$canViewAsStructureAdmin && !$canViewAsDGAdmin && !$canViewAsGlobalAdmin) {
    die('Vous n\'avez pas accès à cette feuille.');
}

// Récupérer les signatures
$signaturesStmt = db()->prepare("
    SELECT * FROM signatures
    WHERE sheet_id = ?
    ORDER BY signed_for_date ASC, signed_at ASC
");
$signaturesStmt->execute([$sheetId]);
$signatures = $signaturesStmt->fetchAll();

// Détecter si c'est un événement multi-jours
$isMultiDay = !empty($sheet['end_date']) && $sheet['end_date'] !== $sheet['event_date'];

// Générer la liste des jours et grouper les signatures par jour
$eventDays = [];
$signaturesByDay = [];

if ($isMultiDay) {
    $startDate = new DateTime($sheet['event_date']);
    $endDate = new DateTime($sheet['end_date']);
    $endDate->modify('+1 day');
    $period = new DatePeriod($startDate, new DateInterval('P1D'), $endDate);
    foreach ($period as $date) {
        $day = $date->format('Y-m-d');
        $eventDays[] = $day;
        $signaturesByDay[$day] = [];
    }
    // Grouper les signatures par jour
    foreach ($signatures as $sig) {
        $day = $sig['signed_for_date'] ?? $sheet['event_date'];
        if (isset($signaturesByDay[$day])) {
            $signaturesByDay[$day][] = $sig;
        }
    }
} else {
    $eventDays[] = $sheet['event_date'];
    $signaturesByDay[$sheet['event_date']] = $signatures;
}

// Nom du fichier
$filename = 'emargement-' . $sheet['unique_code'] . '-' . date('Y-m-d') . '.xls';

// Headers pour téléchargement Excel (format HTML)
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
        }
        .header-section {
            text-align: center;
            margin-bottom: 10px;
        }
        .ministry {
            font-size: 12pt;
            font-weight: bold;
            color: #00703c;
        }
        .title {
            font-size: 16pt;
            font-weight: bold;
            color: #00703c;
            text-align: center;
            margin: 15px 0;
            border-bottom: 3px solid #00703c;
            padding-bottom: 10px;
        }
        .subtitle {
            font-size: 14pt;
            text-align: center;
            margin-bottom: 15px;
        }
        .info-table {
            margin: 10px auto;
            border: none;
        }
        .info-table td {
            padding: 5px 15px;
            font-size: 11pt;
        }
        .info-label {
            font-weight: bold;
            color: #00703c;
        }
        .description {
            background-color: #f5f5f5;
            padding: 10px;
            margin: 10px 0;
            border-left: 3px solid #00703c;
            font-style: italic;
        }
        table.data {
            border-collapse: collapse;
            width: 100%;
            margin-top: 15px;
        }
        table.data th {
            background-color: #00703c;
            color: white;
            font-weight: bold;
            padding: 8px 5px;
            border: 1px solid #005a30;
            text-align: left;
        }
        table.data td {
            padding: 6px 5px;
            border: 1px solid #ccc;
            vertical-align: middle;
        }
        .tel {
            mso-number-format:'\@';
        }
        table.data tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .col-num {
            width: 30px;
            text-align: center;
        }
        .footer-section {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 2px solid #00703c;
            font-size: 10pt;
            color: #666;
        }
        .footer-table {
            width: 100%;
        }
        .footer-table td {
            padding: 5px;
        }
        .total {
            font-weight: bold;
        }
        .day-header {
            background-color: #e8f5e9;
            padding: 8px 12px;
            margin: 15px 0 8px 0;
            border-left: 4px solid #00703c;
            font-size: 12pt;
            color: #00703c;
        }
        .day-header.new-page {
            page-break-before: always;
            break-before: page;
        }
    </style>
</head>
<body>
    <!-- En-tête -->
    <div class="header-section">
        <div class="ministry"><?= MINISTRY_NAME ?></div>
    </div>

    <!-- Titre -->
    <div class="title">FEUILLE D'ÉMARGEMENT</div>
    <div class="subtitle"><?= htmlspecialchars($sheet['title']) ?></div>

    <!-- Informations de l'événement -->
    <table class="info-table" align="center">
        <tr>
            <?php if ($isMultiDay): ?>
            <td><span class="info-label">Période :</span> Du <?= formatDateFr($sheet['event_date']) ?> au <?= formatDateFr($sheet['end_date']) ?></td>
            <?php else: ?>
            <td><span class="info-label">Date :</span> <?= formatDateFr($sheet['event_date']) ?></td>
            <?php endif; ?>
            <?php if ($sheet['event_time']): ?>
            <td><span class="info-label">Heure :</span> <?= formatTime($sheet['event_time']) ?></td>
            <?php endif; ?>
            <?php if ($sheet['location']): ?>
            <td><span class="info-label">Lieu :</span> <?= htmlspecialchars($sheet['location']) ?></td>
            <?php endif; ?>
        </tr>
    </table>

    <?php if (!empty($sheet['description'])): ?>
    <!-- Description -->
    <div class="description">
        <strong>Description :</strong> <?= htmlspecialchars($sheet['description']) ?>
    </div>
    <?php endif; ?>

    <!-- Tableaux des signatures par jour -->
    <?php foreach ($eventDays as $dayIndex => $day): ?>
        <?php $daySignatures = $signaturesByDay[$day]; ?>

        <?php if ($isMultiDay): ?>
        <!-- Titre du jour -->
        <div class="day-header<?= $dayIndex > 0 ? ' new-page' : '' ?>">
            <strong>Jour <?= $dayIndex + 1 ?> :</strong> <?= formatDateFr($day) ?>
            (<?= count($daySignatures) ?> participant<?= count($daySignatures) > 1 ? 's' : '' ?>)
        </div>
        <?php endif; ?>

        <table class="data">
            <thead>
                <tr>
                    <th class="col-num">N°</th>
                    <th>Prénom</th>
                    <th>Nom</th>
                    <th>Structure</th>
                    <th>Fonction</th>
                    <th>Téléphone</th>
                    <th>Tél. secondaire</th>
                    <th>Email</th>
                    <th>Date signature</th>
                    <th>Heure</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $num = 1;
                foreach ($daySignatures as $sig):
                ?>
                <tr>
                    <td class="col-num"><?= $num++ ?></td>
                    <td><?= htmlspecialchars($sig['first_name']) ?></td>
                    <td><?= htmlspecialchars($sig['last_name']) ?></td>
                    <td><?= htmlspecialchars($sig['structure'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($sig['function_title'] ?? '-') ?></td>
                    <td class="tel"><?= htmlspecialchars(formatPhone($sig['phone'])) ?></td>
                    <td class="tel"><?= !empty($sig['phone_secondary']) ? htmlspecialchars(formatPhone($sig['phone_secondary'])) : '-' ?></td>
                    <td><?= htmlspecialchars($sig['email']) ?></td>
                    <td><?= date('d/m/Y', strtotime($sig['signed_at'])) ?></td>
                    <td><?= date('H:i', strtotime($sig['signed_at'])) ?></td>
                </tr>
                <?php endforeach; ?>

                <?php
                // Ajouter seulement 3 lignes vides supplémentaires (optimisation papier)
                $emptyRows = min(3, 10 - count($daySignatures));
                if ($emptyRows < 0) $emptyRows = 0;
                for ($i = 0; $i < $emptyRows; $i++):
                ?>
                <tr>
                    <td class="col-num"><?= $num++ ?></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    <?php endforeach; ?>

    <!-- Pied de page -->
    <div class="footer-section">
        <table class="footer-table">
            <tr>
                <?php if ($isMultiDay): ?>
                <td class="total">Total signatures : <?= count($signatures) ?> (sur <?= count($eventDays) ?> jours)</td>
                <?php else: ?>
                <td class="total">Total participants : <?= count($signatures) ?></td>
                <?php endif; ?>
                <td align="right">Document généré le : <?= date('d/m/Y à H:i') ?></td>
            </tr>
        </table>
    </div>
</body>
</html>
