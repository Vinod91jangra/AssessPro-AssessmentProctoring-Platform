<?php
// submit_test.php
require_once '../config.php';

$input = json_decode(file_get_contents('php://input'), true);
$session_id = (int)($input['session_id'] ?? 0);

if (!$session_id) jsonResponse(['success' => false]);

$db = getDB();

// Calculate score
$answers = $db->prepare("SELECT SUM(marks_obtained) as total_obtained FROM candidate_answers WHERE session_id=?");
$answers->execute([$session_id]);
$score = (int)$answers->fetchColumn();

// Get total possible marks
$total = $db->prepare("SELECT SUM(q.marks) FROM questions q JOIN test_sessions ts ON ts.assessment_id=q.assessment_id WHERE ts.id=?");
$total->execute([$session_id]);
$totalMarks = (int)$total->fetchColumn();

// Get pass score
$passScore = $db->prepare("SELECT a.pass_score FROM assessments a JOIN test_sessions ts ON ts.assessment_id=a.id WHERE ts.id=?");
$passScore->execute([$session_id]);
$pass = (int)$passScore->fetchColumn();

$percentage = $totalMarks > 0 ? round(($score / $totalMarks) * 100, 2) : 0;
$passed = $percentage >= $pass ? 1 : 0;

$db->prepare("UPDATE test_sessions SET status='completed', end_time=NOW(), score=?, total_marks=?, percentage=?, passed=? WHERE id=?")
   ->execute([$score, $totalMarks, $percentage, $passed, $session_id]);

jsonResponse(['success' => true, 'score' => $score, 'total' => $totalMarks, 'percentage' => $percentage, 'passed' => $passed]);
?>
