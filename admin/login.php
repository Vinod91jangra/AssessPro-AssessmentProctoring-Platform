<?php
require_once '../config.php';

if (isAdminLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_company'] = $admin['company_id'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — AssessPro</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #0a0a0f;
    --surface: #12121a;
    --border: #1e1e2e;
    --accent: #6c63ff;
    --accent2: #ff6584;
    --text: #e8e8f0;
    --muted: #6b6b80;
    --success: #43e97b;
    --error: #ff6584;
  }
  body {
    background: var(--bg);
    font-family: 'DM Sans', sans-serif;
    color: var(--text);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }
  .bg-grid {
    position: fixed; inset: 0; z-index: 0;
    background-image: linear-gradient(rgba(108,99,255,.06) 1px, transparent 1px),
                      linear-gradient(90deg, rgba(108,99,255,.06) 1px, transparent 1px);
    background-size: 48px 48px;
  }
  .glow {
    position: fixed;
    width: 600px; height: 600px;
    border-radius: 50%;
    filter: blur(120px);
    z-index: 0;
    pointer-events: none;
  }
  .glow-1 { background: rgba(108,99,255,.15); top: -200px; right: -100px; }
  .glow-2 { background: rgba(255,101,132,.1); bottom: -200px; left: -100px; }
  .login-card {
    position: relative; z-index: 1;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 52px 48px;
    width: 100%; max-width: 440px;
    box-shadow: 0 40px 80px rgba(0,0,0,.5);
    animation: slideUp .5s cubic-bezier(.16,1,.3,1);
  }
  @keyframes slideUp {
    from { opacity: 0; transform: translateY(24px); }
    to { opacity: 1; transform: translateY(0); }
  }
  .logo { display: flex; align-items: center; gap: 12px; margin-bottom: 36px; }
  .logo-icon {
    width: 44px; height: 44px; border-radius: 12px;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
  }
  .logo-text { font-family: 'Syne', sans-serif; font-size: 22px; font-weight: 800; letter-spacing: -0.5px; }
  h1 { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 700; margin-bottom: 8px; }
  .subtitle { color: var(--muted); font-size: 14px; margin-bottom: 32px; }
  .field { margin-bottom: 20px; }
  label { display: block; font-size: 12px; font-weight: 500; color: var(--muted); text-transform: uppercase; letter-spacing: .08em; margin-bottom: 8px; }
  input {
    width: 100%; background: var(--bg); border: 1px solid var(--border);
    border-radius: 12px; padding: 14px 16px; color: var(--text);
    font-family: 'DM Sans', sans-serif; font-size: 15px;
    transition: border-color .2s, box-shadow .2s;
    outline: none;
  }
  input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(108,99,255,.15); }
  .error-msg {
    background: rgba(255,101,132,.1); border: 1px solid rgba(255,101,132,.3);
    border-radius: 10px; padding: 12px 16px; font-size: 13px; color: var(--error);
    margin-bottom: 20px;
  }
  .btn {
    width: 100%; padding: 15px; border-radius: 12px; border: none; cursor: pointer;
    font-family: 'Syne', sans-serif; font-size: 16px; font-weight: 600;
    background: linear-gradient(135deg, var(--accent), #9b59b6);
    color: #fff; transition: opacity .2s, transform .1s;
    letter-spacing: .02em;
  }
  .btn:hover { opacity: .9; }
  .btn:active { transform: scale(.99); }
  .demo-hint {
    margin-top: 24px; text-align: center; font-size: 12px; color: var(--muted);
    border-top: 1px solid var(--border); padding-top: 20px;
  }
  .demo-hint code {
    background: var(--border); padding: 2px 6px; border-radius: 4px; font-size: 11px;
  }
</style>
</head>
<body>
<div class="bg-grid"></div>
<div class="glow glow-1"></div>
<div class="glow glow-2"></div>
<div class="login-card">
  <div class="logo">
    <div class="logo-icon">🎯</div>
    <span class="logo-text">AssessPro</span>
  </div>
  <h1>Admin Login</h1>
  <p class="subtitle">Sign in to manage assessments and candidates</p>
  <?php if ($error): ?>
  <div class="error-msg">⚠️ <?= $error ?></div>
  <?php endif; ?>
  <form method="POST">
    <div class="field">
      <label>Email Address</label>
      <input type="email" name="email" placeholder="admin@company.com" required value="<?= sanitize($_POST['email'] ?? '') ?>">
    </div>
    <div class="field">
      <label>Password</label>
      <input type="password" name="password" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn">Sign In →</button>
  </form>
  <div class="demo-hint">
    Demo: <code>admin@assesspro.com</code> / <code>password</code><br>
    Company HR: <code>hr@techcorp.com</code> / <code>password</code>
  </div>
</div>
</body>
</html>
