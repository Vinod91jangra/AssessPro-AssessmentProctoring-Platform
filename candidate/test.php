<?php
require_once '../config.php';

$token = sanitize($_GET['token'] ?? '');
if (!$token) { die('Invalid test link.'); }

$db = getDB();
$stmt = $db->prepare("SELECT ts.*, a.title as assessment_title, a.description, a.duration_minutes, a.pass_score,
                      c.name as candidate_name, c.email as candidate_email, c.id as cand_id,
                      co.name as company_name
                      FROM test_sessions ts
                      JOIN assessments a ON ts.assessment_id = a.id
                      JOIN candidates c ON ts.candidate_id = c.id
                      JOIN companies co ON a.company_id = co.id
                      WHERE ts.token = ?");
$stmt->execute([$token]);
$session = $stmt->fetch();

if (!$session) { die('Test session not found.'); }
if ($session['status'] === 'expired') { die('This test session has expired.'); }
if ($session['status'] === 'completed') {
    // Show results
    header("Location: results.php?token=$token");
    exit;
}

// Start the session if pending
if ($session['status'] === 'pending') {
    $db->prepare("UPDATE test_sessions SET status='in_progress', start_time=NOW() WHERE token=?")->execute([$token]);
    $session['status'] = 'in_progress';
    $session['start_time'] = date('Y-m-d H:i:s');
}

// Store in PHP session for API calls
$_SESSION['candidate_session_id'] = $session['id'];
$_SESSION['candidate_id'] = $session['cand_id'];

// Get questions
$questions = $db->prepare("SELECT * FROM questions WHERE assessment_id=? ORDER BY order_index ASC, id ASC");
$questions->execute([$session['assessment_id']]);
$questions = $questions->fetchAll();

// Get already answered questions
$answered = $db->prepare("SELECT question_id, answer FROM candidate_answers WHERE session_id=?");
$answered->execute([$session['id']]);
$answeredMap = [];
foreach ($answered->fetchAll() as $a) {
    $answeredMap[$a['question_id']] = $a['answer'];
}

// Calculate time remaining
$startTime = strtotime($session['start_time']);
$endTime = $startTime + ($session['duration_minutes'] * 60);
$remaining = max(0, $endTime - time());
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= sanitize($session['assessment_title']) ?> — AssessPro</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg: #f4f4f9; --surface: #ffffff; --surface2: #f0f0f6;
  --border: #e2e2ee; --accent: #6c63ff; --accent2: #ff6584;
  --text: #1a1a2e; --muted: #8080a0; --success: #27ae60;
  --warning: #f39c12; --error: #e74c3c; --info: #3498db;
}
body { background: var(--bg); font-family: 'DM Sans', sans-serif; color: var(--text); min-height: 100vh; }

/* Top bar */
.topbar {
  background: var(--surface); border-bottom: 1px solid var(--border);
  padding: 0 32px; height: 64px; display: flex; align-items: center;
  position: sticky; top: 0; z-index: 50; box-shadow: 0 2px 12px rgba(0,0,0,.06);
}
.topbar-logo { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 18px; color: var(--accent); }
.topbar-title { margin-left: 24px; font-size: 15px; font-weight: 500; color: var(--muted); }
.topbar-right { margin-left: auto; display: flex; align-items: center; gap: 20px; }
.timer {
  background: var(--bg); border: 2px solid var(--border); border-radius: 10px;
  padding: 8px 16px; font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 700;
  letter-spacing: 2px; min-width: 90px; text-align: center; transition: all .3s;
}
.timer.warning { border-color: var(--warning); color: var(--warning); background: rgba(243,156,18,.08); }
.timer.danger { border-color: var(--error); color: var(--error); background: rgba(231,76,60,.08); animation: pulse 1s infinite; }
@keyframes pulse { 0%,100%{opacity:1}50%{opacity:.7} }
.candidate-info { font-size: 13px; color: var(--muted); }
.candidate-info strong { color: var(--text); }

/* Layout */
.layout { display: flex; gap: 0; }

