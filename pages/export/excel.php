<?php
/**
 * e-Présence DGPPE - Export Excel (format HTML stylisé)
 */

require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$sheetId = intval($_GET['id'] ?? 0);

// Récupérer la feuille
$stmt = db()->prepare("SELECT * FROM sheets WHERE id = ? AND user_id = ?");
$stmt->execute([$sheetId, getCurrentUserId()]);
$sheet = $stmt->fetch();

if (!$sheet) {
    die('Feuille non trouvée.');
}

// Récupérer les signatures
$signaturesStmt = db()->prepare("
    SELECT * FROM signatures
    WHERE sheet_id = ?
    ORDER BY signed_at ASC
");
$signaturesStmt->execute([$sheetId]);
$signatures = $signaturesStmt->fetchAll();

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
        .country {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .motto {
            font-size: 10pt;
            font-style: italic;
            color: #666;
        }
        .ministry {
            font-size: 12pt;
            font-weight: bold;
            color: #00703c;
        }
        .org {
            font-size: 10pt;
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
    </style>
</head>
<body>
    <!-- En-tête ministériel -->
    <div class="header-section">
        <div class="country">République du Sénégal</div>
        <div class="motto">Un Peuple - Un But - Une Foi</div>
        <div class="ministry"><?= MINISTRY_NAME ?></div>
        <div class="org"><?= ORG_FULL_NAME ?></div>
    </div>

    <!-- Titre -->
    <div class="title">FEUILLE D'ÉMARGEMENT</div>
    <div class="subtitle"><?= htmlspecialchars($sheet['title']) ?></div>

    <!-- Informations de l'événement -->
    <table class="info-table" align="center">
        <tr>
            <td><span class="info-label">Date :</span> <?= formatDateFr($sheet['event_date']) ?></td>
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

    <!-- Tableau des signatures -->
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
            foreach ($signatures as $sig):
            ?>
            <tr>
                <td class="col-num"><?= $num++ ?></td>
                <td><?= htmlspecialchars($sig['first_name']) ?></td>
                <td><?= htmlspecialchars($sig['last_name']) ?></td>
                <td><?= htmlspecialchars($sig['structure'] ?? '-') ?></td>
                <td><?= htmlspecialchars($sig['function_title'] ?? '-') ?></td>
                <td><?= htmlspecialchars(formatPhone($sig['phone'])) ?></td>
                <td><?= !empty($sig['phone_secondary']) ? htmlspecialchars(formatPhone($sig['phone_secondary'])) : '-' ?></td>
                <td><?= htmlspecialchars($sig['email']) ?></td>
                <td><?= date('d/m/Y', strtotime($sig['signed_at'])) ?></td>
                <td><?= date('H:i', strtotime($sig['signed_at'])) ?></td>
            </tr>
            <?php endforeach; ?>

            <?php
            // Lignes vides pour atteindre au moins 10 lignes
            $minRows = max(10, count($signatures));
            while ($num <= $minRows):
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
            <?php endwhile; ?>
        </tbody>
    </table>

    <!-- Pied de page -->
    <div class="footer-section">
        <table class="footer-table">
            <tr>
                <td class="total">Total participants : <?= count($signatures) ?></td>
                <td align="right">Document généré le : <?= date('d/m/Y à H:i') ?></td>
            </tr>
        </table>
    </div>
</body>
</html>
