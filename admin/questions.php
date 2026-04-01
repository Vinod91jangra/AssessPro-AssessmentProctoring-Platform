<?php
require_once '../config.php';
requireAdmin();
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: assessments.php'); exit; }

$assessment = $db->prepare("SELECT a.*, c.name as company_name FROM assessments a JOIN companies c ON a.company_id=c.id WHERE a.id=?");
$assessment->execute([$id]);
$assessment = $assessment->fetch();
if (!$assessment) { header('Location: assessments.php'); exit; }

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_question') {
        $text = sanitize($_POST['question_text'] ?? '');
        $type = $_POST['question_type'] ?? 'mcq';
        $marks = (int)($_POST['marks'] ?? 1);
        $correct = sanitize($_POST['correct_answer'] ?? '');
        
        $options = null;
        $error = '';
        
        // Debug logging
        error_log("Question submitted: type=$type, correct=$correct, text=$text");
        
        if ($type === 'mcq') {
            $opts = array_filter(array_map('trim', [
                $_POST['opt1'] ?? '', $_POST['opt2'] ?? '',
                $_POST['opt3'] ?? '', $_POST['opt4'] ?? ''
            ]));
            $options = json_encode(array_values($opts));
            
            if (empty($correct)) {
                $error = 'Correct answer is required for MCQ.';
            } else {
                // Match correct answer with options
                $optsArray = array_values($opts);
                $correctMatch = false;
                foreach ($optsArray as $opt) {
                    if (strtolower(trim($opt)) === strtolower(trim($correct))) {
                        $correct = $opt;
                        $correctMatch = true;
                        break;
                    }
                }
                if (!$correctMatch) {
                    $error = 'Correct answer must match one of the options.';
                }
            }
        } elseif ($type === 'true_false') {
            $options = json_encode(['True', 'False']);
            if ($correct !== 'True' && $correct !== 'False') {
                $error = 'Correct answer must be "True" or "False".';
            }
        } else {
            // Short answer
            if (empty($correct)) {
                $error = 'Expected answer is required.';
            }
        }
        
        if (!$text) {
            $error = 'Question text is required.';
        }
        
        if (!$error) {
            $db->prepare("INSERT INTO questions (assessment_id, question_text, question_type, options, correct_answer, marks) VALUES (?,?,?,?,?,?)")
               ->execute([$id, $text, $type, $options, $correct, $marks]);
            $success = 'Question added!';
        }
    } elseif ($action === 'delete_question') {
        $qid = (int)$_POST['qid'];
        $db->prepare("DELETE FROM questions WHERE id=? AND assessment_id=?")->execute([$qid, $id]);
        header("Location: questions.php?id=$id");
        exit;
    }
}

