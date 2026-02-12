<?php
/**
 * e-Présence - Page de migration et maintenance (admin uniquement)
 */

require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireAdmin();

$user = getCurrentUser();
$messages = [];
$errors = [];

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = "Session expirée. Veuillez réessayer.";
    } else {
        $action = $_POST['action'] ?? '';

        // Migration password_resets
        if ($action === 'migrate_password_resets') {
            try {
                $pdo = db();
                $checkSql = "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'password_resets')";
                $stmt = $pdo->query($checkSql);
                $exists = $stmt->fetchColumn();

                if ($exists) {
                    $messages[] = "La table password_resets existe déjà.";
                } else {
                    $sql = "
                        CREATE TABLE IF NOT EXISTS password_resets (
                            id SERIAL PRIMARY KEY,
                            user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
                            token VARCHAR(64) UNIQUE NOT NULL,
                            expires_at TIMESTAMP NOT NULL,
                            used_at TIMESTAMP DEFAULT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        );
                    ";
                    $pdo->exec($sql);
                    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_password_resets_token ON password_resets(token);");
                    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_password_resets_user_id ON password_resets(user_id);");
                    $messages[] = "Table password_resets créée avec succès!";
                }
            } catch (PDOException $e) {
                $errors[] = "Erreur: " . $e->getMessage();
            }
        }

        // Nettoyage des documents orphelins
        if ($action === 'cleanup_orphan_documents') {
            try {
                $pdo = db();
                $deleted = 0;

                // Récupérer tous les documents
                $stmt = $pdo->query("SELECT id, stored_name FROM sheet_documents");
                $documents = $stmt->fetchAll();

                foreach ($documents as $doc) {
                    $filePath = DOCUMENTS_PATH . '/' . $doc['stored_name'];
                    if (!file_exists($filePath)) {
                        // Supprimer l'entrée de la base de données
                        $deleteStmt = $pdo->prepare("DELETE FROM sheet_documents WHERE id = ?");
                        $deleteStmt->execute([$doc['id']]);
                        $deleted++;
                    }
                }

                if ($deleted > 0) {
                    $messages[] = "$deleted document(s) orphelin(s) supprimé(s) de la base de données.";
                } else {
                    $messages[] = "Aucun document orphelin trouvé.";
                }
            } catch (PDOException $e) {
                $errors[] = "Erreur: " . $e->getMessage();
            }
        }
    }

    regenerateCsrfToken();
}

// Vérifier l'état des tables
$tableStatus = [];
$orphanCount = 0;
try {
    $pdo = db();

    // Liste des tables à vérifier
    $tables = ['users', 'sheets', 'signatures', 'sheet_documents', 'password_resets'];

    foreach ($tables as $table) {
        $checkSql = "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = ?)";
        $stmt = $pdo->prepare($checkSql);
        $stmt->execute([$table]);
        $tableStatus[$table] = (bool)$stmt->fetchColumn();
    }

    // Compter les documents orphelins
    if ($tableStatus['sheet_documents']) {
        $stmt = $pdo->query("SELECT id, stored_name FROM sheet_documents");
        $documents = $stmt->fetchAll();
        foreach ($documents as $doc) {
            $filePath = DOCUMENTS_PATH . '/' . $doc['stored_name'];
            if (!file_exists($filePath)) {
                $orphanCount++;
            }
        }
    }
} catch (PDOException $e) {
    $errors[] = "Erreur: " . $e->getMessage();
}

// Info disque persistant
$persistentDiskExists = is_dir('/var/data/uploads');
$documentsPath = DOCUMENTS_PATH;

$pageTitle = 'Maintenance';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex align-items-center mb-4">
            <a href="<?= SITE_URL ?>/pages/admin/index.php" class="btn btn-outline-secondary me-3">
                <i class="bi bi-arrow-left"></i>
            </a>
            <h1 class="h3 mb-0"><i class="bi bi-tools me-2"></i>Maintenance</h1>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= sanitize($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($messages)): ?>
            <div class="alert alert-success">
                <ul class="mb-0">
                    <?php foreach ($messages as $msg): ?>
                        <li><?= sanitize($msg) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Info Système -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informations Système</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr>
                        <td><strong>Disque persistant Render</strong></td>
                        <td>
                            <?php if ($persistentDiskExists): ?>
                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Monté</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i>Non détecté</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Chemin documents</strong></td>
                        <td><code><?= sanitize($documentsPath) ?></code></td>
                    </tr>
                    <tr>
                        <td><strong>Base de données</strong></td>
                        <td><code>PostgreSQL</code></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- État des tables -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-table me-2"></i>État des Tables</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>Table</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tableStatus as $table => $exists): ?>
                            <tr>
                                <td><code><?= sanitize($table) ?></code></td>
                                <td>
                                    <?php if ($exists): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>OK</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Manquante</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Migrations -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-database-gear me-2"></i>Migrations</h5>
            </div>
            <div class="card-body">
                <div class="border rounded p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">Table password_resets</h6>
                            <p class="text-muted small mb-0">Réinitialisation des mots de passe par email</p>
                        </div>
                        <form method="POST" action="">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="migrate_password_resets">
                            <?php if (isset($tableStatus['password_resets']) && $tableStatus['password_resets']): ?>
                                <button type="button" class="btn btn-outline-success btn-sm" disabled>
                                    <i class="bi bi-check me-1"></i>Installé
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="bi bi-play-fill me-1"></i>Installer
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Nettoyage -->
        <div class="card mb-4">
            <div class="card-header bg-warning bg-opacity-25">
                <h5 class="mb-0"><i class="bi bi-trash me-2"></i>Nettoyage</h5>
            </div>
            <div class="card-body">
                <div class="border rounded p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">Documents orphelins</h6>
                            <p class="text-muted small mb-0">
                                Supprime les entrées en BDD dont le fichier physique n'existe plus.
                                <?php if ($orphanCount > 0): ?>
                                    <br><span class="text-danger"><strong><?= $orphanCount ?> document(s) orphelin(s) détecté(s)</strong></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <form method="POST" action="">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="cleanup_orphan_documents">
                            <?php if ($orphanCount > 0): ?>
                                <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Supprimer <?= $orphanCount ?> entrée(s) orpheline(s) ?')">
                                    <i class="bi bi-trash me-1"></i>Nettoyer (<?= $orphanCount ?>)
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-outline-success btn-sm" disabled>
                                    <i class="bi bi-check me-1"></i>Aucun
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
