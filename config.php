<?php
// config.php - Application Configuration

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'assessment_platform');
define('APP_NAME', 'AssessPro');
define('APP_URL', 'https://german-reuseable-psychiatrically.ngrok-free.dev/Assessment%20Platform%20PHP');
define('SESSION_LIFETIME', 7200); // 2 hours

// Database connection (PDO)
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=127.0.0.1;port=3307;dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER, DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                    PDO::MYSQL_ATTR_DIRECT_QUERY => false,
                ]
            );
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth helpers
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function isCandidateLoggedIn() {
    return isset($_SESSION['candidate_session_id']);
}

function requireAdmin() {
    if (!isAdminLoggedIn()) {
        header('Location: ' . APP_URL . '/admin/login.php');
        exit;
    }
}

function currentAdmin() {
    if (!isAdminLoggedIn()) return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT a.*, c.name as company_name FROM admins a LEFT JOIN companies c ON a.company_id = c.id WHERE a.id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch();
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->d > 0) return $diff->d . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'just now';
}
?>
