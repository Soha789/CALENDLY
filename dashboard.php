<?php
session_start();

/* ── DB helper ─────────────────────────────── */
function db(){ $db=new PDO('sqlite:' . __DIR__ . '/calendar.db'); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); return $db; }
function requireLogin(){ if(!isset($_SESSION['uid'])){ echo "<script>alert('Please log in first.'); location.href='login.php';</script>"; exit; } }

/* ── Logout via JS-submitted form ──────────── */
if (isset($_POST['__logout'])){
  session_destroy();
  echo "<script>alert('Logged out.'); location.href='login.php';</script>"; exit;
}

requireLogin();
$db = db();
$uid = $_SESSION['uid'];

/* ── Cancel booking ───────────────────────── */
if (isset($_GET['cancel'])){
  $bid = (int)$_GET['cancel'];
  $st = $db->prepare("UPDATE bookings SET status='cancelled' WHERE id=? AND user_id=?");
  $st->execute([$bid,$uid]);
  echo "<script>alert('Booking cancelled.'); location.href='dashboard.php';</script>"; exit;
}

/* ── Reschedule booking ───────────────────── */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['__act']) && $_POST['__act']==='reschedule'){
  $bid = (int)($_POST['booking_id'] ?? 0);
  $date= trim($_POST['date'] ?? '');
  $time= trim($_POST['time'] ?? '');
  if ($bid && $date && $time){
    // check conflict
    $chk=$db->prepare("SELECT COUNT(*) FROM bookings WHERE user_id=? AND date=? AND time=? AND status='booked' AND id<>?");
    $chk->execute([$uid,$date,$time,$bid]);
    if ($chk->fetchColumn()>0){
      echo "<script>alert('That slot is already booked.'); history.back();</script>"; exit;
    }
    $up=$db->prepare("UPDATE bookings SET date=?, time=? WHERE id=? AND user_id=?");
    $up->execute([$date,$time,$bid,$uid]);
    echo "<script>alert('Rescheduled.'); location.href='dashboard.php';</script>"; exit;
  } else {
    echo "<script>alert('Please pick date/time.'); history.back();</script>"; exit;
  }
}

/* ── Pull data ────────────────────────────── */
$you = $db->query("SELECT id,name,email FROM users WHERE id={$uid}")->fetch(PDO::FETCH_ASSOC);
$bookings = $db->prepare("SELECT * FROM bookings WHERE user_id=? ORDER BY date ASC, time ASC");
$bookings->execute([$uid]);
$rows = $bookings->fetchAll(PDO::FETCH_ASSOC);

