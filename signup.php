<?php
session_start();

/* ── DB helper ───────────────────────────────────────────── */
function db(){
  $db = new PDO('sqlite:' . __DIR__ . '/calendar.db');
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $db->exec("
    PRAGMA foreign_keys = ON;
    CREATE TABLE IF NOT EXISTS users(
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      email TEXT UNIQUE NOT NULL,
      password TEXT NOT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    CREATE TABLE IF NOT EXISTS availability(
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL,
      weekday INTEGER NOT NULL,     -- 0=Sun .. 6=Sat
      start_time TEXT NOT NULL,     -- '09:00'
      end_time   TEXT NOT NULL,     -- '17:30'
      FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    );
    CREATE TABLE IF NOT EXISTS bookings(
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL,
      guest_name  TEXT NOT NULL,
      guest_email TEXT NOT NULL,
      date TEXT NOT NULL,           -- 'YYYY-MM-DD'
      time TEXT NOT NULL,           -- 'HH:MM'
      status TEXT DEFAULT 'booked', -- booked|cancelled|past
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    );
  ");
  return $db;
}

$msg = "";
if ($_SERVER['REQUEST_METHOD']==='POST'){
  try {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass = $_POST['password'] ?? '';

    if (!$name || !$email || !$pass) throw new Exception('All fields are required.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Invalid email.');

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $st = db()->prepare("INSERT INTO users(name,email,password) VALUES(?,?,?)");
    $st->execute([$name,$email,$hash]);

    $msg = "Signup successful! Please log in.";
    echo "<script>alert('".addslashes($msg)."'); location.href='login.php';</script>";
    exit;
  } catch(Exception $e){
    $msg = $e->getMessage();
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign Up — Calendy</title>
<style>
:root{--bg:#0f172a;--panel:#0b1220;--muted:#94a3b8;--acc:#22d3ee;--acc2:#a78bfa;--err:#ef4444}
*{box-sizing:border-box} body{margin:0;font-family:Inter,system-ui,Segoe UI,Roboto,Arial;background:
radial-gradient(800px 480px at 85% -10%,#38bdf8,transparent),
radial-gradient(700px 460px at -15% 110%,#a78bfa22,transparent),
var(--bg);color:#e5e7eb;min-height:100vh;display:grid;place-items:center}
.card{width:min(440px,92%);background:linear-gradient(180deg,#0b1220,#0a0f1a);border:1px solid #1f2937;
border-radius:16px;box-shadow:0 12px 50px rgba(34,211,238,.18);padding:28px 24px}
h1{margin:0 0 8px;font-size:28px;font-weight:800;letter-spacing:.2px}
p{margin:0 0 18px;color:var(--muted)}
label{display:block;margin:14px 0 6px;color:#cbd5e1;font-size:13px}
input{width:100%;padding:12px 14px;border:1px solid #243041;background:#0b1320;color:#e5e7eb;border-radius:12px;outline:none;transition:.2s}
input:focus{border-color:var(--acc);box-shadow:0 0 0 4px #22d3ee22}
button{width:100%;margin-top:16px;padding:12px 14px;border:0;border-radius:12px;background:
linear-gradient(90deg,var(--acc),var(--acc2));color:#071018;font-weight:800;cursor:pointer;transition:.2s}
button:hover{transform:translateY(-1px);filter:saturate(1.15)}
a{color:#67e8f9;text-decoration:none;display:block;text-align:center;margin-top:14px}
.alert{margin-top:4px;color:var(--err);font-size:13px}
.logo{display:flex;align-items:center;gap:10px;margin-bottom:8px}
.badge{font-size:12px;color:#0f172a;background:#67e8f9;border-radius:999px;padding:4px 10px;font-weight:700}
</style>
</head>
<body>
  <div class="card">
    <div class="logo">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none"><path d="M7 3v4m10-4v4M4 10h16M5 8h14a2 2 0 0 1 2 2v7a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4v-7a2 2 0 0 1 2-2Z" stroke="#67e8f9" stroke-width="1.5"/></svg>
      <span class="badge">Calendy</span>
    </div>
    <h1>Create your account</h1>
    <p>Set availability, share a link, and get booked in minutes.</p>
    <?php if($msg): ?><div class="alert"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form method="post">
      <label>Name</label><input type="text" name="name" required>
      <label>Email</label><input type="email" name="email" required>
      <label>Password</label><input type="password" name="password" minlength="6" required>
      <button>Sign up</button>
    </form>
    <a href="login.php">Already have an account? Log in</a>
  </div>
</body>
</html>
