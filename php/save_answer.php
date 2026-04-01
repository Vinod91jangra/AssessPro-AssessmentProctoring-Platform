<?php
// save_answer.php
require_once '../config.php';

$input = json_decode(file_get_contents('php://input'), true);
$session_id = (int)($input['session_id'] ?? 0);
$question_id = (int)($input['question_id'] ?? 0);
$answer = trim($input['answer'] ?? '');

if (!$session_id || !$question_id) jsonResponse(['success' => false, 'error' => 'Invalid input']);

$db = getDB();

// Get correct answer and marks
$q = $db->prepare("SELECT correct_answer, marks, question_type FROM questions WHERE id=?");
$q->execute([$question_id]);
$q = $q->fetch();
if (!$q) jsonResponse(['success' => false, 'error' => 'Question not found']);

$isCorrect = 0;
$marksObtained = 0;

if ($q['question_type'] !== 'short_answer') {
    $isCorrect = strtolower(trim($answer)) === strtolower(trim($q['correct_answer'])) ? 1 : 0;
    $marksObtained = $isCorrect ? $q['marks'] : 0;
}

// Upsert answer
$existing = $db->prepare("SELECT id FROM candidate_answers WHERE session_id=? AND question_id=?");
$existing->execute([$session_id, $question_id]);

if ($existing->fetch()) {
    $db->prepare("UPDATE candidate_answers SET answer=?, is_correct=?, marks_obtained=? WHERE session_id=? AND question_id=?")
       ->execute([$answer, $isCorrect, $marksObtained, $session_id, $question_id]);
} else {
    $db->prepare("INSERT INTO candidate_answers (session_id, question_id, answer, is_correct, marks_obtained) VALUES (?,?,?,?,?)")
       ->execute([$session_id, $question_id, $answer, $isCorrect, $marksObtained]);
}

jsonResponse(['success' => true]);
?>
