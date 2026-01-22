<?php
/**
 * e-Présence DGPPE - Export PDF (A4 Paysage)
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

// Chemins des logos (base64 pour inclusion dans HTML)
$logoDgppePath = __DIR__ . '/../../assets/img/' . LOGO_DGPPE;
$logoMepcPath = __DIR__ . '/../../assets/img/' . LOGO_MEPC;

$logoDgppeBase64 = '';
$logoMepcBase64 = '';

if (file_exists($logoDgppePath)) {
    $logoDgppeBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoDgppePath));
}
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
            margin: 10mm;
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
            padding: 8mm;
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

        /* Ministry Header */
        .ministry-header {
            text-align: center;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #ccc;
        }
        .ministry-header .country {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 2px;
        }
        .ministry-header .motto {
            font-size: 9px;
            font-style: italic;
            color: #666;
            margin-bottom: 5px;
        }
        .ministry-header .ministry {
            font-size: 12px;
            font-weight: bold;
            color: #00703c;
            margin-bottom: 2px;
        }
        .ministry-header .org {
            font-size: 10px;
            color: #333;
        }

        /* Header with logos */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 3px solid #00703c;
        }
        .header-logo {
            width: 80px;
        }
        .header-logo img {
            max-height: 60px;
            max-width: 80px;
        }
        .header-center {
            text-align: center;
            flex: 1;
        }
        .header-center h1 {
            font-size: 18px;
            margin: 0 0 5px 0;
            color: #00703c;
        }
        .header-center h2 {
            font-size: 14px;
            margin: 0;
            font-weight: normal;
            color: #333;
        }

        /* Event Info */
        .event-info {
            background-color: #f5f5f5;
            padding: 8px 15px;
            border-radius: 5px;
            margin-bottom: 10px;
            display: flex;
            justify-content: center;
            gap: 40px;
            font-size: 11px;
        }
        .event-info strong {
            color: #00703c;
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
            padding: 8px 4px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #005a30;
        }
        td {
            padding: 5px 4px;
            border: 1px solid #ccc;
            vertical-align: middle;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .signature-img {
            max-width: 100px;
            height: 35px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }
        .empty-row td {
            height: 35px;
        }
        .col-num { width: 20px; text-align: center; }
        .col-signature { text-align: center; width: 110px; }

        /* Footer */
        .footer {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 2px solid #00703c;
            font-size: 10px;
            color: #666;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .footer-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .footer-left img {
            height: 30px;
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

    <!-- Ministry Header -->
    <div class="ministry-header">
        <div class="country">République du Sénégal</div>
        <div class="motto">Un Peuple - Un But - Une Foi</div>
        <div class="ministry"><?= MINISTRY_NAME ?></div>
        <div class="org"><?= ORG_FULL_NAME ?></div>
    </div>

    <!-- Header with logo -->
    <div class="header">
        <div class="header-logo">
            <?php if ($logoDgppeBase64): ?>
                <img src="<?= $logoDgppeBase64 ?>" alt="Logo DGPPE">
            <?php endif; ?>
        </div>
        <div class="header-center">
            <h1>FEUILLE D'ÉMARGEMENT</h1>
            <h2><?= htmlspecialchars($sheet['title']) ?></h2>
        </div>
        <div class="header-logo" style="text-align: right;">
            <?php if ($logoDgppeBase64): ?>
                <img src="<?= $logoDgppeBase64 ?>" alt="Logo DGPPE">
            <?php endif; ?>
        </div>
    </div>

    <!-- Event Info -->
    <div class="event-info">
        <span><strong>Date :</strong> <?= formatDateFr($sheet['event_date']) ?></span>
        <?php if ($sheet['event_time']): ?>
            <span><strong>Heure :</strong> <?= formatTime($sheet['event_time']) ?></span>
        <?php endif; ?>
        <?php if ($sheet['location']): ?>
            <span><strong>Lieu :</strong> <?= htmlspecialchars($sheet['location']) ?></span>
        <?php endif; ?>
    </div>

    <!-- Table -->
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
            foreach ($signatures as $sig):
            ?>
            <tr>
                <td class="col-num"><?= $num++ ?></td>
                <td><?= htmlspecialchars($sig['first_name']) ?></td>
                <td><?= htmlspecialchars($sig['last_name']) ?></td>
                <td><?= htmlspecialchars($sig['structure'] ?? '-') ?></td>
                <td><?= htmlspecialchars($sig['function_title'] ?? '-') ?></td>
                <td><?= htmlspecialchars(formatPhone($sig['phone'])) ?></td>
                <td><?= htmlspecialchars($sig['email']) ?></td>
                <td class="col-signature"><img src="<?= $sig['signature_data'] ?>" class="signature-img" /></td>
            </tr>
            <?php endforeach; ?>

            <?php
            // Lignes vides pour atteindre au moins 10 lignes
            $minRows = max(10, count($signatures));
            while ($num <= $minRows):
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
            <?php endwhile; ?>
        </tbody>
    </table>

    <!-- Footer -->
    <div class="footer">
        <div class="footer-left">
            <?php if ($logoDgppeBase64): ?>
                <img src="<?= $logoDgppeBase64 ?>" alt="DGPPE">
            <?php endif; ?>
            <span><strong>Total participants :</strong> <?= count($signatures) ?></span>
        </div>
        <span><strong>Document généré le :</strong> <?= date('d/m/Y à H:i') ?></span>
    </div>
</body>
</html>
