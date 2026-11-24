<?php

namespace App\Services;

/**
 * Defence against invented facts in AI-written copy.
 * Any sentence containing a number, e-mail or URL that is not present in the confirmed facts is dropped.
 * (Limitation: spelled-out numbers such as "twenty years" are not caught — see DECISIONS.md.)
 */
class FactGuard
{
    private const TOKEN = '/\d+|[\w.+-]+@[\w-]+\.[\w.-]+|https?:\/\/\S+|www\.\S+/iu';

    public function sanitize(string $text, string $corpus): string
    {
        $allowed = $this->tokens($corpus);
        $kept = [];
        foreach (preg_split('/(?<=[.!?])\s+/u', trim($text)) ?: [] as $sentence) {
            if ($sentence === '' || array_diff($this->tokens($sentence), $allowed)) {
                continue;
            }
            $kept[] = $sentence;
        }
        return trim(implode(' ', $kept));
    }

    /** @return string[] */
    private function tokens(string $text): array
    {
        preg_match_all(self::TOKEN, mb_strtolower($text), $m);
        return array_values(array_unique(array_map(fn ($t) => rtrim($t, '.,;)'), $m[0])));
    }
}
