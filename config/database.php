<?php
/**
 * e-Présence - Configuration et connexion à la base de données PostgreSQL
 */

// Configuration PostgreSQL via DATABASE_URL (Render)
$databaseUrl = getenv('DATABASE_URL');

if ($databaseUrl) {
    // PostgreSQL sur Render (format: postgres://user:pass@host:port/dbname)
    $parsedUrl = parse_url($databaseUrl);
    define('DB_TYPE', 'pgsql');
    define('DB_HOST', $parsedUrl['host']);
    define('DB_PORT', isset($parsedUrl['port']) ? $parsedUrl['port'] : '5432');
    define('DB_NAME', ltrim($parsedUrl['path'], '/'));
    define('DB_USER', $parsedUrl['user']);
    define('DB_PASS', $parsedUrl['pass']);
} else {
    // Configuration locale par défaut (développement)
    define('DB_TYPE', 'pgsql');
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_PORT', getenv('DB_PORT') ?: '5432');
    define('DB_NAME', getenv('DB_NAME') ?: 'epresence');
    define('DB_USER', getenv('DB_USER') ?: 'postgres');
    define('DB_PASS', getenv('DB_PASS') ?: '');
}

/**
 * Classe de connexion à la base de données (Singleton)
 */
class Database {
    private static $instance = null;

    /**
     * Obtenir l'instance de connexion PDO
     */
    public static function getInstance() {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    "pgsql:host=%s;port=%s;dbname=%s",
                    DB_HOST,
                    DB_PORT,
                    DB_NAME
                );

                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);

            } catch (PDOException $e) {
                if (defined('DEBUG_MODE') && DEBUG_MODE) {
                    die("Erreur de connexion à la base de données : " . $e->getMessage());
                } else {
                    die("Erreur de connexion à la base de données. Veuillez réessayer plus tard.");
                }
            }
        }

        return self::$instance;
    }

    /**
     * Empêcher le clonage
     */
    private function __clone() {}

    /**
     * Empêcher la désérialisation
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Fonction raccourcie pour obtenir la connexion
 */
function db() {
    return Database::getInstance();
}
