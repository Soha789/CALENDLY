<?php
session_start();

/* ── DB helper ─────────────────────────────── */
function db(){ $db=new PDO('sqlite:' . __DIR__ . '/calendar.db'); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); return $db; }

$msg = "";
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $email = strtolower(trim($_POST['email']??''));
  $pass  = $_POST['password'] ?? '';
  if ($email && $pass){
    $st = db()->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
    $st->execute([$email]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if ($u && password_verify($pass, $u['password'])){
      $_SESSION['uid']   = $u['id'];
      $_SESSION['uname'] = $u['name'];
      echo "<script>alert('Welcome, ".addslashes($u['name'])."!'); location.href='index.php';</script>";
      exit;
    } else {
      $msg = "Invalid email or password.";
    }
  } else { $msg = "All fields are required."; }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log In — Calendy</title>
<style>
:root{--bg:#0f172a;--muted:#94a3b8;--acc:#22d3ee;--ok:#22c55e;--err:#ef4444}
*{box-sizing:border-box} body{margin:0;font-family:Inter,system-ui,Segoe UI,Roboto;background:
radial-gradient(780px 470px at 85% -10%,#38bdf8,transparent),var(--bg);color:#e5e7eb;min-height:100vh;display:grid;place-items:center}
.box{width:min(440px,92%);background:linear-gradient(180deg,#0b1220,#0a0f1a);border:1px solid #1f2937;border-radius:16px;box-shadow:0 12px 50px rgba(34,211,238,.18);padding:28px 24px}
h1{margin:0 0 6px;font-size:28px;font-weight:800}
p{margin:0 0 18px;color:var(--muted)}
label{display:block;margin:14px 0 6px;color:#cbd5e1;font-size:13px}
input{width:100%;padding:12px 14px;border:1px solid #243041;background:#0b1320;color:#e5e7eb;border-radius:12px;outline:none;transition:.2s}
input:focus{border-color:var(--acc);box-shadow:0 0 0 4px #22d3ee22}
button{width:100%;margin-top:16px;padding:12px 14px;border:0;border-radius:12px;background:
linear-gradient(90deg,var(--acc),var(--ok));color:#071018;font-weight:800;cursor:pointer;transition:.2s}
button:hover{transform:translateY(-1px);filter:saturate(1.15)}
a{color:#67e8f9;text-decoration:none;display:block;text-align:center;margin-top:14px}
.alert{margin-top:4px;color:var(--err);font-size:13px}
.top{display:flex;align-items:center;justify-content:space-between}
</style>
</head>
<body>
  <div class="box">
    <div class="top">
      <h1>Welcome back</h1>
      <a href="signup.php" style="font-size:12px">Create account</a>
    </div>
    <p>Log in to manage your availability and bookings.</p>
    <?php if($msg): ?><div class="alert"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form method="post">
      <label>Email</label><input type="email" name="email" required>
      <label>Password</label><input type="password" name="password" required>
      <button>Log in</button>
    </form>
  </div>
</body>
</html>
