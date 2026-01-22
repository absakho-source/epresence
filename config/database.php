<?php
/**
 * e-Présence - Configuration et connexion à la base de données MySQL (PRODUCTION)
 */

// Configuration de la base de données MySQL
// Utilise les variables d'environnement (Render) ou les valeurs par défaut (Sonatel)
define('DB_HOST', getenv('DB_HOST') ?: 'sql5c50c.megasqlservers.eu');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'myapp_dgppesn461570470');
define('DB_USER', getenv('DB_USER') ?: 'dgppesn461570470');
define('DB_PASS', getenv('DB_PASS') ?: 'Dgppe@pp2026');

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
                    "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
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