$availability = $db->prepare("SELECT * FROM availability WHERE user_id=? ORDER BY weekday,start_time");
$availability->execute([$uid]); $avs=$availability->fetchAll(PDO::FETCH_ASSOC);
$days=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard — Calendy</title>
<style>
:root{--bg:#0f172a;--panel:#0b1220;--muted:#94a3b8;--acc:#22d3ee;--acc2:#a78bfa;--ok:#22c55e;--bad:#ef4444;--line:#1f2937}
*{box-sizing:border-box} body{margin:0;font-family:Inter,system-ui,Segoe UI,Roboto,Arial;background:
radial-gradient(900px 560px at 85% -10%,#38bdf8,transparent),
radial-gradient(780px 520px at -15% 110%,#a78bfa22,transparent),
var(--bg);color:#e5e7eb;min-height:100vh}
.header{padding:18px 22px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between}
.brand{display:flex;align-items:center;gap:10px;font-weight:800}
.nav a{color:#67e8f9;text-decoration:none;margin-left:14px}
.container{max-width:1100px;margin:0 auto;padding:22px;display:grid;gap:18px}
.card{background:linear-gradient(180deg,#0b1220,#0a0f1a);border:1px solid var(--line);border-radius:16px;box-shadow:0 12px 50px rgba(34,211,238,.12);padding:20px}
h2{margin:0 0 10px}
.p{color:var(--muted);margin:0 0 12px}
.table{width:100%;border-collapse:collapse}
.table th,.table td{border-bottom:1px solid var(--line);padding:10px 8px;text-align:left;font-size:14px}
.badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#0ea5e9;color:#00151b;font-weight:800;font-size:12px}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.btn{display:inline-block;padding:8px 12px;border-radius:10px;border:1px solid #1f3c4f;background:
linear-gradient(90deg,var(--acc),var(--acc2));color:#071018;font-weight:800;text-decoration:none}
.btn.bad{background:linear-gradient(90deg,#fca5a5,#ef4444);color:#180a0a}
.btn.min{padding:6px 10px;font-size:12px}
input,select{width:100%;padding:10px 12px;border:1px solid #243041;background:#0b1320;color:#e5e7eb;border-radius:12px;outline:none}
.copy{display:flex;gap:8px;margin-top:10px}
.copy input{flex:1}
footer{color:#64748b;text-align:center;padding:24px}
@media(max-width:900px){.row2{grid-template-columns:1fr}}
</style>
</head>
<body>
  <div class="header">
    <div class="brand">Calendy Dashboard</div>
    <div class="nav">
      <a href="index.php">Home</a>
      <a href="#" onclick="doLogout()">Logout</a>
    </div>
  </div>

  <div class="container">
    <div class="card">
      <h2>Hello, <?= htmlspecialchars($you['name']) ?></h2>
      <p class="p">Manage bookings, reschedule, or cancel. Share your public link so guests can book you.</p>
      <div class="copy">
        <input id="plink" readonly value="<?= htmlspecialchars((isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/index.php?book_for='.$you['id']) ?>">
        <a class="btn" href="#" onclick="copyLink()">Copy Link</a>
      </div>
    </div>

    <div class="card">
      <h2>Your Availability</h2>
      <table class="table">
        <thead><tr><th>Day</th><th>Start</th><th>End</th></tr></thead>
        <tbody>
        <?php if($avs): foreach($avs as $a): ?>
          <tr><td><?= $days[$a['weekday']] ?></td><td><?= htmlspecialchars($a['start_time']) ?></td><td><?= htmlspecialchars($a['end_time']) ?></td></tr>
        <?php endforeach; else: ?>
          <tr><td colspan="3" style="color:#94a3b8">No availability added yet (add in Home &gt; Manage Availability).</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h2>Upcoming & Past Appointments</h2>
      <table class="table">
        <thead><tr><th>Date</th><th>Time</th><th>Guest</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if($rows): foreach($rows as $b): ?>
          <tr>
            <td><?= htmlspecialchars($b['date']) ?></td>
            <td><?= htmlspecialchars($b['time']) ?></td>
            <td><?= htmlspecialchars($b['guest_name']) ?></td>
            <td><?= htmlspecialchars($b['guest_email']) ?></td>
            <td><span class="badge" style="background:<?= $b['status']==='booked'?'#86efac':'#fca5a5' ?>;color:#061006"><?= htmlspecialchars($b['status']) ?></span></td>
            <td>
              <?php if($b['status']==='booked'): ?>
                <a class="btn min" href="#" onclick="openRes(<?= (int)$b['id'] ?>,'<?= $b['date'] ?>','<?= $b['time'] ?>')">Reschedule</a>
                <a class="btn bad min" href="dashboard.php?cancel=<?= (int)$b['id'] ?>" onclick="return confirm('Cancel this booking?')">Cancel</a>
              <?php else: ?>
                <span style="color:#94a3b8">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="6" style="color:#94a3b8">No bookings yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h2>Notifications</h2>
      <p class="p">Guests receive a confirmation (via email function if server allows). You can also export details or copy them quickly.</p>
      <div class="row2">
        <div>
          <label>Quick reminder (copy text)</label>
          <textarea id="remtxt" rows="4" style="width:100%;padding:10px;border:1px solid #243041;background:#0b1320;color:#e5e7eb;border-radius:12px">Hi! This is a reminder for our meeting. See you soon.</textarea>
          <div style="margin-top:8px"><a class="btn min" href="#" onclick="copyRem()">Copy</a></div>
        </div>
        <div>
          <label>Your public page</label>
          <input readonly value="<?= htmlspecialchars((isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/index.php?book_for='.$you['id']) ?>">
          <div style="margin-top:8px"><a class="btn min" href="index.php?book_for=<?= (int)$you['id'] ?>" target="_blank">Open</a></div>
        </div>
      </div>
    </div>
  </div>

  <footer>© <?= date('Y') ?> Calendy</footer>

<script>
function copyLink(){
  const el=document.getElementById('plink'); el.select(); el.setSelectionRange(0,99999);
  try{ document.execCommand('copy'); alert('Link copied!'); }catch(e){ navigator.clipboard.writeText(el.value).then(()=>alert('Link copied!')); }
}
function copyRem(){
  const el=document.getElementById('remtxt'); el.select(); el.setSelectionRange(0,99999);
  try{ document.execCommand('copy'); alert('Reminder copied!'); }catch(e){ navigator.clipboard.writeText(el.value).then(()=>alert('Reminder copied!')); }
}
function doLogout(){
  if(confirm('Log out?')){
    const f=document.createElement('form'); f.method='post'; f.action='dashboard.php';
    const i=document.createElement('input'); i.type='hidden'; i.name='__logout'; i.value='1'; f.appendChild(i);
    document.body.appendChild(f); f.submit();
  }
}
function openRes(id,d,t){
  const date = prompt('New date (YYYY-MM-DD):', d); if(!date) return;
  const time = prompt('New time (HH:MM):', t); if(!time) return;
  const f=document.createElement('form'); f.method='post'; f.action='dashboard.php';
  f.innerHTML = '<input type="hidden" name="__act" value="reschedule">'+
                '<input type="hidden" name="booking_id" value="'+id+'">'+
                '<input type="hidden" name="date" value="'+date+'">'+
                '<input type="hidden" name="time" value="'+time+'">';
  document.body.appendChild(f); f.submit();
}
</script>
</body>
</html>