$questions = $db->prepare("SELECT * FROM questions WHERE assessment_id=? ORDER BY order_index ASC, id ASC");
$questions->execute([$id]);
$questions = $questions->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Questions — <?= sanitize($assessment['title']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#0a0a0f;--surface:#12121a;--surface2:#1a1a26;--border:#1e1e2e;--accent:#6c63ff;--accent2:#ff6584;--text:#e8e8f0;--muted:#6b6b80;--success:#43e97b;--error:#ff6584;--warning:#f5a623}
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
.main{margin-left:240px;flex:1;padding:36px;display:grid;grid-template-columns:1fr 400px;gap:28px;align-items:start}
.page-header{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;margin-bottom:4px}
.page-title{font-family:'Syne',sans-serif;font-size:22px;font-weight:700}
.page-sub{color:var(--muted);font-size:13px}
.btn{padding:10px 20px;border-radius:10px;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:500;background:var(--accent);color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:opacity .2s}
.btn:hover{opacity:.85}
.btn.sm{padding:6px 12px;font-size:12px}
.btn.danger{background:rgba(255,101,132,.15);border:1px solid rgba(255,101,132,.3);color:var(--error)}
.card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:24px}
.card-title{font-family:'Syne',sans-serif;font-size:16px;font-weight:700;margin-bottom:20px}
.field{margin-bottom:16px}
label{display:block;font-size:12px;font-weight:500;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px}
input,select,textarea{width:100%;background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:11px 13px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:14px;outline:none;transition:border-color .2s}
input:focus,select:focus,textarea:focus{border-color:var(--accent)}
select option{background:var(--surface)}
.opts-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
/* Question list */
.q-item{background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:18px;margin-bottom:12px}
.q-header{display:flex;align-items:flex-start;gap:10px;margin-bottom:8px}
.q-num{font-family:'Syne',sans-serif;font-weight:700;color:var(--accent);font-size:14px;flex-shrink:0}
.q-text{font-size:14px;flex:1;line-height:1.5}
.q-meta{display:flex;align-items:center;gap:10px;margin-top:8px}
.q-type{background:rgba(108,99,255,.1);color:var(--accent);padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600}
.q-marks{background:rgba(67,233,123,.1);color:var(--success);padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600}
.q-correct{background:rgba(67,233,123,.05);border:1px solid rgba(67,233,123,.2);border-radius:6px;padding:4px 10px;font-size:12px;color:var(--success)}
.alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:16px}
.alert.success{background:rgba(67,233,123,.1);border:1px solid rgba(67,233,123,.3);color:var(--success)}
.alert.error{background:rgba(255,101,132,.1);border:1px solid rgba(255,101,132,.3);color:var(--error)}
.type-toggle{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:16px}
.type-btn{padding:8px;border-radius:8px;border:2px solid var(--border);background:var(--bg);color:var(--muted);cursor:pointer;font-size:12px;font-weight:600;text-align:center;transition:all .15s}
.type-btn.active{border-color:var(--accent);background:rgba(108,99,255,.1);color:var(--accent)}
.opts-section,.tf-section,.sa-section{display:none}
.opts-section.show,.tf-section.show,.sa-section.show{display:block}
.empty{text-align:center;padding:40px;color:var(--muted);font-size:14px}
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

