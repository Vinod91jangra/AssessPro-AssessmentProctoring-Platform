<?php
// invite.php
require_once '../config.php';
requireAdmin();
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: assessments.php'); exit; }

$assessment = $db->prepare("SELECT * FROM assessments WHERE id=?")->execute([$id]) ? null : null;
$stmt = $db->prepare("SELECT * FROM assessments WHERE id=?");
$stmt->execute([$id]);
$assessment = $stmt->fetch();

$error = $success = '';
$inviteLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');

    if ($name && $email) {
        // Find or create candidate
        $cand = $db->prepare("SELECT * FROM candidates WHERE email=? AND company_id=?");
        $cand->execute([$email, $assessment['company_id']]);
        $candidate = $cand->fetch();
        if (!$candidate) {
            $db->prepare("INSERT INTO candidates (name, email, phone, company_id) VALUES (?,?,?,?)")
               ->execute([$name, $email, $phone, $assessment['company_id']]);
            $candidateId = $db->lastInsertId();
        } else {
            $candidateId = $candidate['id'];
        }

        // Create session
        $token = generateToken();
        $db->prepare("INSERT INTO test_sessions (assessment_id, candidate_id, token) VALUES (?,?,?)")
           ->execute([$id, $candidateId, $token]);
        
        $inviteLink = APP_URL . '/candidate/test.php?token=' . $token;
        $success = "Invitation link created for $name!";
    } else {
        $error = 'Name and email are required.';
    }
}

