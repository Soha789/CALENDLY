<?php
session_start();

/* ── DB helper ─────────────────────────────── */
function db(){ $db=new PDO('sqlite:' . __DIR__ . '/calendar.db'); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); return $db; }
function isLogged(){ return isset($_SESSION['uid']); }

/* ── Utilities ─────────────────────────────── */
function timeSlots($start, $end, $stepMin=30){ // returns array of 'HH:MM'
  $slots=[]; [$sh,$sm]=array_map('intval',explode(':',$start)); [$eh,$em]=array_map('intval',explode(':',$end));
  $startMin = $sh*60+$sm; $endMin = $eh*60+$em;
  for($m=$startMin; $m+$stepMin <= $endMin; $m+=$stepMin){ $slots[] = sprintf('%02d:%02d', intdiv($m,60), $m%60); }
  return $slots;
}
function weekdayOf($date){ return (int)date('w', strtotime($date)); } // 0..6

/* ── Handle availability CRUD (only logged in) ───────────── */
if (isLogged() && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['__act']) && $_POST['__act']==='add_av'){
  $uid = $_SESSION['uid'];
  $weekday = (int)($_POST['weekday'] ?? -1);
  $start = $_POST['start_time'] ?? '';
  $end   = $_POST['end_time'] ?? '';
  if ($weekday>=0 && $start && $end){
    $st = db()->prepare("INSERT INTO availability(user_id,weekday,start_time,end_time) VALUES(?,?,?,?)");
    $st->execute([$uid,$weekday,$start,$end]);
    echo "<script>alert('Availability added.'); location.href='index.php';</script>"; exit;
  } else {
    echo "<script>alert('Please fill all availability fields.');</script>";
  }
}
if (isLogged() && isset($_GET['del_av'])){
  $uid = $_SESSION['uid']; $id=(int)$_GET['del_av'];
  $st = db()->prepare("DELETE FROM availability WHERE id=? AND user_id=?"); $st->execute([$id,$uid]);
  echo "<script>alert('Deleted.'); location.href='index.php';</script>"; exit;
}

/* ── Handle booking (public) ─────────────────────────────── */
if (isset($_POST['__act']) && $_POST['__act']==='book'){
  $user_id    = (int)($_POST['user_id'] ?? 0);
  $date       = trim($_POST['date'] ?? '');
  $time       = trim($_POST['time'] ?? '');
  $guest_name = trim($_POST['guest_name'] ?? '');
  $guest_email= strtolower(trim($_POST['guest_email'] ?? ''));
  if ($user_id && $date && $time && $guest_name && filter_var($guest_email, FILTER_VALIDATE_EMAIL)){
    // check if slot is free
    $st = db()->prepare("SELECT COUNT(*) FROM bookings WHERE user_id=? AND date=? AND time=? AND status='booked'");
    $st->execute([$user_id,$date,$time]);
    if ($st->fetchColumn()>0){
      echo "<script>alert('This time slot is already booked. Choose another.'); history.back();</script>"; exit;
    }
    // save booking
    $ins = db()->prepare("INSERT INTO bookings(user_id,guest_name,guest_email,date,time) VALUES(?,?,?,?,?)");
    $ins->execute([$user_id,$guest_name,$guest_email,$date,$time]);

    // (Optional) send email – may be disabled on local
    // @mail($guest_email, "Booking Confirmed", "Your meeting on $date at $time is confirmed.", "From: no-reply@calendy.test");

    echo "<script>alert('Booking confirmed! Check your email for details.'); location.href='index.php?book_for=".$user_id."';</script>";
    exit;
  } else {
    echo "<script>alert('Please fill all fields correctly.'); history.back();</script>"; exit;
  }
}

/* ── Fetch for rendering ─────────────────────────────────── */
$db = db();
$you = null;
if (isLogged()){
  $st = $db->prepare("SELECT id,name,email FROM users WHERE id=?");
  $st->execute([$_SESSION['uid']]); $you = $st->fetch(PDO::FETCH_ASSOC);
  $avs = $db->prepare("SELECT * FROM availability WHERE user_id=? ORDER BY weekday,start_time");
  $avs->execute([$you['id']]); $yourAvailability = $avs->fetchAll(PDO::FETCH_ASSOC);
}

/* ── Public booking page for ?book_for=UID ───────────────── */
$publicUser = null; $publicAvailability = [];
if (isset($_GET['book_for'])){
  $pid = (int)$_GET['book_for'];
  $st = $db->prepare("SELECT id,name FROM users WHERE id=?");
  $st->execute([$pid]); $publicUser = $st->fetch(PDO::FETCH_ASSOC);
  if ($publicUser){
    $avs = $db->prepare("SELECT * FROM availability WHERE user_id=? ORDER BY weekday,start_time");
    $avs->execute([$publicUser['id']]); $publicAvailability = $avs->fetchAll(PDO::FETCH_ASSOC);
  }
}

