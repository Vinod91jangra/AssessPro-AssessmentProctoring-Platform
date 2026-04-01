<?php
// log_flag.php
require_once '../config.php';
$input = json_decode(file_get_contents('php://input'), true);
$session_id = (int)($input['session_id'] ?? 0);
$flag_type = sanitize($input['flag_type'] ?? '');
$description = sanitize($input['description'] ?? '');
if (!$session_id || !$flag_type) jsonResponse(['success' => false]);
$db = getDB();
$db->prepare("INSERT INTO proctoring_flags (session_id, flag_type, description) VALUES (?,?,?)")->execute([$session_id, $flag_type, $description]);
jsonResponse(['success' => true]);
?>
