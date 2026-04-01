<?php
require_once '../config.php';
requireAdmin();
$admin = currentAdmin();
$db = getDB();

$cid = $_SESSION['admin_company'];
$isSuperAdmin = $_SESSION['admin_role'] === 'super_admin';

// Stats
$where = $isSuperAdmin ? '' : 'WHERE company_id = ' . (int)$cid;
$assessments = $db->query("SELECT COUNT(*) FROM assessments $where")->fetchColumn();
$sessions = $db->query("SELECT COUNT(*) FROM test_sessions ts JOIN assessments a ON ts.assessment_id = a.id " . ($isSuperAdmin ? '' : "WHERE a.company_id = $cid"))->fetchColumn();
$active = $db->query("SELECT COUNT(*) FROM test_sessions ts JOIN assessments a ON ts.assessment_id = a.id WHERE ts.status='in_progress' " . ($isSuperAdmin ? '' : "AND a.company_id = $cid"))->fetchColumn();
$companies_count = $isSuperAdmin ? $db->query("SELECT COUNT(*) FROM companies")->fetchColumn() : null;

// Unread chat messages
$unread = $db->query("SELECT COUNT(*) FROM chat_messages cm JOIN test_sessions ts ON cm.session_id = ts.id JOIN assessments a ON ts.assessment_id = a.id WHERE cm.sender_type='candidate' AND cm.is_read=0 " . ($isSuperAdmin ? '' : "AND a.company_id = $cid"))->fetchColumn();

// Recent sessions
$recentQ = "SELECT ts.*, a.title as assessment_title, c.name as candidate_name, c.email as candidate_email 
            FROM test_sessions ts 
            JOIN assessments a ON ts.assessment_id = a.id 
            JOIN candidates c ON ts.candidate_id = c.id 
            " . ($isSuperAdmin ? '' : "WHERE a.company_id = $cid") . "
            ORDER BY ts.created_at DESC LIMIT 8";
$recent = $db->query($recentQ)->fetchAll();

// Recent chats with unread counts
$chatQ = "SELECT ts.id as session_id, ts.status, c.name as candidate_name, a.title as assessment_title,
          COUNT(cm.id) as unread_count,
          MAX(cm.sent_at) as last_msg
          FROM test_sessions ts 
          JOIN candidates c ON ts.candidate_id = c.id
          JOIN assessments a ON ts.assessment_id = a.id
          LEFT JOIN chat_messages cm ON cm.session_id = ts.id AND cm.is_read = 0 AND cm.sender_type = 'candidate'
          " . ($isSuperAdmin ? '' : "WHERE a.company_id = $cid") . "
          GROUP BY ts.id ORDER BY last_msg DESC LIMIT 6";
