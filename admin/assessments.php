<?php
require_once '../config.php';
requireAdmin();
$admin = currentAdmin();
$db = getDB();

$cid = $_SESSION['admin_company'];
$isSuperAdmin = $_SESSION['admin_role'] === 'super_admin';
$error = $success = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $title = sanitize($_POST['title'] ?? '');
        $desc = sanitize($_POST['description'] ?? '');
        $duration = (int)($_POST['duration'] ?? 60);
        $pass = (int)($_POST['pass_score'] ?? 60);
        $company = $isSuperAdmin ? (int)$_POST['company_id'] : $cid;
        
        if ($title && $company) {
            $stmt = $db->prepare("INSERT INTO assessments (company_id, title, description, duration_minutes, pass_score, created_by, status) VALUES (?,?,?,?,?,?,'draft')");
            $stmt->execute([$company, $title, $desc, $duration, $pass, $_SESSION['admin_id']]);
            $newId = $db->lastInsertId();
            $success = 'Assessment created!';
            header("Location: questions.php?id=$newId");
            exit;
        } else {
            $error = 'Title and company are required.';
        }
    } elseif ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $status = $_POST['status'];
        $db->prepare("UPDATE assessments SET status=? WHERE id=?")->execute([$status, $id]);
        header('Location: assessments.php');
        exit;
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM assessments WHERE id=?")->execute([$id]);
        header('Location: assessments.php');
        exit;
    }
}

// Fetch assessments
$q = "SELECT a.*, c.name as company_name, 
      (SELECT COUNT(*) FROM questions WHERE assessment_id=a.id) as q_count,
      (SELECT COUNT(*) FROM test_sessions WHERE assessment_id=a.id) as session_count
      FROM assessments a JOIN companies c ON a.company_id=c.id
      " . ($isSuperAdmin ? '' : "WHERE a.company_id=$cid") . "
      ORDER BY a.created_at DESC";
$assessments = $db->query($q)->fetchAll();

