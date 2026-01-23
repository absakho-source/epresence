<?php
/**
 * e-Présence - Diagnostic du disque persistant
 */

require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireAdmin();

$pageTitle = 'Diagnostic Disque';
require_once __DIR__ . '/../../includes/header.php';

$persistentDisk = '/var/data/uploads';
$documentsDir = $persistentDisk . '/documents';
$localUploads = BASE_PATH . '/uploads';
$localDocuments = $localUploads . '/documents';

// Tests
$tests = [];

// Test 1: Le dossier /var/data existe ?
$tests[] = [
    'name' => '/var/data existe',
    'result' => is_dir('/var/data'),
    'value' => is_dir('/var/data') ? 'Oui' : 'Non'
];

// Test 2: Le dossier /var/data/uploads existe ?
$tests[] = [
    'name' => '/var/data/uploads existe',
    'result' => is_dir($persistentDisk),
    'value' => is_dir($persistentDisk) ? 'Oui' : 'Non'
];

// Test 3: /var/data/uploads est-il writable ?
$tests[] = [
    'name' => '/var/data/uploads est writable',
    'result' => is_writable($persistentDisk),
    'value' => is_writable($persistentDisk) ? 'Oui' : 'Non'
];

// Test 4: Peut-on créer un fichier test ?
$canCreateFile = false;
$testFile = $persistentDisk . '/test_' . time() . '.txt';
if (is_dir($persistentDisk)) {
    $canCreateFile = @file_put_contents($testFile, 'test') !== false;
    if ($canCreateFile) {
        @unlink($testFile);
    }
}
$tests[] = [
    'name' => 'Création fichier dans /var/data/uploads',
    'result' => $canCreateFile,
    'value' => $canCreateFile ? 'Oui' : 'Non (Permission refusée)'
];

// Test 5: Le dossier documents existe ?
$tests[] = [
    'name' => '/var/data/uploads/documents existe',
    'result' => is_dir($documentsDir),
    'value' => is_dir($documentsDir) ? 'Oui' : 'Non'
];

// Test 6: Peut-on créer le dossier documents ?
$canCreateDir = false;
if (!is_dir($documentsDir) && is_dir($persistentDisk)) {
    $canCreateDir = @mkdir($documentsDir, 0755, true);
}
$tests[] = [
    'name' => 'Création /var/data/uploads/documents',
    'result' => is_dir($documentsDir) || $canCreateDir,
    'value' => is_dir($documentsDir) ? 'Existe déjà' : ($canCreateDir ? 'Créé avec succès' : 'Échec (Permission refusée)')
];

// Test 7: Constantes actuelles
$tests[] = [
    'name' => 'UPLOADS_PATH actuel',
    'result' => true,
    'value' => UPLOADS_PATH
];

$tests[] = [
    'name' => 'DOCUMENTS_PATH actuel',
    'result' => true,
    'value' => DOCUMENTS_PATH
];

$tests[] = [
    'name' => 'DOCUMENTS_PATH existe',
    'result' => is_dir(DOCUMENTS_PATH),
    'value' => is_dir(DOCUMENTS_PATH) ? 'Oui' : 'Non'
];

$tests[] = [
    'name' => 'DOCUMENTS_PATH writable',
    'result' => is_writable(DOCUMENTS_PATH),
    'value' => is_writable(DOCUMENTS_PATH) ? 'Oui' : 'Non'
];

// Lister le contenu de /var/data
$varDataContent = [];
if (is_dir('/var/data')) {
    $varDataContent = @scandir('/var/data') ?: [];
    $varDataContent = array_diff($varDataContent, ['.', '..']);
}

// Lister le contenu de /var/data/uploads
$uploadsContent = [];
if (is_dir($persistentDisk)) {
    $uploadsContent = @scandir($persistentDisk) ?: [];
    $uploadsContent = array_diff($uploadsContent, ['.', '..']);
}
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center mb-4">
            <a href="<?= SITE_URL ?>/pages/admin/migrate.php" class="btn btn-outline-secondary me-3">
                <i class="bi bi-arrow-left"></i>
            </a>
            <h1 class="h3 mb-0"><i class="bi bi-hdd me-2"></i>Diagnostic Disque</h1>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-check2-all me-2"></i>Tests de Diagnostic</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Test</th>
                            <th>Résultat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tests as $test): ?>
                            <tr>
                                <td><?= sanitize($test['name']) ?></td>
                                <td>
                                    <?php if ($test['result']): ?>
                                        <span class="text-success"><i class="bi bi-check-circle me-1"></i></span>
                                    <?php else: ?>
                                        <span class="text-danger"><i class="bi bi-x-circle me-1"></i></span>
                                    <?php endif; ?>
                                    <code><?= sanitize($test['value']) ?></code>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-folder me-2"></i>Contenu de /var/data</h5>
            </div>
            <div class="card-body">
                <?php if (empty($varDataContent)): ?>
                    <p class="text-muted mb-0">Vide ou inaccessible</p>
                <?php else: ?>
                    <ul class="mb-0">
                        <?php foreach ($varDataContent as $item): ?>
                            <li><code><?= sanitize($item) ?></code></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-folder me-2"></i>Contenu de /var/data/uploads</h5>
            </div>
            <div class="card-body">
                <?php if (empty($uploadsContent)): ?>
                    <p class="text-muted mb-0">Vide ou inaccessible</p>
                <?php else: ?>
                    <ul class="mb-0">
                        <?php foreach ($uploadsContent as $item): ?>
                            <li><code><?= sanitize($item) ?></code></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Sur Render:</strong> Vérifiez que le disque persistant est configuré avec le chemin de montage <code>/var/data/uploads</code>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
