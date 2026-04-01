<?php
require_once '../config.php';
requireAdmin();
$admin = currentAdmin();
$db = getDB();

$cid = $_SESSION['admin_company'];
$isSuperAdmin = $_SESSION['admin_role'] === 'super_admin';
$activeSession = isset($_GET['session']) ? (int)$_GET['session'] : null;

// Get all chat sessions
$sessionsQ = "SELECT ts.id, ts.status, c.name as candidate_name, c.email as candidate_email,
              a.title as assessment_title,
              COUNT(CASE WHEN cm.sender_type='candidate' AND cm.is_read=0 THEN 1 END) as unread,
              MAX(cm.sent_at) as last_msg
              FROM test_sessions ts
              JOIN candidates c ON ts.candidate_id = c.id
              JOIN assessments a ON ts.assessment_id = a.id
              LEFT JOIN chat_messages cm ON cm.session_id = ts.id
              " . ($isSuperAdmin ? '' : "WHERE a.company_id = $cid") . "
              GROUP BY ts.id ORDER BY last_msg DESC, ts.created_at DESC";
$sessions = $db->query($sessionsQ)->fetchAll();

// If session selected, mark as read
if ($activeSession) {
    $db->prepare("UPDATE chat_messages SET is_read=1 WHERE session_id=? AND sender_type='candidate'")->execute([$activeSession]);
    // Get messages
    $messages = $db->prepare("SELECT * FROM chat_messages WHERE session_id=? ORDER BY sent_at ASC");
    $messages->execute([$activeSession]);
    $messages = $messages->fetchAll();
    // Get session info
    $sessionInfo = $db->prepare("SELECT ts.*, c.name as candidate_name, c.email as candidate_email, a.title as assessment_title FROM test_sessions ts JOIN candidates c ON ts.candidate_id=c.id JOIN assessments a ON ts.assessment_id=a.id WHERE ts.id=?");
    $sessionInfo->execute([$activeSession]);
    $sessionInfo = $sessionInfo->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Live Chat — AssessPro</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg: #0a0a0f; --surface: #12121a; --surface2: #1a1a26;
  --border: #1e1e2e; --accent: #6c63ff; --accent2: #ff6584;
  --text: #e8e8f0; --muted: #6b6b80; --success: #43e97b;
  --warning: #f5a623; --error: #ff6584; --info: #4facfe;
}
body { background: var(--bg); font-family: 'DM Sans', sans-serif; color: var(--text); height: 100vh; display: flex; }
.sidebar {
  width: 240px; min-height: 100vh; background: var(--surface);
  border-right: 1px solid var(--border); padding: 24px 16px;
  display: flex; flex-direction: column; position: fixed; left: 0; top: 0; bottom: 0;
}
.logo { display: flex; align-items: center; gap: 10px; margin-bottom: 32px; padding: 0 6px; }
.logo-icon { width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, var(--accent), var(--accent2)); display: flex; align-items: center; justify-content: center; font-size: 16px; }
.logo-text { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 17px; }
.nav-item { display: flex; align-items: center; gap: 8px; padding: 9px 10px; border-radius: 8px; color: var(--muted); text-decoration: none; font-size: 13px; transition: all .15s; margin-bottom: 2px; }
.nav-item:hover { background: var(--surface2); color: var(--text); }
.nav-item.active { background: rgba(108,99,255,.15); color: var(--accent); }
.nav-spacer { flex: 1; }
.nav-back { color: var(--muted); font-size: 12px; text-decoration: none; display: block; text-align: center; margin-top: 10px; }
/* Chat layout */
.main { margin-left: 240px; flex: 1; display: flex; height: 100vh; overflow: hidden; }
.chat-list { width: 300px; border-right: 1px solid var(--border); overflow-y: auto; background: var(--surface); }
.chat-list-header { padding: 20px; border-bottom: 1px solid var(--border); }
.chat-list-title { font-family: 'Syne', sans-serif; font-size: 16px; font-weight: 700; }
.chat-list-sub { font-size: 12px; color: var(--muted); margin-top: 4px; }
.chat-session-item {
  padding: 16px 20px; border-bottom: 1px solid var(--border);
  cursor: pointer; transition: background .15s; text-decoration: none;
  display: flex; gap: 12px; align-items: center; color: var(--text);
}
.chat-session-item:hover { background: var(--surface2); }
.chat-session-item.active { background: rgba(108,99,255,.1); border-left: 3px solid var(--accent); }
.session-avatar {
  width: 44px; height: 44px; border-radius: 50%; flex-shrink: 0;
  background: linear-gradient(135deg, var(--accent), #9b59b6);
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 16px;
}
.session-name { font-size: 14px; font-weight: 500; }
.session-sub { font-size: 11px; color: var(--muted); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 160px; }
.session-meta { margin-left: auto; text-align: right; flex-shrink: 0; }
.unread-badge { background: var(--accent2); color: #fff; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 20px; }
.status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.status-dot.in_progress { background: var(--info); }
.status-dot.completed { background: var(--success); }
.status-dot.pending { background: var(--warning); }
/* Chat window */
.chat-window { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.chat-header {
  padding: 20px 24px; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: 14px;
  background: var(--surface);
}
.chat-header-avatar { width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), #9b59b6); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px; }
.chat-header-name { font-family: 'Syne', sans-serif; font-size: 16px; font-weight: 700; }
.chat-header-sub { font-size: 12px; color: var(--muted); }
.status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; margin-left: auto; }
.status-badge.in_progress { background: rgba(79,172,254,.15); color: var(--info); }
.status-badge.completed { background: rgba(67,233,123,.15); color: var(--success); }
.status-badge.pending { background: rgba(245,166,35,.15); color: var(--warning); }
/* Messages */
.messages-container { flex: 1; overflow-y: auto; padding: 24px; display: flex; flex-direction: column; gap: 12px; }
.message { display: flex; gap: 10px; align-items: flex-end; max-width: 75%; }
.message.admin { flex-direction: row-reverse; align-self: flex-end; }
.message.candidate { align-self: flex-start; }
.msg-avatar { width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; }
.candidate .msg-avatar { background: linear-gradient(135deg, #667eea, #764ba2); }
.admin .msg-avatar { background: linear-gradient(135deg, var(--accent), var(--accent2)); }
.msg-bubble { padding: 12px 16px; border-radius: 16px; font-size: 14px; line-height: 1.5; }
.candidate .msg-bubble { background: var(--surface2); border: 1px solid var(--border); border-bottom-left-radius: 4px; }
.admin .msg-bubble { background: var(--accent); border-bottom-right-radius: 4px; }
.msg-time { font-size: 10px; color: var(--muted); margin-top: 4px; text-align: right; }
.candidate .msg-time { text-align: left; }
/* Input */
.chat-input-area { padding: 20px 24px; border-top: 1px solid var(--border); background: var(--surface); display: flex; gap: 12px; align-items: flex-end; }
.chat-input { flex: 1; background: var(--bg); border: 1px solid var(--border); border-radius: 12px; padding: 12px 16px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 14px; outline: none; resize: none; max-height: 120px; transition: border-color .2s; }
.chat-input:focus { border-color: var(--accent); }
.send-btn { width: 44px; height: 44px; border-radius: 12px; background: var(--accent); border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; transition: opacity .2s; }
.send-btn:hover { opacity: .8; }
/* Empty state */
.empty-state { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--muted); }
.empty-state-icon { font-size: 64px; margin-bottom: 16px; opacity: .5; }
.empty-state-text { font-size: 16px; }
.empty-state-sub { font-size: 13px; margin-top: 6px; }
/* Day divider */
.day-divider { text-align: center; position: relative; margin: 8px 0; }
.day-divider span { background: var(--bg); padding: 0 12px; font-size: 11px; color: var(--muted); position: relative; z-index: 1; }
.day-divider::before { content: ''; position: absolute; top: 50%; left: 0; right: 0; height: 1px; background: var(--border); }
</style>
</head>
<body>
<nav class="sidebar">
  <div class="logo">
    <div class="logo-icon">🎯</div>
    <span class="logo-text">AssessPro</span>
  </div>
  <a href="dashboard.php" class="nav-item">📊 Dashboard</a>
  <a href="assessments.php" class="nav-item">📝 Assessments</a>
  <a href="candidates.php" class="nav-item">👥 Candidates</a>
  <a href="sessions.php" class="nav-item">🖥️ Sessions</a>
  <a href="chat.php" class="nav-item active">💬 Live Chat</a>
  <div class="nav-spacer"></div>
  <a href="logout.php" class="nav-back">← Logout</a>
</nav>

<div class="main">
  <!-- Sessions list -->
  <div class="chat-list">
    <div class="chat-list-header">
      <div class="chat-list-title">💬 Support Chat</div>
      <div class="chat-list-sub"><?= count($sessions) ?> conversation(s)</div>
    </div>
    <?php foreach ($sessions as $s): ?>
    <a href="chat.php?session=<?= $s['id'] ?>" class="chat-session-item <?= ($activeSession == $s['id']) ? 'active' : '' ?>">
      <div class="session-avatar"><?= strtoupper(substr($s['candidate_name'],0,1)) ?></div>
      <div style="min-width:0;flex:1">
        <div class="session-name"><?= sanitize($s['candidate_name']) ?></div>
        <div class="session-sub"><?= sanitize($s['assessment_title']) ?></div>
      </div>
      <div class="session-meta">
        <?php if ($s['unread'] > 0): ?>
          <div><span class="unread-badge"><?= $s['unread'] ?></span></div>
        <?php endif; ?>
        <div style="margin-top:4px">
          <span class="status-dot <?= $s['status'] ?>"></span>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
    <?php if (empty($sessions)): ?>
      <div style="padding:32px;text-align:center;color:var(--muted);font-size:13px">No sessions yet</div>
    <?php endif; ?>
  </div>

  <!-- Chat window -->
  <div class="chat-window">
    <?php if ($activeSession && $sessionInfo): ?>
    <div class="chat-header">
      <div class="chat-header-avatar"><?= strtoupper(substr($sessionInfo['candidate_name'],0,1)) ?></div>
      <div>
        <div class="chat-header-name"><?= sanitize($sessionInfo['candidate_name']) ?></div>
        <div class="chat-header-sub"><?= sanitize($sessionInfo['candidate_email']) ?> · <?= sanitize($sessionInfo['assessment_title']) ?></div>
      </div>
      <span class="status-badge <?= $sessionInfo['status'] ?>"><?= ucfirst(str_replace('_',' ',$sessionInfo['status'])) ?></span>
    </div>

    <div class="messages-container" id="messages">
      <?php 
      $prevDate = null;
      foreach ($messages as $msg): 
        $msgDate = date('M j, Y', strtotime($msg['sent_at']));
        if ($msgDate !== $prevDate): 
          $prevDate = $msgDate;
      ?>
        <div class="day-divider"><span><?= $msgDate ?></span></div>
      <?php endif; ?>
      <div class="message <?= $msg['sender_type'] ?>">
        <div class="msg-avatar"><?= $msg['sender_type'] === 'admin' ? '👤' : '🧑' ?></div>
        <div>
          <div class="msg-bubble"><?= nl2br(sanitize($msg['message'])) ?></div>
          <div class="msg-time"><?= date('h:i A', strtotime($msg['sent_at'])) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($messages)): ?>
        <div style="text-align:center;color:var(--muted);font-size:13px;margin:auto">No messages yet. The candidate can start a conversation during their test.</div>
      <?php endif; ?>
    </div>

    <?php if ($sessionInfo['status'] !== 'expired'): ?>
    <div class="chat-input-area">
      <textarea class="chat-input" id="messageInput" placeholder="Type your reply..." rows="1"></textarea>
      <button class="send-btn" id="sendBtn">➤</button>
    </div>
    <?php else: ?>
    <div style="padding:16px;text-align:center;color:var(--muted);font-size:13px;border-top:1px solid var(--border)">Session expired — chat is read-only</div>
    <?php endif; ?>

    <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon">💬</div>
      <div class="empty-state-text">Select a conversation</div>
      <div class="empty-state-sub">Choose a candidate session from the left to view or respond</div>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
const sessionId = <?= $activeSession ?? 'null' ?>;
const adminId = <?= $_SESSION['admin_id'] ?>;

// Auto-scroll to bottom
function scrollToBottom() {
  const el = document.getElementById('messages');
  if (el) el.scrollTop = el.scrollHeight;
}
scrollToBottom();

// Send message
async function sendMessage() {
  const input = document.getElementById('messageInput');
  const msg = input.value.trim();
  if (!msg || !sessionId) return;
  input.value = '';

  const res = await fetch('../php/send_chat.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ session_id: sessionId, message: msg, sender_type: 'admin', sender_id: adminId })
  });
  const data = await res.json();
  if (data.success) loadMessages();
}

document.getElementById('sendBtn')?.addEventListener('click', sendMessage);
document.getElementById('messageInput')?.addEventListener('keydown', e => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
});

// Poll for new messages
async function loadMessages() {
  if (!sessionId) return;
  const res = await fetch('../php/get_messages.php?session_id=' + sessionId + '&viewer=admin&admin_id=' + adminId);
  const data = await res.json();
  if (!data.messages) return;
  const container = document.getElementById('messages');
  container.innerHTML = data.messages.map(m => `
    <div class="message ${m.sender_type}">
      <div class="msg-avatar">${m.sender_type === 'admin' ? '👤' : '🧑'}</div>
      <div>
        <div class="msg-bubble">${escHtml(m.message).replace(/\n/g,'<br>')}</div>
        <div class="msg-time">${m.time}</div>
      </div>
    </div>
  `).join('');
  scrollToBottom();
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

if (sessionId) setInterval(loadMessages, 3000);
</script>
</body>
</html>
