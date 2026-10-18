# human-check-php

A dependency-free behavioural CAPTCHA for PHP 8.

No third-party service. No tracking. No images to solve. The challenge is
generated on your server, the answer never leaves your server, and a bot that
solves the puzzle correctly **still fails** — because it did not move a
pointer, or it answered in 40ms, or its tab was hidden.

```
composer require realtrafficx/human-check-php
```

---

## Why

Most CAPTCHAs are one of two things:

- **A third-party widget** that ships your users' behaviour to someone else's
  servers and returns an opaque pass/fail you cannot audit.
- **A puzzle** that a bot with an OCR library or a vision model solves in a few
  hundred milliseconds.

This library takes a different angle. The puzzle is the easy part. What actually
separates a human from a script is **how the answer arrives**:

| Signal | What it catches |
|---|---|
| Pointer movement count | Scripts that never move a cursor |
| Response latency | Anything answering faster than human reaction time |
| Tab visibility | Background headless browsers |
| Claimed time vs server clock | Clients lying about how long they took |

A deliberate human scores **100**. A script that answers correctly scores
around **20**. Both have the right answer; only one earns the credit.

---

## Install

```bash
composer require realtrafficx/human-check-php
```

Or, with no composer at all:

```php
require 'src/Icons.php';
require 'src/Challenge.php';
require 'src/Scorer.php';
require 'src/HumanCheck.php';
```

Requires PHP 8.0+. No extensions beyond the defaults.

---

## Use

### 1. Issue a challenge

```php
use HumanCheck\HumanCheck;

session_start();
$hc = new HumanCheck();
$issued = $hc->issue();

// $issued['token']     — put this in a hidden form field
// $issued['challenge'] — safe to send to the browser. Contains no answer.
```

`$issued['challenge']` looks like:

```json
{
  "type": "match",
  "ask": "Tap the <b>donut</b> &nbsp;🍩",
  "multi": false,
  "items": [{"e":"🔥"},{"e":"🍩"},{"e":"🚀"},{"e":"🐱"}]
}
```

Note what is **not** in there: the answer. It is stored in the session, keyed by
the token, and is never serialised to the client.

### 2. Collect signals in the browser

```js
let moves = 0, hidden = 0, blur = 0;
document.addEventListener('mousemove', () => moves++);
document.addEventListener('touchmove', () => moves++);
document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'hidden') hidden++;
});
window.addEventListener('blur', () => blur++);

// on submit
form.signals.value = JSON.stringify({
  moves, ms: Date.now() - startedAt, hidden, blur
});
```

### 3. Verify

```php
$signals = json_decode($_POST['signals'], true);
$result = $hc->verify($_POST['token'], [
    'pick'    => $_POST['pick'],      // index, array of indexes, or 'hold'
    'signals' => $signals,
]);

if (!$result['passed']) {
    http_response_code(429);
    exit($result['error']);
}
```

`verify()` returns:

```php
[
  'passed'   => bool,
  'score'    => int,      // 0–100
  'label'    => string,   // human | borderline | automated
  'reasons'  => string[],
  'error'    => ?string,
  'hardfail' => bool,
]
```

---

## Challenge types

Five, chosen at random:

| Type | Task |
|---|---|
| `match` | Tap the named icon |
| `odd` | Tap the one that doesn't belong to the category |
| `pair` | Tap the icon that appears twice |
| `order` | Tap two named icons in the stated order |
| `hold` | Press and hold until the button fills |

Restrict them if you want:

```php
$hc = new HumanCheck(types: ['match', 'odd', 'hold']);
```

---

## Scoring

Starts at 100. Deductions:

| Condition | Points |
|---|---|
| No pointer movement at all | −45 |
| Fewer than 3 movements | −18 |
| Answered in under 300ms | −35 |
| Answered in under 700ms | −12 |
| Claimed time exceeds server time by >1.5s | −40 |
| Tab hidden while answering | −30 |
| Window lost focus | −18 |

### Some signals are proof, not deductions

A fast bot that guesses correctly would otherwise land on 65 and walk through a
threshold of 60. So four signals **hard-fail** regardless of total:

- zero pointer movement
- a sub-300ms answer
- claimed time contradicting the server clock
- a hidden tab

`blur` alone does **not** hard-fail — password managers and OS dialogs steal
focus from real people. It stays a deduction.

Tune it:

```php
new Scorer(
    minScore: 60,      // pass mark
    fastLimit: 300,    // ms below which a human answer is implausible
    clockSlack: 1500,  // allowed disagreement between client and server time
);
```

---

## Security properties

- **The answer never reaches the browser.** Only `Challenge::public()` is
  serialised, and it strips `answer`.
- **Tokens are single-use.** Burnt before scoring, so a replay cannot succeed.
- **Challenges expire** after 5 minutes (`ttl`).
- **Timing is measured server-side.** `HumanCheck::verify()` computes elapsed
  time from its own clock; the client's claim is only compared against it.
- **CSPRNG throughout.** `random_int()`, never `rand()` or `shuffle()`.

### What this is not

It is not a defence against a determined attacker with a browser automation
framework and a real human solving captchas. Nothing at this layer is. It raises
the cost of casual automation, which is what most sites actually face.

There is **no accessibility fallback** in this version, which is a real
limitation — `hold` is the most usable type, and relying on emoji recognition
excludes some users. If that matters for your audience, run this alongside an
email-verification step rather than as the only gate.

---

## Test

```bash
php tests/run.php
```

650 assertions, no PHPUnit required. Exits non-zero on failure, so it drops into
CI as-is.

Two bugs the suite caught during development, both fixed:

1. `odd` challenges could emit a 3-tile board when the source category was
   small, giving the odd one away by elimination.
2. Four signals were being scored as deductions when they should have been hard
   failures — a bot could collect enough points elsewhere to pass anyway.

---

## Demo

```bash
php -S localhost:8000 examples/demo.php
```

Then try three things: solve it normally, disable JavaScript, and click
instantly. Only the first passes.

---

## License

MIT. Do what you like with it.

---

Built for [RealTrafficX](https://realtrafficx.com) — a traffic exchange that
publishes its delivery record so you can check it against your own analytics.
