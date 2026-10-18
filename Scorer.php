<?php
declare(strict_types=1);

namespace HumanCheck;

/**
 * Behavioural scorer.
 *
 * The point of this class is that knowing the right answer is not enough.
 * A bot that solves the puzzle perfectly still fails here, because it did
 * not move a pointer, or it answered in 40ms, or its tab was hidden.
 *
 * Signals are supplied by the browser and are therefore untrusted. They
 * are checked against the server's own clock, which the client cannot fake.
 */
final class Scorer
{
    public function __construct(
        /** Score at or above which the visitor counts as human. */
        private readonly int $minScore = 60,
        /** Below this many milliseconds, a human answer is implausible. */
        private readonly int $fastLimit = 300,
        /** How much slack to allow between client and server timings. */
        private readonly int $clockSlack = 1500
    ) {}

    /**
     * @param array{moves?:int,ms?:int,hidden?:int,blur?:int} $signals
     * @param int $serverMs Elapsed time measured by the SERVER, not the client.
     * @return array{score:int,label:string,reasons:string[],passed:bool}
     */
    public function score(array $signals, int $serverMs = 0): array
    {
        $score = 100;
        $reasons = [];

        $moves  = (int)($signals['moves']  ?? 0);
        $ms     = (int)($signals['ms']     ?? 0);
        $hidden = (int)($signals['hidden'] ?? 0);
        $blur   = (int)($signals['blur']   ?? 0);

        // 1. The claimed time must not contradict the server's clock.
        if ($serverMs > 0 && $ms > $serverMs + $this->clockSlack) {
            $score -= 40;
            $reasons[] = 'claimed time exceeds real elapsed time';
        }
        if ($serverMs > 0 && $ms <= 0) {
            $score -= 40;
            $reasons[] = 'no answer time reported';
        }

        // 2. Real people move a pointer.
        if ($moves === 0) {
            $score -= 45;
            $reasons[] = 'no pointer movement';
        } elseif ($moves < 3) {
            $score -= 18;
            $reasons[] = 'almost no pointer movement';
        }

        // 3. Real people are not instant.
        if ($ms > 0 && $ms < $this->fastLimit) {
            $score -= 35;
            $reasons[] = 'answered inhumanly fast';
        } elseif ($ms > 0 && $ms < $this->fastLimit * 2 + 100) {
            $score -= 12;
            $reasons[] = 'answered very fast';
        }

        // 4. A real person is looking at the page.
        if ($hidden > 0) {
            $score -= 30;
            $reasons[] = 'tab was hidden while answering';
        }
        if ($blur > 0) {
            $score -= 18;
            $reasons[] = 'window lost focus while answering';
        }

        $score = max(0, min(100, $score));

        /*
         * Some signals are not deductions, they are proof.
         *
         * - No pointer movement at all means no hand touched the device.
         * - A sub-300ms answer means no human reaction time occurred.
         * - Claimed time exceeding real elapsed time is a lie about the clock.
         * - A hidden tab means nobody was watching while it "answered".
         *
         * Any one of these caps the score below the pass mark no matter how
         * many other points it collected. Without this, a fast bot that
         * happens to guess correctly lands on 65 and walks through a
         * threshold of 60.
         */
        $hardFail = false;
        if ($moves === 0)                                   { $hardFail = true; }
        if ($ms > 0 && $ms < $this->fastLimit)              { $hardFail = true; }
        if ($serverMs > 0 && $ms > $serverMs + $this->clockSlack) { $hardFail = true; }
        if ($hidden > 0)                                    { $hardFail = true; }
        /* blur alone is not proof - a password manager or OS dialog can steal
           focus from a real person, so it stays a deduction only. */

        $passed = !$hardFail && $score >= $this->minScore;
        if ($hardFail) {
            $score = min($score, max(0, $this->minScore - 1));
        }

        $label = $hardFail
            ? 'automated'
            : ($score >= 75 ? 'human' : ($score >= 50 ? 'borderline' : 'automated'));

        return [
            'score'   => $score,
            'label'   => $label,
            'reasons' => $reasons,
            'passed'  => $passed,
            'hardfail'=> $hardFail,
        ];
    }
}