/* Questions sidebar */
.q-nav {
  width: 260px; background: var(--surface); border-right: 1px solid var(--border);
  height: calc(100vh - 64px); overflow-y: auto; position: sticky; top: 64px;
  padding: 24px 16px;
}
.q-nav-title { font-size: 12px; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); margin-bottom: 12px; font-weight: 600; padding: 0 6px; }
.q-nav-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; }
.q-btn {
  aspect-ratio: 1; border-radius: 8px; border: 2px solid var(--border);
  background: var(--surface2); font-size: 12px; font-weight: 600; cursor: pointer;
  display: flex; align-items: center; justify-content: center; transition: all .15s;
  color: var(--muted);
}
.q-btn:hover { border-color: var(--accent); color: var(--accent); }
.q-btn.current { border-color: var(--accent); background: var(--accent); color: #fff; }
.q-btn.answered { border-color: var(--success); background: rgba(39,174,96,.1); color: var(--success); }
.q-btn.answered.current { background: var(--success); color: #fff; }

.q-progress { margin-top: 20px; }
.progress-label { display: flex; justify-content: space-between; font-size: 12px; color: var(--muted); margin-bottom: 6px; }
.progress-bar { background: var(--border); border-radius: 4px; height: 6px; }
.progress-fill { height: 100%; border-radius: 4px; background: linear-gradient(90deg, var(--accent), #9b59b6); transition: width .3s; }

.submit-section { margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border); }
.btn-submit { width: 100%; padding: 12px; border-radius: 10px; border: none; cursor: pointer; font-family: 'Syne', sans-serif; font-size: 15px; font-weight: 700; background: linear-gradient(135deg, var(--accent), #9b59b6); color: #fff; transition: opacity .2s; }
.btn-submit:hover { opacity: .9; }

/* Main content */
.q-main { flex: 1; padding: 36px 40px; max-width: 800px; }
.question-card {
  background: var(--surface); border: 1px solid var(--border); border-radius: 20px;
  padding: 36px; margin-bottom: 24px; animation: fadeIn .3s;
}
@keyframes fadeIn { from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none} }
.q-number { font-size: 12px; text-transform: uppercase; letter-spacing: .1em; color: var(--muted); font-weight: 600; margin-bottom: 8px; }
.q-text { font-size: 18px; font-weight: 500; line-height: 1.6; margin-bottom: 28px; }
.q-marks { display: inline-flex; align-items: center; gap: 6px; background: var(--bg); border: 1px solid var(--border); border-radius: 20px; padding: 4px 12px; font-size: 12px; color: var(--muted); margin-bottom: 20px; }

/* MCQ Options */
.options-list { display: flex; flex-direction: column; gap: 10px; }
.option-label {
  display: flex; align-items: center; gap: 14px; padding: 16px 20px;
  background: var(--surface2); border: 2px solid var(--border); border-radius: 12px;
  cursor: pointer; transition: all .15s; font-size: 15px;
}
.option-label:hover { border-color: var(--accent); background: rgba(108,99,255,.05); }
.option-label input[type=radio], .option-label input[type=checkbox] { display: none; }
.option-label.selected { border-color: var(--accent); background: rgba(108,99,255,.08); color: var(--accent); }
.option-radio {
  width: 20px; height: 20px; border-radius: 50%; border: 2px solid var(--border);
  flex-shrink: 0; transition: all .15s; display: flex; align-items: center; justify-content: center;
}
.option-label.selected .option-radio { border-color: var(--accent); background: var(--accent); }
.option-label.selected .option-radio::after { content:'✓'; color:#fff; font-size:11px; font-weight:700; }

/* True/False */
.tf-options { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.tf-btn {
  padding: 20px; text-align: center; border-radius: 12px; border: 2px solid var(--border);
  cursor: pointer; font-size: 16px; font-weight: 600; transition: all .15s; background: var(--surface2);
}
.tf-btn:hover { border-color: var(--accent); }
.tf-btn.selected { border-color: var(--accent); background: rgba(108,99,255,.08); color: var(--accent); }

/* Short answer */
.short-input { width: 100%; padding: 16px; border: 2px solid var(--border); border-radius: 12px; font-family: 'DM Sans', sans-serif; font-size: 15px; outline: none; resize: vertical; min-height: 100px; transition: border-color .2s; background: var(--surface2); }
.short-input:focus { border-color: var(--accent); background: #fff; }

/* Nav buttons */
.q-nav-btns { display: flex; gap: 10px; margin-top: 24px; }
.btn-nav { padding: 12px 24px; border-radius: 10px; border: 2px solid var(--border); background: var(--surface); font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 500; cursor: pointer; transition: all .15s; color: var(--text); }
.btn-nav:hover { border-color: var(--accent); color: var(--accent); }
.btn-nav.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
.btn-nav.primary:hover { opacity: .9; }

/* === CHATBOX === */
.chat-toggle {
  position: fixed; bottom: 28px; right: 28px; z-index: 200;
  width: 58px; height: 58px; border-radius: 50%;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  border: none; cursor: pointer; box-shadow: 0 8px 24px rgba(108,99,255,.4);
  font-size: 24px; display: flex; align-items: center; justify-content: center;
  transition: transform .2s, box-shadow .2s;
}
.chat-toggle:hover { transform: scale(1.08); box-shadow: 0 12px 32px rgba(108,99,255,.5); }
.chat-unread {
  position: absolute; top: -4px; right: -4px; background: var(--error); color: #fff;
  font-size: 10px; font-weight: 700; width: 20px; height: 20px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center; border: 2px solid #fff;
}
.chatbox {
  position: fixed; bottom: 100px; right: 28px; z-index: 199;
  width: 360px; background: var(--surface); border: 1px solid var(--border);
  border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,.15);
  display: none; flex-direction: column; overflow: hidden;
  animation: chatSlide .3s cubic-bezier(.16,1,.3,1);
}
@keyframes chatSlide { from{opacity:0;transform:translateY(20px) scale(.95)}to{opacity:1;transform:none} }
.chatbox.open { display: flex; }
.chatbox-header {
  background: linear-gradient(135deg, var(--accent), #9b59b6);
  padding: 16px 20px; display: flex; align-items: center; gap: 10px; color: #fff;
}
.chatbox-avatar { width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,.2); display: flex; align-items: center; justify-content: center; font-size: 16px; }
.chatbox-info .name { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 14px; }
.chatbox-info .sub { font-size: 11px; opacity: .8; }
.chatbox-close { margin-left: auto; background: none; border: none; color: #fff; font-size: 20px; cursor: pointer; opacity: .8; line-height: 1; }
.chatbox-close:hover { opacity: 1; }
.chatbox-messages { height: 280px; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 10px; }
.chat-msg { max-width: 80%; }
.chat-msg.sent { align-self: flex-end; }
.chat-msg.received { align-self: flex-start; }
.chat-bubble {
  padding: 10px 14px; border-radius: 16px; font-size: 13px; line-height: 1.5;
  word-break: break-word;
}
.sent .chat-bubble { background: var(--accent); color: #fff; border-bottom-right-radius: 4px; }
.received .chat-bubble { background: var(--surface2); border: 1px solid var(--border); border-bottom-left-radius: 4px; }
.chat-time { font-size: 10px; color: var(--muted); margin-top: 3px; }
.sent .chat-time { text-align: right; }
.chatbox-input-area { padding: 12px; border-top: 1px solid var(--border); display: flex; gap: 8px; align-items: flex-end; }
.chatbox-input { flex: 1; background: var(--surface2); border: 1px solid var(--border); border-radius: 10px; padding: 10px 12px; font-family: 'DM Sans', sans-serif; font-size: 13px; outline: none; resize: none; max-height: 80px; transition: border-color .2s; }
.chatbox-input:focus { border-color: var(--accent); }
.chatbox-send { width: 36px; height: 36px; border-radius: 10px; background: var(--accent); border: none; cursor: pointer; color: #fff; font-size: 16px; flex-shrink: 0; transition: opacity .2s; display: flex; align-items: center; justify-content: center; }
.chatbox-send:hover { opacity: .85; }
.chatbox-notice { padding: 8px 16px; background: rgba(108,99,255,.06); font-size: 11px; color: var(--muted); text-align: center; }

/* Confirm modal */
.confirm-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:300;align-items:center;justify-content:center; }
.confirm-overlay.open { display:flex; }
.confirm-modal { background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:32px;max-width:420px;width:90%;text-align:center; }
.confirm-icon { font-size:48px;margin-bottom:16px; }
.confirm-title { font-family:'Syne',sans-serif;font-size:20px;font-weight:700;margin-bottom:8px; }
.confirm-sub { color:var(--muted);font-size:14px;margin-bottom:24px;line-height:1.5; }
.confirm-btns { display:flex;gap:12px;justify-content:center; }
.btn-confirm { padding:12px 28px;border-radius:10px;border:none;cursor:pointer;font-family:'Syne',sans-serif;font-size:15px;font-weight:700;transition:opacity .2s; }
.btn-confirm.primary { background:var(--accent);color:#fff; }
.btn-confirm.secondary { background:var(--surface2);border:1px solid var(--border);color:var(--text); }
.btn-confirm:hover { opacity:.85; }
</style>
</head>
<body>

<div class="topbar">
  <div class="topbar-logo">🎯 AssessPro</div>
  <div class="topbar-title">/ <?= sanitize($session['assessment_title']) ?></div>
  <div class="topbar-right">
    <div class="candidate-info">👤 <strong><?= sanitize($session['candidate_name']) ?></strong></div>
    <div class="timer" id="timer">--:--</div>
  </div>
</div>

<div class="layout">
  <!-- Question nav sidebar -->
  <div class="q-nav">
    <div class="q-nav-title">Questions</div>
    <div class="q-nav-grid" id="qNavGrid">
      <?php foreach ($questions as $i => $q): ?>
      <button class="q-btn <?= isset($answeredMap[$q['id']]) ? 'answered' : '' ?> <?= $i === 0 ? 'current' : '' ?>"
              onclick="goToQuestion(<?= $i ?>)" id="qBtn<?= $i ?>"><?= $i+1 ?></button>
      <?php endforeach; ?>
    </div>
    <div class="q-progress">
      <div class="progress-label">
        <span>Progress</span>
        <span id="progressText">0 / <?= count($questions) ?></span>
      </div>
      <div class="progress-bar"><div class="progress-fill" id="progressFill" style="width:0%"></div></div>
    </div>
    <div class="submit-section">
      <button class="btn-submit" onclick="document.getElementById('confirmModal').classList.add('open')">
        Submit Test
      </button>
    </div>
  </div>

  <!-- Main question area -->
  <div class="q-main">
    <?php foreach ($questions as $i => $q): 
      $options = json_decode($q['options'] ?? '[]', true);
      $savedAnswer = $answeredMap[$q['id']] ?? '';
    ?>
    <div class="question-card" id="qCard<?= $i ?>" style="<?= $i > 0 ? 'display:none' : '' ?>">
      <div class="q-number">Question <?= $i+1 ?> of <?= count($questions) ?></div>
      <div class="q-text"><?= nl2br(sanitize($q['question_text'])) ?></div>
      <div class="q-marks">⭐ <?= $q['marks'] ?> mark<?= $q['marks'] != 1 ? 's' : '' ?></div>

      <?php if ($q['question_type'] === 'mcq' && !empty($options)): ?>
      <div class="options-list" data-qid="<?= $q['id'] ?>" data-type="mcq">
        <?php foreach ($options as $opt): ?>
        <label class="option-label <?= $savedAnswer === $opt ? 'selected' : '' ?>" onclick="selectOption(this, '<?= $q['id'] ?>', '<?= addslashes($opt) ?>')">
          <div class="option-radio"><?php if($savedAnswer===$opt):?><?php endif;?></div>
          <input type="radio" name="q_<?= $q['id'] ?>" value="<?= htmlspecialchars($opt) ?>">
          <?= sanitize($opt) ?>
        </label>
        <?php endforeach; ?>
      </div>

      <?php elseif ($q['question_type'] === 'true_false'): ?>
      <div class="tf-options">
        <div class="tf-btn <?= $savedAnswer === 'True' ? 'selected' : '' ?>" onclick="selectTF(this, '<?= $q['id'] ?>', 'True')">✅ True</div>
        <div class="tf-btn <?= $savedAnswer === 'False' ? 'selected' : '' ?>" onclick="selectTF(this, '<?= $q['id'] ?>', 'False')">❌ False</div>
      </div>

      <?php elseif ($q['question_type'] === 'short_answer'): ?>
      <textarea class="short-input" placeholder="Type your answer here..."
                onblur="saveShortAnswer(<?= $q['id'] ?>, this.value)"
                id="short_<?= $q['id'] ?>"><?= htmlspecialchars($savedAnswer) ?></textarea>
      <?php endif; ?>

      <div class="q-nav-btns">
        <?php if ($i > 0): ?>
        <button class="btn-nav" onclick="goToQuestion(<?= $i-1 ?>)">← Previous</button>
        <?php endif; ?>
        <?php if ($i < count($questions)-1): ?>
        <button class="btn-nav primary" onclick="goToQuestion(<?= $i+1 ?>)">Next →</button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ===== CHATBOX ===== -->
<button class="chat-toggle" id="chatToggle" onclick="toggleChat()">
  💬
  <span class="chat-unread" id="chatUnread" style="display:none">0</span>
</button>

<div class="chatbox" id="chatbox">
  <div class="chatbox-header">
    <div class="chatbox-avatar">🛡️</div>
    <div class="chatbox-info">
      <div class="name">Test Support</div>
      <div class="sub">Admin is monitoring</div>
    </div>
    <button class="chatbox-close" onclick="toggleChat()">✕</button>
  </div>
  <div class="chatbox-notice">💡 Report issues or ask questions to the proctor</div>
  <div class="chatbox-messages" id="chatMessages">
    <div style="text-align:center;color:var(--muted);font-size:12px;padding:16px">
      Need help? Send a message to the exam proctor.
    </div>
  </div>
  <div class="chatbox-input-area">
    <textarea class="chatbox-input" id="chatInput" placeholder="Type a message..." rows="1"></textarea>
    <button class="chatbox-send" onclick="sendChatMessage()">➤</button>
  </div>
</div>

<!-- Confirm Submit Modal -->
<div class="confirm-overlay" id="confirmModal">
  <div class="confirm-modal">
    <div class="confirm-icon">📋</div>
    <div class="confirm-title">Submit Your Test?</div>
    <div class="confirm-sub" id="confirmSub">
      You have answered <strong id="answeredCount">0</strong> out of <strong><?= count($questions) ?></strong> questions.<br>
      Once submitted, you cannot change your answers.
    </div>
    <div class="confirm-btns">
      <button class="btn-confirm secondary" onclick="document.getElementById('confirmModal').classList.remove('open')">Review</button>
      <button class="btn-confirm primary" onclick="submitTest()">Submit →</button>
    </div>
  </div>
</div>

<script>
const SESSION_ID = <?= $session['id'] ?>;
const CANDIDATE_ID = <?= $session['cand_id'] ?>;
const TOTAL_Q = <?= count($questions) ?>;
let currentQ = 0;
let answeredQuestions = <?= json_encode(array_keys($answeredMap)) ?>;
let timeRemaining = <?= $remaining ?>;
let chatOpen = false;
let lastMessageId = 0;

// ===== Timer =====
function updateTimer() {
  if (timeRemaining <= 0) {
    document.getElementById('timer').textContent = '00:00';
    submitTest(true);
    return;
  }
  const m = Math.floor(timeRemaining / 60);
  const s = timeRemaining % 60;
  const el = document.getElementById('timer');
  el.textContent = String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
  if (timeRemaining <= 300) { el.classList.add('danger'); el.classList.remove('warning'); }
  else if (timeRemaining <= 600) { el.classList.add('warning'); el.classList.remove('danger'); }
  timeRemaining--;
}
setInterval(updateTimer, 1000);
updateTimer();

// ===== Navigation =====
function goToQuestion(idx) {
  document.getElementById('qCard' + currentQ).style.display = 'none';
  document.getElementById('qBtn' + currentQ).classList.remove('current');
  currentQ = idx;
  document.getElementById('qCard' + idx).style.display = 'block';
  document.getElementById('qBtn' + idx).classList.add('current');
}

// ===== Answering =====
function selectOption(label, qid, answer) {
  const parent = label.parentElement;
  parent.querySelectorAll('.option-label').forEach(l => l.classList.remove('selected'));
  label.classList.add('selected');
  saveAnswer(qid, answer);
}

function selectTF(btn, qid, answer) {
  btn.parentElement.querySelectorAll('.tf-btn').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
  saveAnswer(qid, answer);
}

function saveShortAnswer(qid, answer) {
  if (answer.trim()) saveAnswer(qid, answer.trim());
}

async function saveAnswer(qid, answer) {
  const res = await fetch('../php/save_answer.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ session_id: SESSION_ID, question_id: qid, answer })
  });
  const data = await res.json();
  if (data.success) {
    if (!answeredQuestions.includes(parseInt(qid))) {
      answeredQuestions.push(parseInt(qid));
    }
    updateProgress();
    // Mark nav button
    const qCards = document.querySelectorAll('.question-card');
    qCards.forEach((card, i) => {
      if (card.contains(document.querySelector('[data-qid="'+qid+'"]')) || 
          card.id === 'qCard' + getQIndexByQid(qid)) {
        const btn = document.getElementById('qBtn' + getQIndexByQid(qid));
        if (btn) btn.classList.add('answered');
      }
    });
  }
}

function getQIndexByQid(qid) {
  const cards = document.querySelectorAll('.question-card');
  for (let i = 0; i < cards.length; i++) {
    if (cards[i].querySelector('[data-qid="'+qid+'"]') || 
        cards[i].querySelector('#short_' + qid)) return i;
  }
  return -1;
}

function updateProgress() {
  const count = answeredQuestions.length;
  document.getElementById('progressText').textContent = count + ' / ' + TOTAL_Q;
  document.getElementById('progressFill').style.width = (count / TOTAL_Q * 100) + '%';
  document.getElementById('answeredCount').textContent = count;
}
updateProgress();

// ===== Submit =====
async function submitTest(auto = false) {
  document.getElementById('confirmModal').classList.remove('open');
  const res = await fetch('../php/submit_test.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ session_id: SESSION_ID, auto })
  });
  const data = await res.json();
  if (data.success) {
    window.location.href = '../candidate/results.php?token=<?= $token ?>';
  }
}

document.getElementById('confirmModal').addEventListener('click', function(e) {
  if (e.target === this) this.classList.remove('open');
});

// ===== CHATBOX =====
function toggleChat() {
  chatOpen = !chatOpen;
  const box = document.getElementById('chatbox');
  if (chatOpen) {
    box.classList.add('open');
    document.getElementById('chatToggle').textContent = '✕';
    document.getElementById('chatUnread').style.display = 'none';
    document.getElementById('chatUnread').textContent = '0';
    loadChatMessages();
  } else {
    box.classList.remove('open');
    document.getElementById('chatToggle').innerHTML = '💬<span class="chat-unread" id="chatUnread" style="display:none">0</span>';
  }
}

async function sendChatMessage() {
  const input = document.getElementById('chatInput');
  const msg = input.value.trim();
  if (!msg) return;
  input.value = '';

  const res = await fetch('../php/send_chat.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ session_id: SESSION_ID, message: msg, sender_type: 'candidate', sender_id: CANDIDATE_ID })
  });
  const data = await res.json();
  if (data.success) loadChatMessages();
}

document.getElementById('chatInput').addEventListener('keydown', function(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChatMessage(); }
});

async function loadChatMessages() {
  const res = await fetch('../php/get_messages.php?session_id=' + SESSION_ID + '&viewer=candidate');
  const data = await res.json();
  if (!data.messages) return;

  const container = document.getElementById('chatMessages');
  if (data.messages.length === 0) {
    container.innerHTML = '<div style="text-align:center;color:var(--muted);font-size:12px;padding:16px">Need help? Send a message to the exam proctor.</div>';
    return;
  }

  container.innerHTML = data.messages.map(m => `
    <div class="chat-msg ${m.sender_type === 'candidate' ? 'sent' : 'received'}">
      <div class="chat-bubble">${escHtml(m.message).replace(/\n/g,'<br>')}</div>
      <div class="chat-time">${m.time}</div>
    </div>
  `).join('');
  container.scrollTop = container.scrollHeight;

  // Show unread badge if chat is closed
  if (!chatOpen && data.unread_from_admin > 0) {
    const badge = document.getElementById('chatUnread');
    if (badge) { badge.textContent = data.unread_from_admin; badge.style.display = 'flex'; }
  }
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Poll for new messages every 5s
setInterval(loadChatMessages, 5000);
loadChatMessages();

// Anti-cheat: detect tab switch
document.addEventListener('visibilitychange', function() {
  if (document.hidden) {
    fetch('../php/log_flag.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ session_id: SESSION_ID, flag_type: 'tab_switch', description: 'Candidate switched tabs' })
    });
  }
});

</script>

<!-- TensorFlow.js & COCO-SSD Model - MUST LOAD FIRST -->
<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.11.0"></script>
<script src="https://cdn.jsdelivr.net/npm/@tensorflow-models/coco-ssd@2.2.3"></script>

<script>
// TEST: Verify this script block is executing
console.clear();
console.log('═══════════════════════════════════════════');
console.log('🚀 PROCTORING SCRIPT LOADING');
console.log('═══════════════════════════════════════════');
console.log('Time:', new Date().toLocaleTimeString());
console.log('readyState:', document.readyState);
console.log('URL:', window.location.href);

// Show alert so user knows script ran
alert('⚡ Proctoring Script Loaded!\n\nCheck Console (F12) for status');

window.proctoringScriptLoaded = true;
console.log('✅ PROCTORING SCRIPT BLOCK LOADED AND EXECUTING');
console.log('window.proctoringScriptLoaded =', window.proctoringScriptLoaded);

// ===== AI PROCTORING (TensorFlow.js) =====
let proctoringModel = null;
let proctoringActive = false;
let violationCount = 0;
const MAX_VIOLATIONS = 3;
const VIOLATION_CHECK_INTERVAL = 5000; // 5 seconds

console.log('✅ Global variables initialized');

// Wait for TensorFlow to load
function waitForTensorFlow() {
  console.log('⏳ [PROCTORING] waitForTensorFlow() called');
  console.log('[PROCTORING] Current: typeof tf=' + (typeof tf) + ', typeof cocoSsd=' + (typeof cocoSsd));
  
  return new Promise((resolve) => {
    console.log('⏳ [PROCTORING] Waiting for TensorFlow libraries...');
    
    let attempts = 0;
    const checkInterval = setInterval(() => {
      attempts++;
      const hasTf = typeof tf !== 'undefined';
      const hasCocoSsd = typeof cocoSsd !== 'undefined';
      
      if (attempts % 5 === 0) {
        console.log(`[PROCTORING] Attempt ${attempts}: tf=${hasTf}, cocoSsd=${hasCocoSsd}`);
      }
      
      if (hasTf && hasCocoSsd) {
        clearInterval(checkInterval);
        console.log('✅ [PROCTORING] TensorFlow.js loaded');
        console.log('✅ [PROCTORING] COCO-SSD loaded');
        updateProctoringStatus('Libraries loaded ✓');
        resolve();
      }
    }, 300); // Check every 300ms instead of 100ms
    
    // Timeout after 20 seconds
    setTimeout(() => {
      clearInterval(checkInterval);
      const hasTf = typeof tf !== 'undefined';
      const hasCocoSsd = typeof cocoSsd !== 'undefined';
      console.warn(`⚠️ [PROCTORING] Library timeout after 20s (tf=${hasTf}, cocoSsd=${hasCocoSsd})`);
      updateProctoringStatus('Library timeout - continuing');
      resolve();
    }, 20000);
  });
}

function updateProctoringStatus(message) {
  console.log('📍 [STATUS]:', message);
  const status = document.getElementById('proctoringStatus');
  if (status) {
    status.textContent = message;
    console.log('[STATUS] Updated DOM element');
  } else {
    console.log('[STATUS] DOM element not found, will create it');
  }
}

async function initProctoring() {
  updateProctoringStatus('🎬 Initializing AI Proctoring...');
  console.log('🎬 [PROCTORING] initProctoring() started');
  
  // Wait for libraries to load
  console.log('📚 [PROCTORING] Waiting for libraries...');
  await waitForTensorFlow();
  
  if (typeof cocoSsd === 'undefined') {
    console.warn('⚠️ [PROCTORING] COCO-SSD still not loaded. Retrying...');
    updateProctoringStatus('⚠️ Libraries delayed - retrying...');
    // Retry with longer timeout
    await new Promise(resolve => setTimeout(resolve, 2000));
  }
  
  try {
    // Request camera with audio
    updateProctoringStatus('📷 Requesting camera access...');
    console.log('📷 [PROCTORING] Requesting camera/mic permissions...');
    
    const stream = await navigator.mediaDevices.getUserMedia({ 
      video: { width: 640, height: 480 }, 
      audio: true 
    });
    
    console.log('✅ [PROCTORING] Camera/mic access granted');
    updateProctoringStatus('✓ Camera access granted');
    
    // Create hidden video element
    const video = document.createElement('video');
    video.id = 'proctoringVideo';
    video.srcObject = stream;
    video.style.display = 'none';
    video.autoplay = true;
    video.playsInline = true;
    video.muted = false;  // Get audio from video element
    document.body.appendChild(video);
    
    console.log('✅ [PROCTORING] Video element created and attached');
    
    // Wait for video to be ready
    video.onloadedmetadata = async () => {
      updateProctoringStatus(`✓ Video: ${video.videoWidth}x${video.videoHeight}`);
      console.log(`📹 [PROCTORING] Video stream ready: ${video.videoWidth}x${video.videoHeight}`);
      
      // Load model
      try {
        updateProctoringStatus('🤖 Loading COCO-SSD model...');
        console.log('🤖 [PROCTORING] Loading COCO-SSD model...');
        
        if (typeof cocoSsd === 'undefined') {
          throw new Error('cocoSsd is not defined');
        }
        
        proctoringModel = await cocoSsd.load();
        
        console.log('✅ [PROCTORING] Model loaded successfully');
        updateProctoringStatus('✅ Proctoring Active!');
        
        proctoringActive = true;
        startProctoringAnalysis(video);
      } catch (modelErr) {
        console.error('❌ [PROCTORING] Model loading error:', modelErr);
        updateProctoringStatus('❌ Model Error: ' + modelErr.message);
      }
    };
    
    // Timeout for video.onloadedmetadata
    setTimeout(() => {
      if (!proctoringActive && video.videoWidth > 0) {
        console.log('⏱️ [PROCTORING] Video metadata loaded event timeout, triggering manually');
        video.onloadedmetadata();
      }
    }, 5000);
    
  } catch (err) {
    console.error('❌ [PROCTORING] Camera access error:', err.message, err.name);
    updateProctoringStatus('❌ Camera Error: ' + err.message);
  }
}

async function startProctoringAnalysis(video) {
  let analysisCount = 0;
  
  const analyzeFrame = async () => {
    if (!proctoringActive || !proctoringModel) return;
    
    try {
      analysisCount++;
      
      // Debug: Check what the model actually has
      if (analysisCount === 1) {
        console.log('[DEBUG] proctoringModel type:', typeof proctoringModel);
        console.log('[DEBUG] proctoringModel keys:', Object.keys(proctoringModel));
        console.log('[DEBUG] proctoringModel methods:', Object.getOwnPropertyNames(Object.getPrototypeOf(proctoringModel)));
      }
      
      // Get predictions from COCO-SSD - try different method names
      let predictions = [];
      
      if (typeof proctoringModel.estimateObjects === 'function') {
        console.log('[DETECT] Using estimateObjects()');
        predictions = await proctoringModel.estimateObjects(video, 0.4);
      } else if (typeof proctoringModel.detect === 'function') {
        console.log('[DETECT] Using detect()');
        predictions = await proctoringModel.detect(video);
      } else if (typeof proctoringModel.predict === 'function') {
        console.log('[DETECT] Using predict()');
        predictions = await proctoringModel.predict(video);
      } else {
        console.error('[ERROR] No detection method found on model!');
        console.log('[ERROR] Available methods:', Object.getOwnPropertyNames(Object.getPrototypeOf(proctoringModel)));
        return;
      }
      
      const violations = [];
      
      // Suspicious object classes that indicate cheating
      const suspiciousObjects = {
        'cell phone': 'Phone detected',
        'book': 'Book/Notes detected',
        'laptop': 'Extra laptop detected',
        'tablet': 'Tablet detected',
        'monitor': 'Extra monitor detected',
        'keyboard': 'Extra keyboard detected'
      };
      
      // Check for suspicious objects
      if (Array.isArray(predictions)) {
        predictions.forEach(pred => {
          const classLower = (pred.class || '').toLowerCase();
          for (const [key, message] of Object.entries(suspiciousObjects)) {
            if (classLower.includes(key) && (pred.score || pred.confidenceInClass || 0) > 0.5) {
              violations.push(`${message} (${Math.round((pred.score || 0) * 100)}%)`);
              break;
            }
          }
        });
      }
      
      // Count people in frame
      const personCount = Array.isArray(predictions) 
        ? predictions.filter(p => (p.class || '').toLowerCase() === 'person' && (p.score || 0) > 0.5).length 
        : 0;
      
      if (personCount === 0) {
        violations.push('No person in frame');
      } else if (personCount > 1) {
        violations.push(`Multiple people detected (${personCount})`);
      }
      
      // Log and display violations
      if (violations.length > 0) {
        await logViolations(violations, 'high');
        violationCount++;
        showViolationWarning(violations[0]);
        
        console.warn(`⚠️ Violation #${violationCount}: ${violations.join(', ')}`);
        updateProctoringStatus(`⚠️ Violation detected! (${violationCount}/${MAX_VIOLATIONS})`);
        
        // Auto-submit after max violations
        if (violationCount >= MAX_VIOLATIONS) {
          console.error('❌ Max violations reached. Submitting test...');
          updateProctoringStatus('❌ Max violations - auto-submitting');
          proctoringActive = false;
          setTimeout(() => submitTest(true), 1000);
        }
      } else if (analysisCount % 10 === 0) {
        updateProctoringStatus(`✅ Monitoring (${personCount} person, ${analysisCount} frames)`);
        console.log(`✅ Frame ${analysisCount}: Clean - ${personCount} person(s), ${predictions.length} objects detected`);
      }
      
    } catch (err) {
      console.error('Analysis error:', err);
      console.error('Error message:', err.message);
    }
    
    // Schedule next analysis
    setTimeout(analyzeFrame, VIOLATION_CHECK_INTERVAL);
  };
  
  updateProctoringStatus('🔍 Starting continuous analysis...');
  console.log('🔍 Starting continuous analysis...');
  analyzeFrame();
}

async function logViolations(violations, severity = 'medium') {
  try {
    const video = document.getElementById('proctoringVideo');
    let frame = null;
    
    // Capture frame as JPEG
    if (video && video.videoWidth > 0) {
      const canvas = document.createElement('canvas');
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(video, 0, 0);
      frame = canvas.toDataURL('image/jpeg', 0.7).split(',')[1];
    }
    
    // Send to backend
    const res = await fetch('../php/log_violation.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        session_id: SESSION_ID,
        violations: violations,
        severity: severity,
        frame: frame
      })
    });
    
    const data = await res.json();
    console.log('📝 Violation logged:', data.id);
    
  } catch (err) {
    console.error('Error logging violation:', err);
  }
}

function showViolationWarning(message) {
  const warning = document.createElement('div');
  warning.style.cssText = `
    position: fixed; top: 80px; right: 20px; 
    background: #ff6584; color: white; 
    padding: 16px 24px; border-radius: 8px;
    font-weight: 700; z-index: 9999;
    font-size: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    animation: slideIn .3s ease-out;
  `;
  warning.innerHTML = `⚠️ ${message}<br><small style="opacity:0.9">Violations: ${violationCount}/${MAX_VIOLATIONS}</small>`;
  document.body.appendChild(warning);
  setTimeout(() => warning.remove(), 4000);
}

// Initialize immediately when script loads
console.log('🚀 [INIT] Script executed, document.readyState=' + document.readyState);
console.log('[INIT] Defining startup function...');

// Use IIFE to start proctoring ASAP
(function startProctoringInit() {
  console.log('[INIT] IIFE executed');
  
  function doStartProctoring() {
    console.log('[INIT] ========== doStartProctoring() CALLED ==========');
    console.log('[INIT] Creating status indicator div...');
    
    // Show proctoring status element
    const procStatus = document.createElement('div');
    procStatus.id = 'proctoringStatus';
    procStatus.style.cssText = `
      position: fixed; top: 8px; left: 50%; transform: translateX(-50%);
      background: rgba(0,0,0,0.8); color: #fff; padding: 6px 12px;
      border-radius: 6px; font-size: 11px; z-index: 10000;
      font-family: monospace; min-width: 200px; text-align: center;
    `;
    procStatus.textContent = '⏳ Starting Proctoring...';
    
    console.log('[INIT] Status div created, attempting to append to body');
    console.log('[INIT] document.body exists?', !!document.body);
    
    try {
      document.body.appendChild(procStatus);
      console.log('✅ [INIT] Status indicator ADDED TO BODY');
    } catch(e) {
      console.log('❌ [INIT] ERROR adding to body:', e.message);
      console.log('[INIT] Retrying in 100ms...');
      setTimeout(doStartProctoring, 100);
      return;
    }
    
    // Start proctoring
    console.log('[INIT] ========== STARTING PROCTORING ==========');
    console.log('[INIT] Calling initProctoring()...');
    console.log('[INIT] typeof initProctoring =', typeof initProctoring);
    
    initProctoring().then(() => {
      console.log('✅ [INIT] initProctoring() completed successfully');
    }).catch(err => {
      console.error('❌ [INIT] initProctoring() failed:', err);
      console.error('[INIT] Error message:', err.message);
      console.error('[INIT] Error stack:', err.stack);
      updateProctoringStatus('❌ Error: ' + (err.message || err));
    });
  }
  
  // Try different triggers in order of preference
  console.log('[INIT] Checking document.readyState =', document.readyState);
  
  if (document.readyState === 'complete') {
    console.log('[INIT] Document COMPLETE - starting immediately');
    doStartProctoring();
  } else if (document.readyState === 'interactive') {
    console.log('[INIT] Document INTERACTIVE - starting immediately');
    doStartProctoring();
  } else if (document.readyState === 'loading') {
    console.log('[INIT] Document LOADING - waiting for DOMContentLoaded');
    document.addEventListener('DOMContentLoaded', function() {
      console.log('[INIT] DOMContentLoaded fired, starting proctoring');
      doStartProctoring();
    });
  } else {
    console.log('[INIT] Unknown readyState, starting anyway');
    doStartProctoring();
  }
})();

console.log('[INIT] IIFE definition complete, waiting for execution');

// Cleanup when test is submitted
const originalSubmitTest = submitTest;
window.submitTest = function(auto = false) {
  proctoringActive = false;
  const video = document.getElementById('proctoringVideo');
  if (video && video.srcObject) {
    video.srcObject.getTracks().forEach(t => t.stop());
    console.log('📹 Camera stream stopped');
  }
  originalSubmitTest(auto);
};

</script>

</body>
</html>
