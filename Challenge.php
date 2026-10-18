<?php
declare(strict_types=1);

namespace HumanCheck;

/**
 * A generated challenge.
 *
 * `answer` is private and must never reach the browser.
 * `public()` is the only shape safe to serialise to the client.
 */
final class Challenge
{
    public const TYPES = ['match', 'odd', 'pair', 'order', 'hold'];

    public function __construct(
        public readonly string $type,
        public readonly string $ask,
        public readonly bool $multi,
        /** @var array<int,array{e:string}> */
        public readonly array $items,
        /** @var mixed int|array<int,int>|string|null */
        public readonly mixed $answer
    ) {}

    /**
     * Generate a challenge of a random enabled type.
     *
     * @param string[] $enabled Subset of self::TYPES
     */
    public static function generate(array $enabled = self::TYPES): self
    {
        $enabled = array_values(array_intersect($enabled, self::TYPES));
        if (!$enabled) {
            $enabled = self::TYPES;
        }
        $type = $enabled[random_int(0, count($enabled) - 1)];
        $pool = Icons::all();

        return match ($type) {
            'match' => self::buildMatch($pool),
            'odd'   => self::buildOdd($pool),
            'pair'  => self::buildPair($pool),
            'order' => self::buildOrder($pool),
            default => new self('hold', 'Press and <b>hold</b> the button until it fills', false, [], 'hold'),
        };
    }

    /**
     * The only representation allowed to leave the server.
     *
     * @return array{type:string,ask:string,multi:bool,items:array<int,array{e:string}>}
     */
    public function public(): array
    {
        return [
            'type'  => $this->type,
            'ask'   => $this->ask,
            'multi' => $this->multi,
            'items' => array_map(static fn (array $x): array => ['e' => $x['e']], $this->items),
        ];
    }

    private static function buildMatch(array $pool): self
    {
        $target = Icons::pick($pool);
        $others = array_slice(Icons::shuffle(array_filter(
            $pool,
            static fn (array $x): bool => $x['e'] !== $target['e']
        )), 0, 3);
        $items = Icons::shuffle(array_merge($others, [$target]));
        $answer = null;
        foreach ($items as $i => $x) {
            if ($x['e'] === $target['e']) {
                $answer = $i;
            }
        }
        return new self(
            'match',
            'Tap the <b>' . $target['n'] . '</b> &nbsp;' . $target['e'],
            false,
            $items,
            $answer
        );
    }

    private static function buildOdd(array $pool): self
    {
        /* only use categories that can supply 3 "same" icons, otherwise the
           board comes out with 3 tiles instead of 4 */
        $counts = [];
        foreach ($pool as $x) {
            $counts[$x['c']] = ($counts[$x['c']] ?? 0) + 1;
        }
        $big   = array_keys(array_filter($counts, static fn (int $n): bool => $n >= 3));
        $cats  = Icons::shuffle($big);
        $main  = $cats[0];
        $other = $cats[1];
        $same = array_slice(Icons::shuffle(array_filter(
            $pool,
            static fn (array $x): bool => $x['c'] === $main
        )), 0, 3);
        $odd = Icons::pick(array_filter(
            $pool,
            static fn (array $x): bool => $x['c'] === $other
        ));
        $items = Icons::shuffle(array_merge($same, [$odd]));
        $answer = null;
        foreach ($items as $i => $x) {
            if ($x['e'] === $odd['e']) {
                $answer = $i;
            }
        }
        return new self('odd', 'Tap the one that <b>doesn\'t belong</b>', false, $items, $answer);
    }

    private static function buildPair(array $pool): self
    {
        $pair = Icons::pick($pool);
        $singles = array_slice(Icons::shuffle(array_filter(
            $pool,
            static fn (array $x): bool => $x['e'] !== $pair['e']
        )), 0, 2);
        $items = Icons::shuffle(array_merge($singles, [$pair, $pair]));
        $answer = [];
        foreach ($items as $i => $x) {
            if ($x['e'] === $pair['e']) {
                $answer[] = $i;
            }
        }
        return new self('pair', 'Tap the icon that appears <b>twice</b>', true, $items, $answer);
    }

    private static function buildOrder(array $pool): self
    {
        $a = Icons::pick($pool);
        $b = Icons::pick(array_filter(
            $pool,
            static fn (array $x): bool => $x['e'] !== $a['e']
        ));
        $rest = array_slice(Icons::shuffle(array_filter(
            $pool,
            static fn (array $x): bool => $x['e'] !== $a['e'] && $x['e'] !== $b['e']
        )), 0, 2);
        $items = Icons::shuffle(array_merge([$a, $b], $rest));
        $ia = $ib = null;
        foreach ($items as $i => $x) {
            if ($x['e'] === $a['e']) {
                $ia = $i;
            }
            if ($x['e'] === $b['e']) {
                $ib = $i;
            }
        }
        return new self(
            'order',
            'First tap the <b>' . $a['n'] . '</b> ' . $a['e']
                . ', then the <b>' . $b['n'] . '</b> ' . $b['e'],
            true,
            $items,
            [$ia, $ib]
        );
    }
}
