<?php
/**
 * e-Présence - Document imprimable avec QR Code
 * Format A4 optimisé pour l'impression
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/structures.php';
requireLogin();

$sheetId = intval($_GET['id'] ?? 0);

// Récupérer la feuille
$stmt = db()->prepare("SELECT s.*, u.first_name as creator_first_name, u.last_name as creator_last_name, u.structure as creator_structure
                       FROM sheets s
                       JOIN users u ON s.user_id = u.id
                       WHERE s.id = ?");
$stmt->execute([$sheetId]);
$sheet = $stmt->fetch();

// Vérifier l'accès (propriétaire ou admin)
if (!$sheet) {
    setFlash('error', 'Feuille non trouvée.');
    redirect(SITE_URL . '/pages/dashboard/index.php');
}

// Vérifier que l'utilisateur est propriétaire ou admin
if ($sheet['user_id'] != getCurrentUserId() && !isAdmin()) {
    setFlash('error', 'Accès non autorisé.');
    redirect(SITE_URL . '/pages/dashboard/index.php');
}

$signUrl = getSheetUrl($sheet['unique_code']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code - <?= sanitize($sheet['title']) ?></title>
    <link rel="icon" type="image/x-icon" href="<?= SITE_URL ?>/favicon.ico">
    <link rel="icon" type="image/png" href="<?= SITE_URL ?>/assets/img/<?= LOGO_MEPC ?>">
    <link rel="apple-touch-icon" href="<?= SITE_URL ?>/assets/img/<?= LOGO_MEPC ?>">
    <link rel="shortcut icon" href="<?= SITE_URL ?>/favicon.ico">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #333;
            background: #f5f5f5;
        }

        .print-container {
            width: 210mm;
            max-height: 297mm;
            margin: 0 auto;
            background: white;
            padding: 8mm 12mm;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        /* En-tête officiel */
        .official-header {
            display: flex;
            align-items: center;
            padding-bottom: 8px;
            border-bottom: 2px solid #00703c;
            margin-bottom: 10px;
        }

        .official-header .logo {
            height: 100px;
            flex-shrink: 0;
        }

        .official-header .header-content {
            flex: 1;
            text-align: center;
        }

        .official-header .platform {
            font-size: 14px;
            color: #00703c;
            font-weight: 700;
        }

        /* Titre de la réunion */
        .meeting-title {
            text-align: center;
            margin: 10px 0;
        }

        .meeting-title h1 {
            font-size: 18px;
            color: #00703c;
            margin-bottom: 3px;
            text-transform: uppercase;
        }

        .meeting-title .subtitle {
            font-size: 11px;
            color: #666;
        }

        /* Section QR Code */
        .qr-section {
            text-align: center;
            margin: 12px 0;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 2px dashed #00703c;
        }

        .qr-section h2 {
            color: #00703c;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .qr-code-box {
            display: inline-block;
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 2px solid #00703c;
        }

        .qr-code-box canvas,
        .qr-code-box img {
            display: block;
        }

        .scan-instruction {
            margin-top: 10px;
            font-size: 12px;
            color: #333;
        }

        .scan-instruction i {
            display: inline;
            font-size: 16px;
            color: #00703c;
            margin-right: 5px;
        }

        /* Informations de la réunion */
        .meeting-info {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px 12px;
            margin: 12px 0;
        }

        .meeting-info h3 {
            font-size: 11px;
            color: #00703c;
            text-transform: uppercase;
            margin-bottom: 8px;
            border-bottom: 1px solid #00703c;
            padding-bottom: 4px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 8px;
        }

        .info-item {
            padding: 6px 8px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .info-item .label {
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .info-item .value {
            font-size: 11px;
            color: #333;
            font-weight: 600;
        }

        .info-item.full-width {
            grid-column: 1 / -1;
        }

        /* URL alternative */
        .url-section {
            text-align: center;
            margin: 10px 0;
            padding: 8px;
            background: #e8f5e9;
            border-radius: 6px;
        }

        .url-section .label {
            font-size: 10px;
            color: #666;
            margin-bottom: 3px;
        }

        .url-section .url {
            font-family: monospace;
            font-size: 9px;
            color: #00703c;
            word-break: break-all;
            background: white;
            padding: 5px 10px;
            border-radius: 4px;
            display: inline-block;
            border: 1px solid #00703c;
        }

        /* Instructions */
        .instructions {
            margin: 10px 0;
            padding: 10px;
            background: #fff3cd;
            border-radius: 6px;
            border-left: 3px solid #ffc107;
        }

        .instructions h4 {
            font-size: 11px;
            color: #856404;
            margin-bottom: 6px;
        }

        .instructions ol {
            font-size: 10px;
            color: #856404;
            padding-left: 18px;
            margin: 0;
            columns: 2;
            column-gap: 20px;
        }

        .instructions li {
            margin-bottom: 2px;
        }

        /* Pied de page */
        .footer {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 9px;
            color: #999;
        }

        /* Boutons d'action (non imprimés) */
        .action-buttons {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }

        .action-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
        }

        .btn-print {
            background: #00703c;
            color: white;
        }

        .btn-back {
            background: #6c757d;
            color: white;
        }

        .btn-print:hover {
            background: #005a30;
        }

        .btn-back:hover {
            background: #5a6268;
        }

        /* Styles d'impression */
        @media print {
            body {
                background: white;
            }

            .print-container {
                box-shadow: none;
                padding: 5mm 8mm;
                width: 100%;
                max-height: none;
            }

            .action-buttons {
                display: none !important;
            }

            .qr-section {
                break-inside: avoid;
            }
        }

        @page {
            size: A4;
            margin: 5mm;
        }
    </style>
</head>
<body>
    <!-- Boutons d'action -->
    <div class="action-buttons">
        <button class="btn-print" onclick="window.print()">
            Imprimer
        </button>
        <button class="btn-back" onclick="window.history.back()">
            Retour
        </button>
    </div>

    <div class="print-container">
        <!-- En-tête officiel -->
        <div class="official-header">
            <img src="<?= SITE_URL ?>/assets/img/<?= LOGO_MEPC ?>" alt="Logo MEPC" class="logo">
            <div class="header-content">
                <div class="platform">Plateforme d'Émargement Électronique (e-Présence)</div>
            </div>
        </div>

        <!-- Titre de la réunion -->
        <div class="meeting-title">
            <h1><?= sanitize($sheet['title']) ?></h1>
            <div class="subtitle">Feuille d'émargement</div>
        </div>

        <!-- Section QR Code -->
        <div class="qr-section">
            <h2>Scannez pour signer</h2>
            <div class="qr-code-box">
                <div id="qrcode"></div>
            </div>
            <div class="scan-instruction">
                <i>Pointez votre appareil photo vers le QR code</i>
                Scannez ce QR code avec votre téléphone pour accéder au formulaire d'émargement
            </div>
        </div>

        <!-- Informations de la réunion -->
        <div class="meeting-info">
            <h3>Informations de la réunion</h3>
            <div class="info-grid">
                <div class="info-item">
                    <div class="label">Date</div>
                    <div class="value"><?= formatDateFr($sheet['event_date']) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Heure</div>
                    <div class="value"><?= $sheet['event_time'] ? formatTime($sheet['event_time']) : 'Non précisée' ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Lieu</div>
                    <div class="value"><?= $sheet['location'] ? sanitize($sheet['location']) : 'Non précisé' ?></div>
                </div>
                <div class="info-item">
                    <div class="label">Organisateur</div>
                    <div class="value"><?= $sheet['creator_structure'] ? sanitize(getStructureName($sheet['creator_structure'])) : sanitize($sheet['creator_first_name'] . ' ' . $sheet['creator_last_name']) ?></div>
                </div>
                <?php if ($sheet['description']): ?>
                <div class="info-item full-width">
                    <div class="label">Description</div>
                    <div class="value"><?= nl2br(sanitize($sheet['description'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- URL alternative -->
        <div class="url-section">
            <div class="label">Si le QR code ne fonctionne pas, saisissez cette adresse dans votre navigateur :</div>
            <div class="url"><?= sanitize($signUrl) ?></div>
        </div>

        <!-- Instructions -->
        <div class="instructions">
            <h4>Comment signer ?</h4>
            <ol>
                <li>Scannez le QR code avec l'appareil photo de votre téléphone</li>
                <li>Cliquez sur le lien qui apparaît</li>
                <li>Remplissez le formulaire avec vos informations</li>
                <li>Signez avec votre doigt dans la zone prévue</li>
                <li>Validez votre signature</li>
            </ol>
        </div>

        <!-- Pied de page -->
        <div class="footer">
            Document généré le <?= date('d/m/Y à H:i') ?> | Code unique : <?= $sheet['unique_code'] ?><br>
            <?= SITE_NAME ?> - <?= MINISTRY_NAME ?>
        </div>
    </div>

    <!-- QRCode.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        // Générer le QR code
        new QRCode(document.getElementById("qrcode"), {
            text: "<?= $signUrl ?>",
            width: 150,
            height: 150,
            colorDark: "#00703c",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
    </script>
</body>
</html>
