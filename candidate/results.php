<?php
require_once '../config.php';

$token = sanitize($_GET['token'] ?? '');
if (!$token) die('Invalid link.');

$db = getDB();
$stmt = $db->prepare("SELECT ts.*, a.title, a.pass_score, c.name as candidate_name, co.name as company_name
                      FROM test_sessions ts 
                      JOIN assessments a ON ts.assessment_id=a.id
                      JOIN candidates c ON ts.candidate_id=c.id
                      JOIN companies co ON a.company_id=co.id
                      WHERE ts.token=?");
$stmt->execute([$token]);
$session = $stmt->fetch();

if (!$session || $session['status'] !== 'completed') die('Results not available yet.');

$duration = '';
if ($session['start_time'] && $session['end_time']) {
    $secs = strtotime($session['end_time']) - strtotime($session['start_time']);
    $duration = floor($secs/60) . 'm ' . ($secs%60) . 's';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Test Results — AssessPro</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root { --bg:#0a0a0f;--surface:#12121a;--border:#1e1e2e;--accent:#6c63ff;--accent2:#ff6584;--text:#e8e8f0;--muted:#6b6b80;--success:#43e97b;--error:#ff6584; }
body { background:var(--bg);font-family:'DM Sans',sans-serif;color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:40px 20px; }
.card { background:var(--surface);border:1px solid var(--border);border-radius:24px;padding:52px 48px;width:100%;max-width:520px;text-align:center;animation:fadeUp .5s cubic-bezier(.16,1,.3,1); }
@keyframes fadeUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:none}}
.result-icon { font-size:72px;margin-bottom:20px;animation:bounce .6s .3s both; }
@keyframes bounce{0%{transform:scale(0)}70%{transform:scale(1.1)}100%{transform:scale(1)}}
h1 { font-family:'Syne',sans-serif;font-size:28px;font-weight:800;margin-bottom:8px; }
.subtitle { color:var(--muted);font-size:15px;margin-bottom:36px; }
/* Score circle */
.score-circle-wrap { display:flex;justify-content:center;margin-bottom:36px; }
.score-circle { position:relative;width:160px;height:160px; }
.score-circle svg { width:100%;height:100%;transform:rotate(-90deg); }
.score-circle circle { fill:none;stroke-width:12; }
.score-circle .bg-circle { stroke:var(--border); }
.score-circle .progress-circle { stroke:var(--success);stroke-linecap:round;transition:stroke-dashoffset 1s cubic-bezier(.16,1,.3,1);stroke-dasharray:408;stroke-dashoffset:408; }
.score-circle.fail .progress-circle { stroke:var(--error); }
.score-text { position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center; }
.score-pct { font-family:'Syne',sans-serif;font-size:36px;font-weight:800;line-height:1; }
.score-label { font-size:12px;color:var(--muted);margin-top:4px; }
/* Stats */
.stats-row { display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:32px; }
.stat { background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:14px;padding:16px; }
.stat-val { font-family:'Syne',sans-serif;font-size:24px;font-weight:700;margin-bottom:4px; }
.stat-lbl { font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em; }
.badge { display:inline-block;padding:8px 20px;border-radius:20px;font-family:'Syne',sans-serif;font-size:14px;font-weight:700;margin-bottom:28px; }
.badge.pass { background:rgba(67,233,123,.15);color:var(--success);border:1px solid rgba(67,233,123,.3); }
.badge.fail { background:rgba(255,101,132,.15);color:var(--error);border:1px solid rgba(255,101,132,.3); }
.company { font-size:13px;color:var(--muted);margin-bottom: 6px; }
.msg { font-size:14px;color:var(--muted);line-height:1.6; }
</style>
</head>
<body>
<div class="card">
  <div class="result-icon"><?= $session['passed'] ? '🎉' : '😔' ?></div>
  <h1>Test Completed!</h1>
  <p class="subtitle">Here are your results for <strong><?= sanitize($session['title']) ?></strong></p>
  
  <div class="score-circle-wrap">
    <div class="score-circle <?= $session['passed'] ? '' : 'fail' ?>" id="scoreCircle">
      <svg viewBox="0 0 140 140">
        <circle class="bg-circle" cx="70" cy="70" r="65"/>
        <circle class="progress-circle" cx="70" cy="70" r="65" id="progressCircle"/>
      </svg>
      <div class="score-text">
        <div class="score-pct"><?= $session['percentage'] ?>%</div>
        <div class="score-label">Score</div>
      </div>
    </div>
  </div>

  <span class="badge <?= $session['passed'] ? 'pass' : 'fail' ?>">
    <?= $session['passed'] ? '✅ PASSED' : '❌ NOT PASSED' ?>
  </span>

  <div class="stats-row">
    <div class="stat">
      <div class="stat-val"><?= $session['score'] ?></div>
      <div class="stat-lbl">Marks Scored</div>
    </div>
    <div class="stat">
      <div class="stat-val"><?= $session['total_marks'] ?></div>
      <div class="stat-lbl">Total Marks</div>
    </div>
    <div class="stat">
      <div class="stat-val"><?= $duration ?: '—' ?></div>
      <div class="stat-lbl">Time Taken</div>
    </div>
  </div>
  
  <div class="company">📋 Pass Score: <?= $session['pass_score'] ?>% · 🏢 <?= sanitize($session['company_name']) ?></div>
  <p class="msg"><?= $session['passed'] 
    ? 'Congratulations! You have successfully passed this assessment. The hiring team will be in touch with you shortly.'
    : 'Unfortunately you did not meet the minimum pass score. Thank you for your time and effort.' ?>
  </p>
</div>
<script>
// Animate score circle
window.addEventListener('load', function() {
  const pct = <?= $session['percentage'] ?>;
  const circumference = 408;
  const offset = circumference - (pct / 100 * circumference);
  setTimeout(() => {
    document.getElementById('progressCircle').style.strokeDashoffset = offset;
  }, 300);
});
</script>
</body>
</html>