<main class="main" style="margin-left:240px;padding:36px;display:grid;grid-template-columns:1fr 400px;gap:28px;align-items:start">
  <div style="grid-column:1/-1">
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:4px">
      <a href="assessments.php" style="color:var(--muted);font-size:13px;text-decoration:none">← Assessments</a>
      <span style="color:var(--border)">/</span>
      <span style="font-family:'Syne',sans-serif;font-size:22px;font-weight:700"><?= sanitize($assessment['title']) ?></span>
    </div>
    <div style="color:var(--muted);font-size:13px;margin-bottom:24px"><?= count($questions) ?> question(s) · <?= $assessment['duration_minutes'] ?>min · Pass: <?= $assessment['pass_score'] ?>%</div>
    <?php if ($error): ?><div class="alert error">⚠️ <?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert success">✓ <?= $success ?></div><?php endif; ?>
  </div>

  <!-- Questions list -->
  <div>
    <?php if (empty($questions)): ?>
    <div class="empty">📝 No questions yet.<br>Add your first question →</div>
    <?php else: ?>
    <?php foreach ($questions as $i => $q): 
      $opts = json_decode($q['options'] ?? '[]', true);
    ?>
    <div class="q-item">
      <div class="q-header">
        <span class="q-num">Q<?= $i+1 ?></span>
        <div class="q-text"><?= nl2br(sanitize($q['question_text'])) ?></div>
        <form method="POST" style="flex-shrink:0" onsubmit="return confirm('Delete?')">
          <input type="hidden" name="action" value="delete_question">
          <input type="hidden" name="qid" value="<?= $q['id'] ?>">
          <button type="submit" class="btn sm danger">🗑</button>
        </form>
      </div>
      <div class="q-meta">
        <span class="q-type"><?= strtoupper(str_replace('_',' ',$q['question_type'])) ?></span>
        <span class="q-marks">⭐ <?= $q['marks'] ?> mark<?= $q['marks']!=1?'s':'' ?></span>
      </div>
      <?php if (!empty($opts)): ?>
      <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px">
        <?php foreach ($opts as $opt): ?>
        <span style="background:<?= strtolower($opt)===strtolower($q['correct_answer'])?'rgba(67,233,123,.1)':'var(--bg)' ?>;border:1px solid <?= strtolower($opt)===strtolower($q['correct_answer'])?'rgba(67,233,123,.3)':'var(--border)' ?>;border-radius:6px;padding:3px 10px;font-size:12px;color:<?= strtolower($opt)===strtolower($q['correct_answer'])?'var(--success)':'var(--muted)' ?>">
          <?= strtolower($opt)===strtolower($q['correct_answer'])?'✓ ':'' ?><?= sanitize($opt) ?>
        </span>
        <?php endforeach; ?>
      </div>
      <?php elseif ($q['question_type'] === 'short_answer'): ?>
      <div class="q-correct" style="margin-top:8px">Expected: <?= sanitize($q['correct_answer']) ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Add question form -->
  <div class="card" style="position:sticky;top:24px">
    <div class="card-title">＋ Add Question</div>
    <form method="POST" id="addForm">
      <input type="hidden" name="action" value="add_question">
      <input type="hidden" name="question_type" id="qTypeInput" value="mcq">
      <input type="hidden" name="correct_answer" id="correctAnswerHidden" value="">
      
      <div class="type-toggle">
        <div class="type-btn active" onclick="setType('mcq',this)">🔘 MCQ</div>
        <div class="type-btn" onclick="setType('true_false',this)">✅ True/False</div>
        <div class="type-btn" onclick="setType('short_answer',this)">✍️ Short Answer</div>
      </div>

      <div class="field">
        <label>Question</label>
        <textarea name="question_text" placeholder="Enter your question..." rows="3" required></textarea>
      </div>

      <div class="opts-section show" id="optsSection">
        <div class="field">
          <label>Options</label>
          <div style="display:flex;flex-direction:column;gap:8px">
            <input type="text" name="opt1" placeholder="Option A">
            <input type="text" name="opt2" placeholder="Option B">
            <input type="text" name="opt3" placeholder="Option C">
            <input type="text" name="opt4" placeholder="Option D">
          </div>
        </div>
        <div class="field">
          <label>Correct Answer</label>
          <input type="text" id="mcqCorrect" placeholder="Must match one option exactly">
        </div>
      </div>

      <div class="tf-section" id="tfSection">
        <div class="field">
          <label>Correct Answer</label>
          <select id="tfCorrect">
            <option value="">Select...</option>
            <option value="True">True</option>
            <option value="False">False</option>
          </select>
        </div>
      </div>

      <div class="sa-section" id="saSection">
        <div class="field">
          <label>Expected Answer</label>
          <input type="text" id="saCorrect" placeholder="Keywords or exact answer">
        </div>
      </div>

      <div class="field">
        <label>Marks</label>
        <input type="number" name="marks" value="1" min="1" max="100">
      </div>
      <button type="submit" class="btn" style="width:100%">Add Question</button>
    </form>
  </div>
</main>

<script>
function setType(type, btn) {
  document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('qTypeInput').value = type;
  document.getElementById('optsSection').className = 'opts-section' + (type==='mcq'?' show':'');
  document.getElementById('tfSection').className = 'tf-section' + (type==='true_false'?' show':'');
  document.getElementById('saSection').className = 'sa-section' + (type==='short_answer'?' show':'');
}

// Consolidate answers before submit
document.getElementById('addForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const type = document.getElementById('qTypeInput').value;
  const text = document.querySelector('textarea[name="question_text"]').value.trim();
  
  let correct = '';
  if (type === 'mcq') {
    correct = document.getElementById('mcqCorrect').value.trim();
  } else if (type === 'true_false') {
    correct = document.getElementById('tfCorrect').value.trim();
  } else if (type === 'short_answer') {
    correct = document.getElementById('saCorrect').value.trim();
  }
  
  // Validation
  if (!text) {
    alert('⚠️ Question text is required');
    return;
  }
  
  if (!correct) {
    alert('⚠️ Correct answer is required');
    return;
  }
  
  // Set the hidden field and submit
  document.getElementById('correctAnswerHidden').value = correct;
  this.submit();
});
</script>
</body>
</html>
