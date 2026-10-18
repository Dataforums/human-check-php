<?php
declare(strict_types=1);

namespace HumanCheck;

/**
 * Session-backed human check.
 *
 * Usage:
 *   $hc = new HumanCheck();
 *   $ch = $hc->issue();                  // send $ch to the browser
 *   ... browser answers ...
 *   $r  = $hc->verify($_POST['token'], (array)$_POST['answer'], $serverMs);
 *   if (!$r['passed']) { reject(); }
 *
 * The correct answer is stored server-side and is never serialised to the
 * client. The token is single-use and expires.
 */
final class HumanCheck
{
    private const SESSION_KEY = '_humancheck';
    private const TTL = 300; // 5 minutes

    public function __construct(
        private readonly Scorer $scorer = new Scorer(),
        private readonly array $types = Challenge::TYPES,
        private readonly int $ttl = self::TTL
    ) {}

    /**
     * Create a challenge, remember its answer and issued-at time, and return
     * the public shape plus a token. The answer stays in the session.
     *
     * @return array{token:string,challenge:array{type:string,ask:string,multi:bool,items:array<int,array{e:string}>}}
     */
    public function issue(): array
    {
        $this->startSession();
        $this->gc();

        $challenge = Challenge::generate($this->types);
        $token = bin2hex(random_bytes(16));

        $_SESSION[self::SESSION_KEY][$token] = [
            'answer' => $challenge->answer,
            'issued' => microtime(true),
            'used'   => false,
        ];

        return ['token' => $token, 'challenge' => $challenge->public()];
    }

    /**
     * Check a submission.
     *
     * @param mixed $answer Index, list of indexes, or 'hold' — as submitted.
     * @return array{passed:bool,score:int,label:string,reasons:string[],error:?string}
     */
    public function verify(string $token, mixed $answer, ?int $serverMs = null): array
    {
        $this->startSession();
        $fail = static fn (string $e): array => [
            'passed' => false, 'score' => 0, 'label' => 'automated',
            'reasons' => [$e], 'error' => $e,
        ];

        $row = $_SESSION[self::SESSION_KEY][$token] ?? null;
        if (!is_array($row)) {
            return $fail('unknown or already used token');
        }
        // single use — burn it before doing anything else
        unset($_SESSION[self::SESSION_KEY][$token]);

        $elapsed = microtime(true) - (float)$row['issued'];
        if ($elapsed > $this->ttl) {
            return $fail('challenge expired');
        }

        // Server-measured elapsed time beats anything the client claims.
        $serverMs ??= (int)round($elapsed * 1000);

        $signals = is_array($answer) && isset($answer['signals']) && is_array($answer['signals'])
            ? $answer['signals']
            : [];
        $picked = is_array($answer) && array_key_exists('pick', $answer) ? $answer['pick'] : $answer;

        $expected = $row['answer'];
        $correct = $this->answersMatch($expected, $picked);

        $result = $this->scorer->score($signals, $serverMs);

        if (!$correct) {
            $result['score'] = 0;
            $result['label'] = 'automated';
            $result['reasons'][] = 'wrong answer';
            $result['passed'] = false;
        }

        $result['error'] = $result['passed'] ? null : implode('; ', $result['reasons']);
        return $result;
    }

    /** @param mixed $expected @param mixed $picked */
    private function answersMatch(mixed $expected, mixed $picked): bool
    {
        if ($expected === 'hold') {
            return $picked === 'hold' || $picked === true || $picked === 1;
        }
        if (is_array($expected)) {
            if (!is_array($picked)) {
                return false;
            }
            $e = array_map('intval', $expected);
            $p = array_map('intval', $picked);
            sort($e);
            sort($p);
            return $e === $p;
        }
        if ($picked === null || is_array($picked)) {
            return false;
        }
        return (int)$expected === (int)$picked;
    }

    private function gc(): void
    {
        $now = microtime(true);
        foreach ($_SESSION[self::SESSION_KEY] ?? [] as $k => $v) {
            if (!is_array($v) || $now - (float)($v['issued'] ?? 0) > $this->ttl) {
                unset($_SESSION[self::SESSION_KEY][$k]);
            }
        }
    }

    private function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (PHP_SAPI !== 'cli' && !headers_sent()) {
                session_start();
            } else {
                // CLI / test context: use an in-memory session array
                $_SESSION ??= [];
            }
        }
    }
}
