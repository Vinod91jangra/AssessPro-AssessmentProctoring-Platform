# 🎯 AssessPro — Candidate Assessment Platform

A full-featured PHP assessment platform with live chat support.

---

## ✨ Features

### For Admins
- **Dashboard** — Stats: assessments, sessions, live count, unread chats
- **Assessment Builder** — Create assessments with title, duration, pass score
- **Question Builder** — MCQ, True/False, Short Answer question types with marks
- **Candidate Invites** — Generate unique test links per candidate
- **Live Chat Support** — Real-time chat with candidates during their test
- **Results & Scores** — Automatic scoring for MCQ/T-F, percentage, pass/fail
- **Proctoring Flags** — Tab-switch detection logged automatically
- **Company Management** — Super admin manages multiple companies + HR admins

### For Candidates
- **Timed Test Interface** — Countdown timer with color warnings
- **Question Navigator** — Visual grid showing answered/unanswered questions
- **Auto-save** — Answers saved on selection
- **💬 Live Chatbox** — Floating chat button to message the proctor
  - Real-time polling (every 5 seconds)
  - Unread message badge notification
  - Proctor replies shown instantly
- **Results Page** — Animated score circle, pass/fail badge, time taken

---

## 🚀 Installation

### Requirements
- PHP 7.4+ with PDO extension
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx with mod_rewrite

### Steps

1. **Clone/copy** the `assessment/` folder to your web root (e.g. `/var/www/html/assessment`)

2. **Create database** and import schema:
   ```bash
   mysql -u root -p < db_schema.sql
   ```

3. **Edit config** in `config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_db_user');
   define('DB_PASS', 'your_db_password');
   define('DB_NAME', 'assessment_platform');
   define('APP_URL', 'http://yourdomain.com/assessment');
   ```

4. **Set permissions**:
   ```bash
   chmod -R 755 assessment/
   ```

5. **Visit** `http://localhost/assessment/admin/login.php`

---

## 🔐 Default Credentials

| Role | Email | Password |
|------|-------|----------|
| Super Admin | admin@assesspro.com | password |
| Company HR (TechCorp) | hr@techcorp.com | password |

> ⚠️ **Change these immediately in production!**

---

## 📁 File Structure

```
assessment/
├── config.php                  # Database & app config
├── db_schema.sql               # Full MySQL schema
│
├── admin/
│   ├── login.php               # Admin login
│   ├── logout.php              # Session destroy
│   ├── dashboard.php           # Main dashboard
│   ├── assessments.php         # Manage assessments
│   ├── questions.php           # Question builder
│   ├── invite.php              # Invite candidates, view sessions
│   ├── chat.php                # Live chat with candidates
│   └── sessions.php            # All test sessions
│
├── candidate/
│   ├── test.php                # Test-taking interface with chatbox
│   └── results.php             # Score results page
│
└── php/
    ├── send_chat.php           # API: Send chat message
    ├── get_messages.php        # API: Fetch messages (polling)
    ├── save_answer.php         # API: Save candidate answer
    ├── submit_test.php         # API: Submit and grade test
    └── log_flag.php            # API: Log proctoring flags
```

---

## 🔄 Workflow

```
1. Admin creates Assessment
2. Admin adds Questions
3. Admin activates Assessment
4. Admin invites Candidate → unique token URL generated
5. Candidate opens link → timed test begins
6. Candidate can chat with admin during test via 💬 chatbox
7. Admin replies in real-time from admin/chat.php
8. Candidate submits → auto-graded → results shown
9. Admin views results in sessions/results pages
```

---

## 💬 Chat Flow

The chatbox uses a **polling architecture** (no WebSockets required):
- Candidate sends message → `POST /php/send_chat.php`
- Admin chat page polls every **3 seconds** for new messages
- Candidate test page polls every **5 seconds**
- Unread badge appears on chat toggle button when admin replies
- All messages marked as read when viewed

---

## 🛡️ Security Notes

- Tokens are 64-char random hex strings
- All user input sanitized with `htmlspecialchars`
- PDO prepared statements throughout (SQL injection safe)
- Session-based admin authentication
- Anti-cheat: tab-switch events logged to `proctoring_flags` table

---

## 🔮 Extending

To add WebSockets for true real-time chat, replace the polling in `test.php` and `chat.php` with a Ratchet/Swoole WebSocket server, keeping the same `chat_messages` DB table as the persistence layer.
