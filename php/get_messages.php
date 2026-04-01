<?php
require_once '../config.php';

$session_id = (int)($_GET['session_id'] ?? 0);
$viewer = $_GET['viewer'] ?? 'candidate';
$admin_id = (int)($_GET['admin_id'] ?? 0);

if (!$session_id) jsonResponse(['error' => 'Invalid session']);

$db = getDB();

// Mark messages as read
if ($viewer === 'admin' && $admin_id) {
    $db->prepare("UPDATE chat_messages SET is_read=1 WHERE session_id=? AND sender_type='candidate'")->execute([$session_id]);
} elseif ($viewer === 'candidate') {
    $db->prepare("UPDATE chat_messages SET is_read=1 WHERE session_id=? AND sender_type='admin'")->execute([$session_id]);
}

$stmt = $db->prepare("SELECT * FROM chat_messages WHERE session_id=? ORDER BY sent_at ASC");
$stmt->execute([$session_id]);
$rows = $stmt->fetchAll();

$messages = array_map(function($m) {
    return [
        'id' => $m['id'],
        'sender_type' => $m['sender_type'],
        'message' => $m['message'],
        'is_read' => $m['is_read'],
        'time' => date('h:i A', strtotime($m['sent_at'])),
        'date' => date('M j, Y', strtotime($m['sent_at'])),
    ];
}, $rows);

// Unread count from admin side (messages to candidate)
$unread = $db->prepare("SELECT COUNT(*) FROM chat_messages WHERE session_id=? AND sender_type='admin' AND is_read=0");
$unread->execute([$session_id]);

jsonResponse(['messages' => $messages, 'unread_from_admin' => $unread->fetchColumn()]);
?>
