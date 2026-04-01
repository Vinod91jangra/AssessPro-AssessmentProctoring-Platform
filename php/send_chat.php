<?php
require_once '../config.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$session_id = (int)($input['session_id'] ?? 0);
$message = trim($input['message'] ?? '');
$sender_type = $input['sender_type'] ?? '';
$sender_id = (int)($input['sender_id'] ?? 0);

if (!$session_id || !$message || !in_array($sender_type, ['candidate','admin']) || !$sender_id) {
    jsonResponse(['success' => false, 'error' => 'Invalid input']);
}

// Validate session access
$db = getDB();
$session = $db->prepare("SELECT ts.*, a.company_id FROM test_sessions ts JOIN assessments a ON ts.assessment_id = a.id WHERE ts.id = ?");
$session->execute([$session_id]);
$session = $session->fetch();

if (!$session) {
    jsonResponse(['success' => false, 'error' => 'Session not found']);
}

// Validate sender
if ($sender_type === 'candidate') {
    // Check candidate owns session
    $cand = $db->prepare("SELECT * FROM test_sessions WHERE id = ? AND candidate_id = ?");
    $cand->execute([$session_id, $sender_id]);
    if (!$cand->fetch()) {
        jsonResponse(['success' => false, 'error' => 'Unauthorized']);
    }
} else {
    // Admin - verify they belong to the right company
    if (!isAdminLoggedIn() && !$sender_id) {
        jsonResponse(['success' => false, 'error' => 'Unauthorized']);
    }
}

// Insert message
$stmt = $db->prepare("INSERT INTO chat_messages (session_id, sender_type, sender_id, message) VALUES (?, ?, ?, ?)");
$stmt->execute([$session_id, $sender_type, $sender_id, $message]);

jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
?>
