<?php
// admin/violations.php
require_once '../config.php';
requireAdmin();
$db = getDB();

// Get all violations
$violations = $db->query(
  "SELECT pv.*, c.name as candidate_name, c.email as candidate_email, 
          a.title as assessment_title, ts.token
   FROM proctoring_violations pv
   JOIN test_sessions ts ON pv.session_id = ts.id
   JOIN assessments a ON ts.assessment_id = a.id
   JOIN candidates c ON ts.candidate_id = c.id
   ORDER BY pv.timestamp DESC"
)->fetchAll();

// Group by session
$sessionViolations = [];
foreach ($violations as $v) {
  if (!isset($sessionViolations[$v['session_id']])) {
    $sessionViolations[$v['session_id']] = [
      'candidate' => $v['candidate_name'],
      'email' => $v['candidate_email'],
      'assessment' => $v['assessment_title'],
      'token' => $v['token'],
      'violations' => []
    ];
  }
  $sessionViolations[$v['session_id']]['violations'][] = $v;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Proctoring Violations — AssessPro</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg: #f4f4f9; --surface: #ffffff; --surface2: #f0f0f6;
  --border: #e2e2ee; --accent: #6c63ff; --text: #1a1a2e; --muted: #8080a0;
  --success: #27ae60; --warning: #f39c12; --error: #e74c3c;
}
body { background: var(--bg); font-family: 'DM Sans', sans-serif; color: var(--text); }

.container { max-width: 1200px; margin: 0 auto; padding: 32px 20px; }
.header { margin-bottom: 32px; }
.header h1 { font-size: 28px; font-weight: 700; margin-bottom: 8px; }
.header p { color: var(--muted); }

.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 32px; }
.stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 20px; }
.stat-number { font-size: 32px; font-weight: 700; color: var(--accent); }
.stat-label { font-size: 13px; color: var(--muted); margin-top: 8px; }

.violations-list { display: grid; gap: 16px; }
.violation-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 20px; }
.violation-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.violation-name { font-weight: 600; color: var(--text); }
.violation-email { font-size: 13px; color: var(--muted); margin-bottom: 4px; }
.violation-assessment { font-size: 13px; color: var(--text); }

.violation-badges { display: flex; flex-wrap: wrap; gap: 8px; margin: 12px 0; }
.badge { 
  display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;
}
.badge.high { background: rgba(231,76,60,.1); color: var(--error); }
.badge.medium { background: rgba(243,156,18,.1); color: var(--warning); }
.badge.low { background: rgba(39,174,96,.1); color: var(--success); }

.violation-items { border-top: 1px solid var(--border); padding-top: 12px; }
.violation-item { 
  display: flex; align-items: center; justify-content: space-between;
  padding: 8px; margin: 4px 0; background: var(--surface2); border-radius: 6px; font-size: 13px;
}
.violation-time { color: var(--muted); }

.btn { padding: 8px 16px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; }
.btn-primary { background: var(--accent); color: white; }
.btn-primary:hover { opacity: 0.9; }
</style>
</head>
<body>

<div class="container">
  <div class="header">
    <h1>⚠️ Proctoring Violations</h1>
    <p>Monitor suspicious activity detected during exams</p>
  </div>

  <div class="stats">
    <div class="stat-card">
      <div class="stat-number"><?= count($sessionViolations) ?></div>
      <div class="stat-label">Sessions with Violations</div>
    </div>
    <div class="stat-card">
      <div class="stat-number"><?= count($violations) ?></div>
      <div class="stat-label">Total Flags Recorded</div>
    </div>
    <div class="stat-card">
      <div class="stat-number"><?= count(array_filter($violations, fn($v) => $v['severity'] === 'high')) ?></div>
      <div class="stat-label">High Severity</div>
    </div>
  </div>

  <div class="violations-list">
    <?php if (empty($sessionViolations)): ?>
    <div class="violation-card">
      <p style="text-align: center; color: var(--muted);">✅ No violations detected</p>
    </div>
    <?php else: foreach ($sessionViolations as $sessionId => $data): ?>
    <div class="violation-card">
      <div class="violation-header">
        <div>
          <div class="violation-name">👤 <?= sanitize($data['candidate']) ?></div>
          <div class="violation-email"><?= sanitize($data['email']) ?></div>
          <div class="violation-assessment">📝 <?= sanitize($data['assessment']) ?></div>
        </div>
        <button class="btn btn-primary" onclick="window.open('chat.php', '_blank')">Review</button>
      </div>
      
      <div class="violation-badges">
        <?php 
        $severity = array_column($data['violations'], 'severity');
        if (in_array('high', $severity)): ?>
        <span class="badge high">🔴 HIGH PRIORITY</span>
        <?php endif; ?>
      </div>

      <div class="violation-items">
        <?php foreach ($data['violations'] as $v): ?>
        <div class="violation-item">
          <div>
            <strong><?= sanitize($v['violation_type']) ?></strong>
            <span class="badge <?= $v['severity'] ?>"><?= strtoupper($v['severity']) ?></span>
          </div>
          <div class="violation-time"><?= date('H:i:s', strtotime($v['timestamp'])) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

</body>
</html>
