<?php
declare(strict_types=1);

require __DIR__ . '/../src/Icons.php';
require __DIR__ . '/../src/Challenge.php';
require __DIR__ . '/../src/Scorer.php';
require __DIR__ . '/../src/HumanCheck.php';

use HumanCheck\Challenge;
use HumanCheck\HumanCheck;
use HumanCheck\Scorer;

$pass = 0;
$fail = 0;

function ok(bool $cond, string $name, string $extra = ''): void
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "  PASS  {$name}\n";
    } else {
        $fail++;
        echo "  FAIL  {$name}" . ($extra ? "  <{$extra}>" : '') . "\n";
    }
}

echo "\nChallenge generation\n";
$types = [];
for ($i = 0; $i < 400; $i++) {
    $c = Challenge::generate();
    $types[$c->type] = ($types[$c->type] ?? 0) + 1;
    // the public shape must never contain the answer
    $pub = json_encode($c->public());
    if (str_contains($pub, '"answer"')) {
        ok(false, 'public() leaks answer');
    }
    if ($c->type === 'hold') {
        continue;
    }
    ok(count($c->items) >= 4, 'has at least 4 items', $c->type);
    ok($c->answer !== null, 'answer computed', $c->type);
}
ok(count($types) === 5, 'all five types generated', json_encode($types));
echo "  types: " . json_encode($types) . "\n";

echo "\nScorer — humans\n";
$s = new Scorer();
$r = $s->score(['moves' => 40, 'ms' => 2400, 'hidden' => 0, 'blur' => 0], 2400);
ok($r['score'] === 100 && $r['passed'], 'deliberate human scores 100', json_encode($r));

echo "\nScorer — bots\n";
$r = $s->score(['moves' => 0, 'ms' => 40, 'hidden' => 0, 'blur' => 0], 40);
ok($r['score'] <= 25 && !$r['passed'], 'instant, motionless bot is capped below pass mark', json_encode($r));

$r = $s->score(['moves' => 0, 'ms' => 2000, 'hidden' => 0, 'blur' => 0], 2000);
ok(!$r['passed'], 'bot with correct timing still fails (no motion)', json_encode($r));

$r = $s->score(['moves' => 30, 'ms' => 50, 'hidden' => 0, 'blur' => 0], 50);
ok(!$r['passed'] && $r['hardfail'], 'fast human-looking click hard-fails', json_encode($r));

$r = $s->score(['moves' => 30, 'ms' => 2400, 'hidden' => 3, 'blur' => 0], 2400);
ok(!$r['passed'] && $r['hardfail'], 'hidden tab hard-fails', json_encode($r));

$r = $s->score(['moves' => 30, 'ms' => 9000, 'hidden' => 0, 'blur' => 0], 2000);
ok(!$r['passed'] && $r['hardfail'], 'lying about elapsed time hard-fails', json_encode($r));

$r = $s->score(['moves' => 30, 'ms' => 2400, 'hidden' => 0, 'blur' => 2], 2400);
ok(!$r['hardfail'], 'blur alone does not hard-fail (password managers exist)', json_encode($r));

echo "\nSession flow\n";
$hc = new HumanCheck(new Scorer(minScore: 60));
$issued = $hc->issue();
ok(isset($issued['token'], $issued['challenge']), 'issue() returns token + challenge');

$ch = $issued['challenge'];
ok(!array_key_exists('answer', $ch), 'issued challenge carries no answer');

// find the real answer by reaching into the session store
$token = $issued['token'];
$stored = $_SESSION['_humancheck'][$token]['answer'] ?? null;
ok($stored !== null, 'answer stored server-side');

$v = $hc->verify($token, ['pick' => $stored, 'signals' => ['moves' => 40, 'ms' => 2400]]);
ok($v['passed'], 'correct answer + human signals passes', json_encode($v));

$reuse = $hc->verify($token, ['pick' => $stored, 'signals' => ['moves' => 40, 'ms' => 2400]]);
ok(!$reuse['passed'], 'token is single-use', json_encode($reuse));

$t2 = $hc->issue();
$wrong = is_array($_SESSION['_humancheck'][$t2['token']]['answer'])
    ? [99, 98]
    : 99;
$v2 = $hc->verify($t2['token'], ['pick' => $wrong, 'signals' => ['moves' => 40, 'ms' => 2400]]);
ok(!$v2['passed'] && $v2['score'] === 0, 'wrong answer scores 0', json_encode($v2));

$v3 = $hc->verify('deadbeef', ['pick' => 0, 'signals' => []]);
ok(!$v3['passed'], 'unknown token rejected', json_encode($v3));

echo "\nExpiry\n";
$hcFast = new HumanCheck(new Scorer(), Challenge::TYPES, ttl: 0);
$t3 = $hcFast->issue();
usleep(2000);
$v4 = $hcFast->verify($t3['token'], ['pick' => 0, 'signals' => ['moves' => 40, 'ms' => 2400]]);
ok(!$v4['passed'] && str_contains((string)$v4['error'], 'expired'), 'expired challenge rejected', json_encode($v4));

echo "\n----------------------------------------\n";
echo "PASS {$pass}   FAIL {$fail}\n";
exit($fail === 0 ? 0 : 1);
