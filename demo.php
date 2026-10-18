<?php
declare(strict_types=1);
/**
 * Self-contained demo. Run:  php -S localhost:8000 examples/demo.php
 * Try solving it yourself, then try it with JS disabled, an instant click,
 * and a hidden tab. All three fail.
 */
require __DIR__ . '/../src/Icons.php';
require __DIR__ . '/../src/Challenge.php';
require __DIR__ . '/../src/Scorer.php';
require __DIR__ . '/../src/HumanCheck.php';

use HumanCheck\HumanCheck;

session_start();
$hc = new HumanCheck();

$result = null;
$challenge = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $serverMs = isset($_POST['started'], $_POST['arrived'])
        ? max(0, (int)$_POST['arrived'] - (int)$_POST['started'])
        : null;
    $signals = json_decode((string)($_POST['signals'] ?? '{}'), true);
    $answer = [
        'pick'    => $_POST['hold'] ?? $_POST['pick'] ?? null,
        'signals' => is_array($signals) ? $signals : [],
    ];
    $result = $hc->verify((string)($_POST['token'] ?? ''), $answer, $serverMs);
}

if ($challenge === null) {
    $issued = $hc->issue();
    $challenge = $issued['challenge'];
    $token = $issued['token'];
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>human-check-php demo</title>
<style>
  :root { color-scheme: dark; }
  * { box-sizing: border-box; }
  body { margin:0; min-height:100vh; display:grid; place-items:center; padding:24px;
         background:#0a0e14; color:#e8eef6; font:15px/1.6 system-ui,-apple-system,sans-serif; }
  .card { width:min(460px,100%); border:1px solid #1e2a38; border-radius:16px; padding:26px;
          background:#0e141d; }
  h1 { font-size:19px; margin:0 0 4px; }
  .sub { color:#7d90a6; font-size:13px; margin:0 0 20px; }
  .ask { font-size:15px; margin-bottom:16px; }
  .grid { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; margin-bottom:16px; }
  .tile { aspect-ratio:2/1; border:1px solid #24384c; border-radius:12px; background:#131c28;
          font-size:30px; cursor:pointer; color:#fff; transition:.15s; }
  .tile:hover { border-color:#2f4f6b; }
  .tile.on { border-color:#22d3ee; box-shadow:0 0 0 2px rgba(34,211,238,.25); transform:translateY(-1px); }
  .hold { width:100%; padding:16px; border-radius:12px; border:1px solid #24384c; background:#131c28;
          color:#cfe2f3; font-weight:600; cursor:pointer; user-select:none; }
  .hold.on { background:#0e3a44; border-color:#22d3ee; color:#8ae9fb; }
  button[type=submit] { width:100%; padding:13px; border:0; border-radius:12px; font-weight:700;
          background:linear-gradient(135deg,#0891b2,#22d3ee); color:#04222a; cursor:pointer; margin-top:6px; }
  .out { border-radius:12px; padding:14px; font-size:13.5px; margin-bottom:16px; }
  .ok  { background:rgba(74,222,158,.1); border:1px solid rgba(74,222,158,.35); color:#8af0c6; }
  .bad { background:rgba(251,113,133,.1); border:1px solid rgba(251,113,133,.35); color:#fda4af; }
  code { background:#131c28; padding:1px 5px; border-radius:4px; font-size:12px; }
</style>
</head>
<body>
<div class="card">
  <?php if ($result !== null): ?>
    <h1><?= $result['passed'] ? 'Passed' : 'Blocked' ?></h1>
    <p class="sub">score <?= (int)$result['score'] ?>/100 · <?= htmlspecialchars($result['label']) ?></p>
    <div class="out <?= $result['passed'] ? 'ok' : 'bad' ?>">
      <?php if ($result['reasons']): ?>
        <?= htmlspecialchars(implode(' · ', $result['reasons'])) ?>
      <?php else: ?>
        Deliberate pointer movement, human response time, visible tab. Nothing suspicious.
      <?php endif; ?>
    </div>
    <p class="sub">Reload for a fresh challenge.</p>
  <?php else: ?>
    <h1>Human check</h1>
    <p class="sub">Demo by <a href="https://realtrafficx.com" style="color:#22d3ee">RealTrafficX</a></p>
    <form method="post" id="f">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <input type="hidden" name="started" value="<?= time() ?>">
      <input type="hidden" name="arrived" id="arrived" value="0">
      <input type="hidden" name="signals" id="signals" value="{}">

      <div class="ask"><?= $challenge['ask'] ?></div>

      <?php if ($challenge['type'] === 'hold'): ?>
        <button type="button" class="hold" id="hold">Press and hold</button>
        <input type="hidden" name="hold" id="holdval" value="">
      <?php else: ?>
        <div class="grid">
          <?php foreach ($challenge['items'] as $i => $it): ?>
            <button type="button" class="tile" data-i="<?= $i ?>"><?= htmlspecialchars($it['e']) ?></button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" name="pick" id="pick" value="">
      <?php endif; ?>

      <button type="submit">Verify</button>
    </form>
  <?php endif; ?>
</div>

<?php if ($result === null): ?>
<script>
(function () {
  var moves = 0, hidden = 0, blur = 0;
  document.addEventListener('mousemove', function () { moves++; });
  document.addEventListener('touchmove', function () { moves++; });
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') hidden++;
  });
  window.addEventListener('blur', function () { blur++; });

  var multi = <?= $challenge['multi'] ? 'true' : 'false' ?>;
  var picked = [];

  document.querySelectorAll('.tile').forEach(function (t) {
    t.addEventListener('click', function () {
      var i = parseInt(t.dataset.i, 10);
      if (!multi) {
        picked = [i];
        document.querySelectorAll('.tile').forEach(function (x) { x.classList.remove('on'); });
        t.classList.add('on');
      } else {
        var at = picked.indexOf(i);
        if (at > -1) { picked.splice(at, 1); t.classList.remove('on'); }
        else { picked.push(i); t.classList.add('on'); }
      }
      document.getElementById('pick').value = picked.join(',');
    });
  });

  var hold = document.getElementById('hold'), timer = null, t0 = 0;
  if (hold) {
    var start = function () {
      t0 = Date.now();
      timer = setTimeout(function () {
        hold.classList.add('on');
        hold.textContent = 'Verified';
        document.getElementById('holdval').value = 'hold';
      }, 1200);
    };
    var stop = function () {
      clearTimeout(timer);
      if (Date.now() - t0 < 1200) { hold.classList.remove('on'); document.getElementById('holdval').value = ''; }
    };
    hold.addEventListener('mousedown', start);
    hold.addEventListener('touchstart', start, {passive:true});
    hold.addEventListener('mouseup', stop);
    hold.addEventListener('mouseleave', stop);
    hold.addEventListener('touchend', stop);
  }

  document.getElementById('f').addEventListener('submit', function () {
    document.getElementById('arrived').value = Math.floor(Date.now() / 1000);
    document.getElementById('signals').value = JSON.stringify({
      moves: moves, ms: <?= time() * 1000 ?> ? (Date.now() - <?= time() * 1000 ?>) : 0,
      hidden: hidden, blur: blur
    });
  });
})();
</script>
<?php endif; ?>
</body>
</html>