/* ── Build available times for selected date ─────────────── */
$selectedDate = $_GET['d'] ?? '';
$generatedTimes = [];
if ($publicUser && $selectedDate){
  $w = weekdayOf($selectedDate);
  foreach($publicAvailability as $row){
    if ((int)$row['weekday'] === $w){
      $generatedTimes = array_merge($generatedTimes, timeSlots($row['start_time'],$row['end_time'],30));
    }
  }
  // remove already booked
  if ($generatedTimes){
    $st = $db->prepare("SELECT time FROM bookings WHERE user_id=? AND date=? AND status='booked'");
    $st->execute([$publicUser['id'],$selectedDate]);
    $taken = $st->fetchAll(PDO::FETCH_COLUMN);
    $generatedTimes = array_values(array_diff($generatedTimes, $taken));
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Calendy — Schedule & Book</title>
<style>
:root{
  --bg:#0f172a;--panel:#0b1220;--muted:#94a3b8;--acc:#22d3ee;--acc2:#a78bfa;--ok:#22c55e;--bad:#ef4444;--line:#1f2937
}
*{box-sizing:border-box} body{margin:0;font-family:Inter,system-ui,Segoe UI,Roboto,Arial;background:
radial-gradient(900px 560px at 85% -10%,#38bdf8,transparent),
radial-gradient(780px 520px at -15% 110%,#a78bfa22,transparent),
var(--bg);color:#e5e7eb;min-height:100vh}
.header{padding:18px 22px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;backdrop-filter:blur(6px);position:sticky;top:0}
.brand{display:flex;align-items:center;gap:10px;font-weight:800}
.brand .dot{width:10px;height:10px;border-radius:50%;background:conic-gradient(from 0deg, #22d3ee, #a78bfa)}
.nav a{color:#67e8f9;text-decoration:none;margin-left:14px}
.container{max-width:1100px;margin:0 auto;padding:22px}
.hero{display:grid;grid-template-columns:1.2fr .8fr;gap:18px}
.card{background:linear-gradient(180deg,#0b1220,#0a0f1a);border:1px solid var(--line);border-radius:16px;box-shadow:0 12px 50px rgba(34,211,238,.12);padding:20px}
h1{font-size:30px;margin:6px 0 8px}
p.muted{color:var(--muted);margin:0 0 14px}
.btn{display:inline-block;padding:10px 14px;border-radius:12px;border:1px solid #1f3c4f;background:
linear-gradient(90deg,var(--acc),var(--acc2));color:#071018;font-weight:800;text-decoration:none}
.section{margin-top:20px}
label{display:block;margin:10px 0 6px;color:#cbd5e1;font-size:13px}
input,select{width:100%;padding:10px 12px;border:1px solid #243041;background:#0b1320;color:#e5e7eb;border-radius:12px;outline:none}
.table{width:100%;border-collapse:collapse;margin-top:10px}
.table th,.table td{border-bottom:1px solid #1f2937;padding:10px 8px;text-align:left;font-size:14px}
.badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#0ea5e9;color:#00151b;font-weight:800;font-size:12px}
.row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.copy{display:flex;gap:8px;margin-top:10px}
.copy input{flex:1}
.tag{display:inline-flex;gap:8px;align-items:center;font-size:13px;color:#cbd5e1}
.timegrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:10px;margin-top:10px}
.slot{padding:10px;border:1px solid #1f2937;border-radius:10px;text-align:center;cursor:pointer;background:#101828}
.slot:hover{outline:3px solid #22d3ee33}
footer{color:#64748b;text-align:center;padding:24px}
@media(max-width:900px){.hero{grid-template-columns:1fr}}
</style>
</head>
<body>
  <div class="header">
    <div class="brand"><span class="dot"></span> Calendy</div>
    <div class="nav">
      <?php if(isLogged()): ?>
        <a href="dashboard.php">Dashboard</a>
        <a href="#" onclick="logout()">Logout</a>
      <?php else: ?>
        <a href="login.php">Log in</a>
        <a href="signup.php">Sign up</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="container">
    <?php if(!$publicUser): ?>
      <div class="hero">
        <div class="card">
          <span class="badge">Scheduling made simple</span>
          <h1>Set availability. Share a link. Get booked.</h1>
          <p class="muted">Calendly-style booking with a beautiful UI. Visitors pick a time from your available slots — and you see it in your dashboard.</p>
          <?php if(isLogged()): ?>
            <a class="btn" href="#availability">Manage Availability</a>
          <?php else: ?>
            <a class="btn" href="signup.php">Create your booking link</a>
          <?php endif; ?>
        </div>
        <div class="card">
          <h3 style="margin:0 0 8px">Book a meeting (demo)</h3>
          <p class="muted">Try a public page by opening someone’s booking link.</p>
          <div class="section">
            <?php if(isLogged()): ?>
              <div class="tag">Your public link:</div>
              <div class="copy">
                <input id="plink" readonly value="<?= htmlspecialchars((isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/index.php?book_for='.$you['id']) ?>">
                <button class="btn" onclick="copyLink()">Copy</button>
              </div>
            <?php else: ?>
              <a class="btn" href="signup.php">Generate my link</a>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php if(isLogged()): ?>
      <div id="availability" class="section card">
        <h2 style="margin:0 0 10px">Your Availability</h2>
        <form method="post" class="row">
          <input type="hidden" name="__act" value="add_av">
          <div>
            <label>Weekday</label>
            <select name="weekday" required>
              <?php $days=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
              foreach($days as $i=>$d): ?>
                <option value="<?= $i ?>"><?= $d ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div><label>Start</label><input type="time" name="start_time" required value="09:00"></div>
          <div><label>End</label><input type="time" name="end_time" required value="17:00"></div>
          <div></div><div></div>
          <div><button class="btn" style="width:100%">Add</button></div>
        </form>

        <table class="table">
          <thead><tr><th>Day</th><th>Start</th><th>End</th><th>Action</th></tr></thead>
          <tbody>
          <?php if(!empty($yourAvailability)): foreach($yourAvailability as $a): ?>
            <tr>
              <td><?= $days[$a['weekday']] ?></td>
              <td><?= htmlspecialchars($a['start_time']) ?></td>
              <td><?= htmlspecialchars($a['end_time']) ?></td>
              <td><a class="btn" href="index.php?del_av=<?= $a['id'] ?>" onclick="return confirm('Delete this slot?')">Delete</a></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="4" style="color:#94a3b8">No availability yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

    <?php else: /* Public booking UI */ ?>
      <div class="card">
        <h2 style="margin:0 0 10px">Book with <?= htmlspecialchars($publicUser['name']) ?></h2>
        <p class="muted">Pick a date, then select a time.</p>

        <form method="get" class="row2">
          <input type="hidden" name="book_for" value="<?= (int)$publicUser['id'] ?>">
          <div>
            <label>Date</label>
            <input type="date" name="d" value="<?= htmlspecialchars($selectedDate ?: date('Y-m-d')) ?>" required>
          </div>
          <div style="display:flex;align-items:flex-end"><button class="btn">Show Times</button></div>
        </form>

        <?php if($selectedDate): ?>
          <div class="section">
            <div class="tag">Available time slots for <strong style="margin-left:6px"><?= htmlspecialchars($selectedDate) ?></strong></div>
            <?php if($generatedTimes): ?>
              <form method="post" id="bookForm" class="section">
                <input type="hidden" name="__act" value="book">
                <input type="hidden" name="user_id" value="<?= (int)$publicUser['id'] ?>">
                <input type="hidden" name="date" value="<?= htmlspecialchars($selectedDate) ?>">
                <label>Choose Time</label>
                <div class="timegrid" id="timegrid">
                  <?php foreach($generatedTimes as $t): ?>
                    <div class="slot" onclick="pickTime('<?= $t ?>')"><?= $t ?></div>
                  <?php endforeach; ?>
                </div>
                <input type="hidden" name="time" id="timeInput" required>

                <div class="row2" style="margin-top:12px">
                  <div><label>Your Name</label><input type="text" name="guest_name" required></div>
                  <div><label>Your Email</label><input type="email" name="guest_email" required></div>
                </div>
                <div style="margin-top:12px"><button class="btn">Confirm Booking</button></div>
              </form>
            <?php else: ?>
              <p class="muted" style="margin-top:10px">No slots available for this date. Try another date.</p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <footer>© <?= date('Y') ?> Calendy — a simple Calendly-like scheduler</footer>

<script>
function copyLink(){
  const el=document.getElementById('plink'); el.select(); el.setSelectionRange(0,99999);
  try{ document.execCommand('copy'); alert('Link copied!'); }catch(e){ navigator.clipboard.writeText(el.value).then(()=>alert('Link copied!'));}
}
function pickTime(t){
  document.querySelectorAll('.slot').forEach(s=>s.style.outline='none');
  [...document.querySelectorAll('.slot')].filter(s=>s.textContent.trim()===t).forEach(s=>s.style.outline='3px solid #22d3eeaa');
  document.getElementById('timeInput').value=t;
}
function logout(){
  if(confirm('Log out?')){ // JS-based redirect to a tiny logout action
    const f=document.createElement('form'); f.method='post'; f.action='dashboard.php'; 
    const i=document.createElement('input'); i.type='hidden'; i.name='__logout'; i.value='1'; f.appendChild(i);
    document.body.appendChild(f); f.submit();
  }
}
</script>
</body>
</html>
