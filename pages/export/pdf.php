<?php
/**
 * e-Présence MEPC - Export PDF (A4 Paysage)
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

// Chemin du logo MEPC (base64 pour inclusion dans HTML)
$logoMepcPath = __DIR__ . '/../../assets/img/' . LOGO_MEPC;
$logoMepcBase64 = '';

if (file_exists($logoMepcPath)) {
    $logoMepcBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoMepcPath));
}

// Fallback: afficher HTML pour impression
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Feuille d'émargement - <?= htmlspecialchars($sheet['title']) ?></title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm;
        }
        @media print {
            .no-print { display: none !important; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.3;
            color: #333;
            max-width: 297mm;
            margin: 0 auto;
            padding: 5mm;
        }
        .no-print {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 1000;
        }
        .no-print button {
            padding: 10px 20px;
            font-size: 14px;
            cursor: pointer;
            background: #00703c;
            color: white;
            border: none;
            border-radius: 5px;
            margin-right: 10px;
        }
        .no-print button:hover {
            background: #005a30;
        }

        /* Header with logo left and centered title */
        .header {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 3px solid #00703c;
        }
        .header-logo {
            flex-shrink: 0;
        }
        .header-logo img {
            max-height: 110px;
        }
        .header-content {
            flex: 1;
            text-align: center;
        }
        .header-content .platform-name {
            font-size: 11px;
            color: #00703c;
            font-weight: 600;
            margin-bottom: 3px;
        }
        .header-content h1 {
            font-size: 18px;
            margin: 0 0 5px 0;
            color: #00703c;
        }
        .header-content h2 {
            font-size: 14px;
            margin: 0;
            font-weight: normal;
            color: #333;
        }

        /* Event Info */
        .event-info {
            background-color: #f5f5f5;
            padding: 5px 15px;
            border-radius: 3px;
            margin-bottom: 6px;
            display: flex;
            justify-content: center;
            gap: 40px;
            font-size: 11px;
        }
        .event-info strong {
            color: #00703c;
        }

        /* Description */
        .event-description {
            padding: 5px 15px;
            margin-bottom: 6px;
            font-size: 10px;
            font-style: italic;
            color: #555;
            background-color: #fafafa;
            border-left: 3px solid #00703c;
        }
        .event-description strong {
            color: #00703c;
            font-style: normal;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        th {
            background-color: #00703c;
            color: white;
            padding: 5px 3px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #005a30;
        }
        td {
            padding: 4px 3px;
            border: 1px solid #ccc;
            vertical-align: middle;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .signature-img {
            max-width: 100px;
            height: 30px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }
        .empty-row td {
            height: 30px;
        }
        .col-num { width: 20px; text-align: center; }
        .col-signature { text-align: center; width: 110px; }

        /* Day header for multi-day events */
        .day-header {
            background-color: #e8f5e9;
            padding: 6px 12px;
            margin: 10px 0 6px 0;
            border-left: 4px solid #00703c;
            font-size: 12px;
            color: #00703c;
        }
        .day-header .day-count {
            font-weight: normal;
            color: #666;
            margin-left: 10px;
        }
        .day-header.new-page {
            page-break-before: always;
            break-before: page;
        }

        /* Footer */
        .footer {
            margin-top: 6px;
            padding-top: 5px;
            border-top: 2px solid #00703c;
            font-size: 10px;
            color: #666;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">
            Imprimer / Enregistrer en PDF
        </button>
        <button onclick="window.close()" style="background: #6c757d;">
            Fermer
        </button>
    </div>

    <!-- Header with logo left and centered title -->
    <div class="header">
        <div class="header-logo">
            <?php if ($logoMepcBase64): ?>
                <img src="<?= $logoMepcBase64 ?>" alt="Logo MEPC">
            <?php endif; ?>
        </div>
        <div class="header-content">
            <div class="platform-name">Plateforme d'Émargement Électronique (e-Présence)</div>
            <h1>FEUILLE D'ÉMARGEMENT</h1>
            <h2><?= htmlspecialchars($sheet['title']) ?></h2>
        </div>
    </div>

    <!-- Event Info -->
    <div class="event-info">
        <?php if ($isMultiDay): ?>
            <span><strong>Période :</strong> Du <?= formatDateFr($sheet['event_date']) ?> au <?= formatDateFr($sheet['end_date']) ?></span>
        <?php else: ?>
            <span><strong>Date :</strong> <?= formatDateFr($sheet['event_date']) ?></span>
        <?php endif; ?>
        <?php if ($sheet['event_time']): ?>
            <span><strong>Heure :</strong> <?= formatTime($sheet['event_time']) ?></span>
        <?php endif; ?>
        <?php if ($sheet['location']): ?>
            <span><strong>Lieu :</strong> <?= htmlspecialchars($sheet['location']) ?></span>
        <?php endif; ?>
    </div>

    <?php if (!empty($sheet['description'])): ?>
    <!-- Description -->
    <div class="event-description">
        <strong>Description :</strong> <?= htmlspecialchars($sheet['description']) ?>
    </div>
    <?php endif; ?>

    <!-- Tables par jour -->
    <?php
    $displayedDayIndex = 0;
    foreach ($eventDays as $day):
        $daySignatures = $signaturesByDay[$day];
        // Ignorer les jours sans signatures
        if (empty($daySignatures)) continue;
    ?>

        <?php if ($isMultiDay): ?>
        <!-- Titre du jour -->
        <div class="day-header<?= $displayedDayIndex > 0 ? ' new-page' : '' ?>">
            <strong><?= formatDateFr($day) ?></strong>
            <span class="day-count">(<?= count($daySignatures) ?> participant<?= count($daySignatures) > 1 ? 's' : '' ?>)</span>
        </div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th class="col-num">N°</th>
                    <th>Prénom</th>
                    <th>Nom</th>
                    <th>Structure</th>
                    <th>Fonction</th>
                    <th>Téléphone</th>
                    <th>Email</th>
                    <th class="col-signature">Signature</th>
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
                    <td><?= htmlspecialchars(formatPhone($sig['phone'])) ?><?php if (!empty($sig['phone_secondary'])): ?><br><small><?= htmlspecialchars(formatPhone($sig['phone_secondary'])) ?></small><?php endif; ?></td>
                    <td><?= htmlspecialchars($sig['email']) ?></td>
                    <td class="col-signature"><img src="<?= $sig['signature_data'] ?>" class="signature-img" /></td>
                </tr>
                <?php endforeach; ?>

                <?php
                // Ajouter seulement 3 lignes vides supplémentaires (optimisation papier)
                $emptyRows = min(3, 10 - count($daySignatures));
                if ($emptyRows < 0) $emptyRows = 0;
                for ($i = 0; $i < $emptyRows; $i++):
                ?>
                <tr class="empty-row">
                    <td class="col-num"><?= $num++ ?></td>
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
    <?php $displayedDayIndex++; endforeach; ?>

    <!-- Footer -->
    <div class="footer">
        <?php if ($isMultiDay): ?>
            <span><strong>Total signatures :</strong> <?= count($signatures) ?> (sur <?= count($eventDays) ?> jours)</span>
        <?php else: ?>
            <span><strong>Total participants :</strong> <?= count($signatures) ?></span>
        <?php endif; ?>
        <span><strong>Document généré le :</strong> <?= date('d/m/Y à H:i') ?></span>
    </div>
    <div style="text-align: center; margin-top: 10px; font-size: 9px; color: #999;">
        © <?= date('Y') ?> e-Présence - Plateforme d'Émargement Électronique
    </div>
</body>
</html>