$chats = $db->query($chatQ)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — AssessPro</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg: #0a0a0f; --surface: #12121a; --surface2: #1a1a26;
  --border: #1e1e2e; --accent: #6c63ff; --accent2: #ff6584;
  --text: #e8e8f0; --muted: #6b6b80; --success: #43e97b;
  --warning: #f5a623; --error: #ff6584; --info: #4facfe;
}
body { background: var(--bg); font-family: 'DM Sans', sans-serif; color: var(--text); min-height: 100vh; display: flex; }
/* Sidebar */
.sidebar {
  width: 260px; min-height: 100vh; background: var(--surface);
  border-right: 1px solid var(--border); padding: 28px 20px;
  display: flex; flex-direction: column; position: fixed; left: 0; top: 0; bottom: 0;
}
.logo { display: flex; align-items: center; gap: 10px; margin-bottom: 40px; padding: 0 8px; }
.logo-icon { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, var(--accent), var(--accent2)); display: flex; align-items: center; justify-content: center; font-size: 18px; }
.logo-text { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 18px; }
.nav-section { font-size: 10px; text-transform: uppercase; letter-spacing: .1em; color: var(--muted); padding: 0 10px; margin: 16px 0 8px; }
.nav-item {
  display: flex; align-items: center; gap: 10px; padding: 10px 12px;
  border-radius: 10px; color: var(--muted); text-decoration: none; font-size: 14px;
  transition: all .15s; margin-bottom: 2px; position: relative;
}
.nav-item:hover { background: var(--surface2); color: var(--text); }
.nav-item.active { background: rgba(108,99,255,.15); color: var(--accent); }
.nav-item .badge {
  margin-left: auto; background: var(--accent2); color: #fff;
  font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 20px;
}
.nav-spacer { flex: 1; }
.user-card {
  background: var(--surface2); border-radius: 12px; padding: 14px;
  display: flex; align-items: center; gap: 10px;
}
.avatar {
  width: 36px; height: 36px; border-radius: 50%;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 14px;
}
.user-info .name { font-size: 13px; font-weight: 500; }
.user-info .role { font-size: 11px; color: var(--muted); }
/* Main */
.main { margin-left: 260px; flex: 1; padding: 36px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 36px; }
.page-title { font-family: 'Syne', sans-serif; font-size: 26px; font-weight: 700; }
.page-sub { color: var(--muted); font-size: 14px; margin-top: 4px; }
.btn {
  padding: 10px 20px; border-radius: 10px; border: none; cursor: pointer;
  font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 500;
  background: var(--accent); color: #fff; text-decoration: none;
  display: inline-flex; align-items: center; gap: 6px; transition: opacity .2s;
}
.btn:hover { opacity: .85; }
/* Stats grid */
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 36px; }
.stat-card {
  background: var(--surface); border: 1px solid var(--border); border-radius: 16px;
  padding: 24px; position: relative; overflow: hidden;
}
.stat-card::before {
  content: ''; position: absolute; top: -30px; right: -30px;
  width: 100px; height: 100px; border-radius: 50%;
  opacity: .1;
}
.stat-card:nth-child(1)::before { background: var(--accent); }
.stat-card:nth-child(2)::before { background: var(--success); }
.stat-card:nth-child(3)::before { background: var(--warning); }
.stat-card:nth-child(4)::before { background: var(--accent2); }
.stat-label { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .08em; margin-bottom: 12px; }
.stat-value { font-family: 'Syne', sans-serif; font-size: 36px; font-weight: 800; }
.stat-icon { position: absolute; top: 20px; right: 20px; font-size: 24px; opacity: .6; }
/* Grid layout */
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
.card {
  background: var(--surface); border: 1px solid var(--border); border-radius: 16px;
  padding: 24px;
}
.card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.card-title { font-family: 'Syne', sans-serif; font-size: 16px; font-weight: 700; }
.card-action { font-size: 12px; color: var(--accent); text-decoration: none; }
/* Table */
.table { width: 100%; border-collapse: collapse; font-size: 13px; }
.table th { text-align: left; padding: 0 12px 12px; color: var(--muted); font-weight: 500; text-transform: uppercase; font-size: 11px; letter-spacing: .06em; border-bottom: 1px solid var(--border); }
.table td { padding: 14px 12px; border-bottom: 1px solid rgba(30,30,46,.5); }
.table tr:last-child td { border-bottom: none; }
.table tr:hover td { background: rgba(108,99,255,.04); }
.status-badge {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;
}
.status-badge.pending { background: rgba(245,166,35,.15); color: var(--warning); }
.status-badge.in_progress { background: rgba(79,172,254,.15); color: var(--info); }
.status-badge.completed { background: rgba(67,233,123,.15); color: var(--success); }
.status-badge.expired { background: rgba(255,101,132,.15); color: var(--error); }
/* Chat list */
.chat-item {
  display: flex; align-items: center; gap: 12px; padding: 12px;
  border-radius: 10px; cursor: pointer; transition: background .15s;
  text-decoration: none; color: var(--text);
}
.chat-item:hover { background: var(--surface2); }
.chat-avatar {
  width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
  background: linear-gradient(135deg, var(--accent), #9b59b6);
  display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;
}
.chat-name { font-size: 14px; font-weight: 500; }
.chat-sub { font-size: 12px; color: var(--muted); }
.chat-meta { margin-left: auto; text-align: right; }
.unread-dot {
  display: inline-block; background: var(--accent2); color: #fff;
  font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 20px;
}
.score-bar { background: var(--border); border-radius: 4px; height: 4px; width: 80px; }
.score-fill { height: 100%; border-radius: 4px; background: var(--success); }
</style>
</head>
<body>
<nav class="sidebar">
  <div class="logo">
    <div class="logo-icon">🎯</div>
    <span class="logo-text">AssessPro</span>
  </div>
  <span class="nav-section">Main</span>
  <a href="dashboard.php" class="nav-item active">📊 Dashboard</a>
  <a href="assessments.php" class="nav-item">📝 Assessments</a>
  <a href="candidates.php" class="nav-item">👥 Candidates</a>
  <a href="sessions.php" class="nav-item">🖥️ Test Sessions</a>
  <a href="chat.php" class="nav-item">💬 Live Chat <?php if($unread > 0): ?><span class="badge"><?= $unread ?></span><?php endif; ?></a>
  <?php if ($isSuperAdmin): ?>
  <span class="nav-section">Admin</span>
  <a href="companies.php" class="nav-item">🏢 Companies</a>
  <a href="admins.php" class="nav-item">👤 Admins</a>
  <?php endif; ?>
  <div class="nav-spacer"></div>
  <div class="user-card">
    <div class="avatar"><?= strtoupper(substr($admin['name'], 0, 1)) ?></div>
    <div class="user-info">
      <div class="name"><?= sanitize($admin['name']) ?></div>
      <div class="role"><?= $admin['role'] === 'super_admin' ? 'Super Admin' : 'Company Admin' ?></div>
    </div>
  </div>
  <a href="logout.php" style="display:block;text-align:center;margin-top:12px;font-size:13px;color:var(--muted);text-decoration:none;">← Logout</a>
</nav>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Dashboard</div>
      <div class="page-sub">Welcome back, <?= sanitize($admin['name']) ?>!</div>
    </div>
    <a href="assessments.php?new=1" class="btn">＋ New Assessment</a>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-label">Total Assessments</div>
      <div class="stat-value"><?= $assessments ?></div>
      <div class="stat-icon">📝</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total Sessions</div>
      <div class="stat-value"><?= $sessions ?></div>
      <div class="stat-icon">🖥️</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Live Right Now</div>
      <div class="stat-value"><?= $active ?></div>
      <div class="stat-icon">🔴</div>
    </div>
    <?php if ($isSuperAdmin): ?>
    <div class="stat-card">
      <div class="stat-label">Companies</div>
      <div class="stat-value"><?= $companies_count ?></div>
      <div class="stat-icon">🏢</div>
    </div>
    <?php else: ?>
    <div class="stat-card">
      <div class="stat-label">Unread Messages</div>
      <div class="stat-value"><?= $unread ?></div>
      <div class="stat-icon">💬</div>
    </div>
    <?php endif; ?>
  </div>

  <div class="grid-2">
    <div class="card">
      <div class="card-header">
        <span class="card-title">Recent Sessions</span>
        <a href="sessions.php" class="card-action">View all →</a>
      </div>
      <table class="table">
        <thead>
          <tr><th>Candidate</th><th>Assessment</th><th>Status</th><th>Score</th></tr>
        </thead>
        <tbody>
        <?php foreach ($recent as $s): ?>
          <tr>
            <td>
              <div><?= sanitize($s['candidate_name']) ?></div>
              <div style="font-size:11px;color:var(--muted)"><?= sanitize($s['candidate_email']) ?></div>
            </td>
            <td><?= sanitize($s['assessment_title']) ?></td>
            <td><span class="status-badge <?= $s['status'] ?>"><?= ucfirst(str_replace('_',' ',$s['status'])) ?></span></td>
            <td>
              <?php if ($s['status'] === 'completed' && $s['total_marks'] > 0): ?>
                <div class="score-bar"><div class="score-fill" style="width:<?= $s['percentage'] ?>%;background:<?= $s['passed'] ? 'var(--success)' : 'var(--error)' ?>"></div></div>
                <div style="font-size:11px;margin-top:2px"><?= $s['percentage'] ?>%</div>
              <?php else: ?>
                <span style="color:var(--muted);font-size:12px">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($recent)): ?>
          <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:24px">No sessions yet</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <div class="card-header">
        <span class="card-title">Live Chat Support</span>
        <a href="chat.php" class="card-action">Open all →</a>
      </div>
      <?php foreach ($chats as $ch): ?>
      <a href="chat.php?session=<?= $ch['session_id'] ?>" class="chat-item">
        <div class="chat-avatar"><?= strtoupper(substr($ch['candidate_name'],0,1)) ?></div>
        <div>
          <div class="chat-name"><?= sanitize($ch['candidate_name']) ?></div>
          <div class="chat-sub"><?= sanitize($ch['assessment_title']) ?></div>
        </div>
        <div class="chat-meta">
          <?php if ($ch['unread_count'] > 0): ?>
            <span class="unread-dot"><?= $ch['unread_count'] ?></span>
          <?php endif; ?>
          <div style="font-size:11px;color:var(--muted);margin-top:4px">
            <span class="status-badge <?= $ch['status'] ?>" style="padding:2px 8px"><?= ucfirst(str_replace('_',' ',$ch['status'])) ?></span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
      <?php if (empty($chats)): ?>
        <div style="text-align:center;color:var(--muted);padding:32px;font-size:14px">No active conversations</div>
      <?php endif; ?>
    </div>
  </div>
</main>
</body>
</html>