$companies = $isSuperAdmin ? $db->query("SELECT * FROM companies ORDER BY name")->fetchAll() : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assessments — AssessPro</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root 
{ --bg:#0a0a0f;
    --surface:#12121a;
    --surface2:#1a1a26;
    --border:#1e1e2e;
    --accent:#6c63ff;
    --accent2:#ff6584;
    --text:#e8e8f0;
    --muted:#6b6b80;
    --success:#43e97b;
    --warning:#f5a623;
    --error:#ff6584; }
body { 
    background:var(--bg);
    font-family:'DM Sans',
    sans-serif;color:var(--text);
    min-height:100vh;
    display:flex; }
.sidebar { width:240px;min-height:100vh;background:var(--surface);border-right:1px solid var(--border);padding:24px 16px;display:flex;flex-direction:column;position:fixed;left:0;top:0;bottom:0; }
.logo { display:flex;align-items:center;gap:10px;margin-bottom:32px;padding:0 6px; }
.logo-icon { width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:16px; }
.logo-text { font-family:'Syne',sans-serif;font-weight:800;font-size:17px; }
.nav-item { display:flex;align-items:center;gap:8px;padding:9px 10px;border-radius:8px;color:var(--muted);text-decoration:none;font-size:13px;transition:all .15s;margin-bottom:2px; }
.nav-item:hover { background:var(--surface2);color:var(--text); }
.nav-item.active { background:rgba(108,99,255,.15);color:var(--accent); }
.nav-spacer { flex:1; }
.nav-back { color:var(--muted);font-size:12px;text-decoration:none;display:block;text-align:center;margin-top:10px; }
.main { margin-left:240px;flex:1;padding:36px; }
.page-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:32px; }
.page-title { font-family:'Syne',sans-serif;font-size:26px;font-weight:700; }
.btn { padding:10px 20px;border-radius:10px;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:500;background:var(--accent);color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:opacity .2s; }
.btn:hover { opacity:.85; }
.btn.secondary { background:var(--surface2);border:1px solid var(--border);color:var(--text); }
.btn.danger { background:rgba(255,101,132,.15);border:1px solid rgba(255,101,132,.3);color:var(--error); }
.btn.sm { padding:6px 14px;font-size:12px; }
/* Modal */
.modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:100;align-items:center;justify-content:center; }
.modal-overlay.open { display:flex; }
.modal { background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:36px;width:100%;max-width:500px;animation:fadeIn .2s; }
@keyframes fadeIn { from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none} }
.modal-title { font-family:'Syne',sans-serif;font-size:20px;font-weight:700;margin-bottom:24px; }
.field { margin-bottom:18px; }
label { display:block;font-size:12px;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px; }
input,select,textarea { width:100%;background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:12px 14px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:14px;transition:border-color .2s;outline:none; }
input:focus,select:focus,textarea:focus { border-color:var(--accent); }
select option { background:var(--surface); }
.modal-actions { display:flex;gap:10px;justify-content:flex-end;margin-top:24px; }
/* Cards grid */
.cards-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px; }
.assessment-card { background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:24px;transition:border-color .2s;position:relative; }
.assessment-card:hover { border-color:rgba(108,99,255,.4); }
.card-top { display:flex;align-items:flex-start;gap:12px;margin-bottom:16px; }
.card-icon { width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0; }
.card-icon.draft { background:rgba(107,107,128,.15); }
.card-icon.active { background:rgba(67,233,123,.15); }
.card-icon.archived { background:rgba(245,166,35,.1); }
.card-title-text { font-family:'Syne',sans-serif;font-size:16px;font-weight:700;line-height:1.3; }
.card-company { font-size:12px;color:var(--muted);margin-top:2px; }
.card-meta { display:flex;gap:16px;margin-bottom:16px; }
.meta-item { font-size:12px;color:var(--muted); }
.meta-item strong { display:block;font-size:18px;font-weight:700;color:var(--text);font-family:'Syne',sans-serif; }
.status-badge { display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600; }
.status-badge.draft { background:rgba(107,107,128,.15);color:var(--muted); }
.status-badge.active { background:rgba(67,233,123,.15);color:var(--success); }
.status-badge.archived { background:rgba(245,166,35,.1);color:var(--warning); }
.card-actions { display:flex;gap:8px;flex-wrap:wrap; }
.alert { padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px; }
.alert.success { background:rgba(67,233,123,.1);border:1px solid rgba(67,233,123,.3);color:var(--success); }
.alert.error { background:rgba(255,101,132,.1);border:1px solid rgba(255,101,132,.3);color:var(--error); }
.empty { text-align:center;padding:60px;color:var(--muted); }
.empty-icon { font-size:48px;margin-bottom:12px; }
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
  <div class="page-header">
    <div class="page-title">📝 Assessments</div>
    <button class="btn" onclick="document.getElementById('createModal').classList.add('open')">＋ Create Assessment</button>
  </div>

  <?php if ($error): ?><div class="alert error">⚠️ <?= $error ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert success">✓ <?= $success ?></div><?php endif; ?>

  <?php if (empty($assessments)): ?>
  <div class="empty"><div class="empty-icon">📝</div><div>No assessments yet</div><div style="font-size:13px;margin-top:6px">Create your first assessment to get started</div></div>
  <?php else: ?>
  <div class="cards-grid">
    <?php foreach ($assessments as $a): ?>
    <div class="assessment-card">
      <div class="card-top">
        <div class="card-icon <?= $a['status'] ?>">
          <?= $a['status'] === 'active' ? '✅' : ($a['status'] === 'archived' ? '📦' : '📄') ?>
        </div>
        <div>
          <div class="card-title-text"><?= sanitize($a['title']) ?></div>
          <?php if ($isSuperAdmin): ?><div class="card-company">🏢 <?= sanitize($a['company_name']) ?></div><?php endif; ?>
        </div>
        <span class="status-badge <?= $a['status'] ?>" style="margin-left:auto"><?= ucfirst($a['status']) ?></span>
      </div>
      <div class="card-meta">
        <div class="meta-item"><strong><?= $a['q_count'] ?></strong>Questions</div>
        <div class="meta-item"><strong><?= $a['duration_minutes'] ?>m</strong>Duration</div>
        <div class="meta-item"><strong><?= $a['pass_score'] ?>%</strong>Pass Score</div>
        <div class="meta-item"><strong><?= $a['session_count'] ?></strong>Sessions</div>
      </div>
      <div class="card-actions">
        <a href="questions.php?id=<?= $a['id'] ?>" class="btn sm secondary">✏️ Questions</a>
        <a href="invite.php?id=<?= $a['id'] ?>" class="btn sm secondary">📧 Invite</a>
        <a href="results.php?id=<?= $a['id'] ?>" class="btn sm secondary">📊 Results</a>
        <?php if ($a['status'] === 'draft'): ?>
        <form method="POST" style="display:inline"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="id" value="<?= $a['id'] ?>"><input type="hidden" name="status" value="active"><button class="btn sm" type="submit">▶ Activate</button></form>
        <?php elseif ($a['status'] === 'active'): ?>
        <form method="POST" style="display:inline"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="id" value="<?= $a['id'] ?>"><input type="hidden" name="status" value="archived"><button class="btn sm secondary" type="submit">📦 Archive</button></form>
        <?php endif; ?>
        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this assessment?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="btn sm danger" type="submit">🗑</button></form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>

<!-- Create Modal -->
<div class="modal-overlay" id="createModal" onclick="if(e.target===this)this.classList.remove('open')">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-title">Create New Assessment</div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <?php if ($isSuperAdmin): ?>
      <div class="field">
        <label>Company</label>
        <select name="company_id" required>
          <option value="">Select company...</option>
          <?php foreach ($companies as $co): ?>
          <option value="<?= $co['id'] ?>"><?= sanitize($co['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="field">
        <label>Assessment Title</label>
        <input type="text" name="title" placeholder="e.g. Frontend Developer Assessment" required>
      </div>
      <div class="field">
        <label>Description</label>
        <textarea name="description" placeholder="Brief description for candidates..." rows="3"></textarea>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div class="field">
          <label>Duration (minutes)</label>
          <input type="number" name="duration" value="60" min="5" max="300">
        </div>
        <div class="field">
          <label>Pass Score (%)</label>
          <input type="number" name="pass_score" value="60" min="1" max="100">
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn secondary" onclick="document.getElementById('createModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn">Create & Add Questions →</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('createModal').addEventListener('click', function(e) {
  if (e.target === this) this.classList.remove('open');
});
<?php if (isset($_GET['new'])): ?>
document.getElementById('createModal').classList.add('open');
<?php endif; ?>
</script>
</body>
</html>
