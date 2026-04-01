<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['error' => 'Invalid request']));
}

$data = json_decode(file_get_contents('php://input'), true);
$sessionId = $data['session_id'] ?? ($_SESSION['candidate_session_id'] ?? null);
$violations = $data['violations'] ?? [];
$severity = $data['severity'] ?? 'low';
$frameData = $data['frame'] ?? null;

if (!$sessionId || empty($violations)) {
    die(json_encode(['error' => 'Missing session_id or violations', 'received' => $data]));
}

$db = getDB();
$lastId = null;

foreach ($violations as $violation) {
    try {
        $stmt = $db->prepare("INSERT INTO proctoring_violations 
                            (session_id, violation_type, severity, frame_data, timestamp) 
                            VALUES (?,?,?,?,NOW())");
        $stmt->execute([
            $sessionId,
            $violation,
            $severity,
            $frameData ? $frameData : null  // Already base64 encoded from frontend
        ]);
        $lastId = $db->lastInsertId();
    } catch (PDOException $e) {
        error_log("Proctoring log error: " . $e->getMessage());
        die(json_encode(['error' => $e->getMessage()]));
    }
}

echo json_encode(['status' => 'logged', 'count' => count($violations), 'id' => $lastId]);
?>
