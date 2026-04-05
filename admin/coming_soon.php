
<?php
//coming soon page
// admin/coming_soon.php
require_once '../config.php';
requireAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Coming Soon — AssessPro</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#0a0a0f;--surface:#12121a;--border:#1e1e2e;--accent:#6c63ff;--accent2:#ff6584;--text:#e8e8f0;--muted:#6b6b80}
body{background:var(--bg);font-family:'DM Sans',sans-serif;color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center}

.container{text-align:center;padding:40px;max-width:500px}

.icon{font-size:80px;margin-bottom:24px;animation:bounce 2s infinite}

@keyframes bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}

h1{font-family:'Syne',sans-serif;font-size:32px;font-weight:700;margin-bottom:12px;color:var(--accent)}

.subtitle{font-size:16px;color:var(--muted);margin-bottom:32px;line-height:1.6}

.status{display:inline-block;background:rgba(108,99,255,.15);border:1px solid rgba(108,99,255,.3);color:var(--accent);padding:8px 16px;border-radius:20px;font-size:13px;font-weight:600;margin-bottom:32px}

.progress-bar{width:100%;height:6px;background:var(--border);border-radius:10px;overflow:hidden;margin-bottom:32px}

.progress-fill{height:100%;background:linear-gradient(90deg,var(--accent),var(--accent2));width:60%;animation:growProgress 3s infinite}

@keyframes growProgress{0%{width:20%}50%{width:80%}100%{width:20%}}

.btn{display:inline-block;padding:12px 32px;background:var(--accent);color:#fff;border:none;border-radius:10px;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:600;text-decoration:none;transition:opacity .2s;margin-top:24px}

.btn:hover{opacity:.85}

.features{margin-top:48px;padding-top:32px;border-top:1px solid var(--border);text-align:left}

.feature{display:flex;align-items:flex-start;gap:12px;margin-bottom:16px}

.feature-icon{font-size:20px;flex-shrink:0}

.feature-text{color:var(--muted);font-size:13px;line-height:1.6}

.feature-text strong{color:var(--text);display:block;margin-bottom:4px}
</style>
</head>
<body>

<div class="container">
  <div class="icon">🚀</div>
  
  <h1>Coming Very Soon</h1>
  
  <div class="status">⏳ In Development Phase</div>
  
  <p class="subtitle">
    This feature is currently under development. We're working hard to bring you an amazing experience!
  </p>
  
  <div class="progress-bar">
    <div class="progress-fill"></div>
  </div>
  
  <p class="subtitle" style="font-size:13px;margin-bottom:0">
    Expected release: Soon
  </p>
  
  <a href="dashboard.php" class="btn">← Back to Dashboard</a>
  
  <div class="features">
    <div class="feature">
      <div class="feature-icon">✓</div>
      <div class="feature-text">
        <strong>Assessment Builder</strong>
        Create and manage assessments
      </div>
    </div>
    
    <div class="feature">
      <div class="feature-icon">✓</div>
      <div class="feature-text">
        <strong>Question Management</strong>
        Add MCQ, True/False, and Short Answer questions
      </div>
    </div>
    
    <div class="feature">
      <div class="feature-icon">✓</div>
      <div class="feature-text">
        <strong>Candidate Invitations</strong>
        Generate and share test links
      </div>
    </div>
    
    <div class="feature">
      <div class="feature-icon">✓</div>
      <div class="feature-text">
        <strong>Live Chat Support</strong>
        Chat with candidates during tests
      </div>
    </div>
    
    <div class="feature">
      <div class="feature-icon">⏳</div>
      <div class="feature-text">
        <strong>Candidate Management</strong>
        View and manage all candidates (Coming Soon)
      </div>
    </div>
    
    <div class="feature">
      <div class="feature-icon">⏳</div>
      <div class="feature-text">
        <strong>Test Sessions</strong>
        Monitor and track all test sessions (Coming Soon)
      </div>
    </div>
  </div>
</div>

</body>
</html>
