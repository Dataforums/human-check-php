<?php
declare(strict_types=1);

namespace HumanCheck;

/**
 * The icon pool. Each entry has a glyph, a lowercase name used in the
 * question text, and a category used to build "odd one out" puzzles.
 */
final class Icons
{
    /** @return array<int,array{e:string,n:string,c:string}> */
    public static function all(): array
    {
        return [
            ['e' => '🔥', 'n' => 'fire',       'c' => 'thing'],
            ['e' => '🌊', 'n' => 'wave',       'c' => 'thing'],
            ['e' => '🎸', 'n' => 'guitar',     'c' => 'thing'],
            ['e' => '💎', 'n' => 'diamond',    'c' => 'thing'],
            ['e' => '🍕', 'n' => 'pizza',      'c' => 'food'],
            ['e' => '🍩', 'n' => 'donut',      'c' => 'food'],
            ['e' => '🍓', 'n' => 'strawberry', 'c' => 'food'],
            ['e' => '🚀', 'n' => 'rocket',     'c' => 'vehicle'],
            ['e' => '🚁', 'n' => 'helicopter', 'c' => 'vehicle'],
            ['e' => '⛵', 'n' => 'sailboat',   'c' => 'vehicle'],
            ['e' => '🐱', 'n' => 'cat',        'c' => 'animal'],
            ['e' => '🐟', 'n' => 'fish',       'c' => 'animal'],
            ['e' => '🦉', 'n' => 'owl',        'c' => 'animal'],
            ['e' => '🌸', 'n' => 'blossom',    'c' => 'plant'],
            ['e' => '🌵', 'n' => 'cactus',     'c' => 'plant'],
        ];
    }

    /** @param array<int,mixed> $a */
    public static function pick(array $a): array
    {
        $a = array_values($a);
        return $a[random_int(0, count($a) - 1)];
    }

    /**
     * Fisher-Yates with a CSPRNG. shuffle() is fine for games, not for
     * anything an attacker could sample.
     *
     * @param array<int,mixed> $a
     * @return array<int,mixed>
     */
    public static function shuffle(array $a): array
    {
        $a = array_values($a);
        for ($i = count($a) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            $t = $a[$i];
            $a[$i] = $a[$j];
            $a[$j] = $t;
        }
        return $a;
    }
}