// Past sessions
$sessions = $db->prepare("SELECT ts.*, c.name as candidate_name, c.email as candidate_email FROM test_sessions ts JOIN candidates c ON ts.candidate_id=c.id WHERE ts.assessment_id=? ORDER BY ts.created_at DESC");
$sessions->execute([$id]);
$sessions = $sessions->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invite Candidate — AssessPro</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#0a0a0f;--surface:#12121a;--surface2:#1a1a26;--border:#1e1e2e;--accent:#6c63ff;--accent2:#ff6584;--text:#e8e8f0;--muted:#6b6b80;--success:#43e97b;--error:#ff6584}
body{background:var(--bg);font-family:'DM Sans',sans-serif;color:var(--text);min-height:100vh;display:flex}
.sidebar{width:240px;min-height:100vh;background:var(--surface);border-right:1px solid var(--border);padding:24px 16px;display:flex;flex-direction:column;position:fixed;left:0;top:0;bottom:0}
.logo{display:flex;align-items:center;gap:10px;margin-bottom:32px;padding:0 6px}
.logo-icon{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:16px}
.logo-text{font-family:'Syne',sans-serif;font-weight:800;font-size:17px}
.nav-item{display:flex;align-items:center;gap:8px;padding:9px 10px;border-radius:8px;color:var(--muted);text-decoration:none;font-size:13px;transition:all .15s;margin-bottom:2px}
.nav-item:hover{background:var(--surface2);color:var(--text)}
.nav-item.active{background:rgba(108,99,255,.15);color:var(--accent)}
.nav-spacer{flex:1}
.nav-back{color:var(--muted);font-size:12px;text-decoration:none;display:block;text-align:center;margin-top:10px}
.main{margin-left:240px;flex:1;padding:36px;display:grid;grid-template-columns:1fr 380px;gap:28px;align-items:start}
.page-title{font-family:'Syne',sans-serif;font-size:22px;font-weight:700;grid-column:1/-1;margin-bottom:8px}
.card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:24px}
.card-title{font-family:'Syne',sans-serif;font-size:16px;font-weight:700;margin-bottom:20px}
.field{margin-bottom:16px}
label{display:block;font-size:12px;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px}
input{width:100%;background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:11px 13px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;transition:border-color .2s}
input:focus{border-color:var(--accent)}
.btn{width:100%;padding:12px;border-radius:10px;border:none;cursor:pointer;font-family:'Syne',sans-serif;font-size:15px;font-weight:700;background:var(--accent);color:#fff;transition:opacity .2s}
.btn:hover{opacity:.9}
.alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:16px}
.alert.success{background:rgba(67,233,123,.1);border:1px solid rgba(67,233,123,.3);color:var(--success)}
.alert.error{background:rgba(255,101,132,.1);border:1px solid rgba(255,101,132,.3);color:var(--error)}
.link-box{background:var(--bg);border:1px solid rgba(108,99,255,.4);border-radius:10px;padding:12px 14px;font-size:12px;font-family:monospace;word-break:break-all;color:var(--accent);cursor:pointer;margin-top:8px}
.table{width:100%;border-collapse:collapse;font-size:13px}
.table th{text-align:left;padding:0 12px 12px;color:var(--muted);font-weight:500;text-transform:uppercase;font-size:11px;letter-spacing:.06em;border-bottom:1px solid var(--border)}
.table td{padding:12px;border-bottom:1px solid rgba(30,30,46,.5)}
.status-badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600}
.status-badge.pending{background:rgba(245,166,35,.15);color:#f5a623}
.status-badge.in_progress{background:rgba(79,172,254,.15);color:#4facfe}
.status-badge.completed{background:rgba(67,233,123,.15);color:var(--success)}
.copy-btn{background:rgba(108,99,255,.15);border:1px solid rgba(108,99,255,.3);color:var(--accent);padding:4px 10px;border-radius:6px;cursor:pointer;font-size:11px}
</style>
</head>
<body>
<nav class="sidebar">
  <div class="logo"><div class="logo-icon">🎯</div><span class="logo-text">AssessPro</span></div>
  <a href="dashboard.php" class="nav-item">📊 Dashboard</a>
  <a href="assessments.php" class="nav-item active">📝 Assessments</a>
  <a href="candidates.php" class="nav-item">👥 Candidates</a>
  <a href="sessions.php" class="nav-item">🖥️ Sessions</a>
  <a href="chat.php" class="nav-item">💬 Live Chat</a>
  <div class="nav-spacer"></div>
  <a href="logout.php" class="nav-back">← Logout</a>
</nav>
<main class="main">
  <div style="grid-column:1/-1">
    <a href="assessments.php" style="color:var(--muted);font-size:13px;text-decoration:none">← Assessments</a>
    <div class="page-title" style="margin-top:8px">📧 Invite Candidates — <?= sanitize($assessment['title']) ?></div>
  </div>

  <div>
    <div class="card">
      <div class="card-title">Sessions (<?= count($sessions) ?>)</div>
      <?php if (empty($sessions)): ?>
        <div style="color:var(--muted);font-size:13px;text-align:center;padding:24px">No invitations yet</div>
      <?php else: ?>
      <table class="table">
        <thead><tr><th>Candidate</th><th>Status</th><th>Score</th><th>Link</th></tr></thead>
        <tbody>
        <?php foreach ($sessions as $s): ?>
        <tr>
          <td>
            <div><?= sanitize($s['candidate_name']) ?></div>
            <div style="font-size:11px;color:var(--muted)"><?= sanitize($s['candidate_email']) ?></div>
          </td>
          <td><span class="status-badge <?= $s['status'] ?>"><?= ucfirst(str_replace('_',' ',$s['status'])) ?></span></td>
          <td><?= $s['status']==='completed' ? $s['percentage'].'%' : '—' ?></td>
          <td>
            <button class="copy-btn" onclick="copyLink('<?= APP_URL ?>/candidate/test.php?token=<?= $s['token'] ?>')">Copy</button>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <div class="card" style="position:sticky;top:24px">
    <div class="card-title">＋ Invite Candidate</div>
    <?php if ($error): ?><div class="alert error">⚠️ <?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert success">✓ <?= $success ?></div><?php endif; ?>
    <?php if ($inviteLink): ?>
    <div style="margin-bottom:16px">
      <label>Invite Link (share with candidate)</label>
      <div class="link-box" onclick="copyLink('<?= $inviteLink ?>')" title="Click to copy"><?= $inviteLink ?></div>
      <div style="font-size:11px;color:var(--muted);margin-top:6px">Click to copy · Link expires after test submission</div>
    </div>
    <?php endif; ?>
    <form method="POST">
      <div class="field">
        <label>Candidate Name</label>
        <input type="text" name="name" placeholder="John Smith" required>
      </div>
      <div class="field">
        <label>Email Address</label>
        <input type="email" name="email" placeholder="candidate@email.com" required>
      </div>
      <div class="field">
        <label>Phone (optional)</label>
        <input type="tel" name="phone" placeholder="+1 234 567 8900">
      </div>
      <button type="submit" class="btn">Generate Invite Link</button>
    </form>
  </div>
</main>
<script>
function copyLink(url) {
  navigator.clipboard.writeText(url).then(() => {
    alert('Link copied! Share it with the candidate:\n' + url);
  });
}
</script>
</body>
</html>
