<?php

namespace App\Services;

/**
 * Defence layer 1 against prompt injection hidden in uploaded files / web pages.
 * Instruction-like sentences are removed BEFORE the text reaches the AI and reported to the user.
 * (Layers 2-4: random delimiters + "untrusted data" system rule, evidence verification, user confirmation.)
 */
class InjectionScanner
{
    private const PATTERNS = [
        '/\b(ignore|disregard|forget|override)\b[^.\n]{0,40}\b(previous|prior|above|earlier|all|any|your)\b[^.\n]{0,40}\b(instructions?|prompts?|rules?|guidelines?|context)\b/iu',
        '/\b(system|developer)\s+(prompt|message|instruction)s?\b/iu',
        '/<\/?\s*(system|assistant|instructions?)\s*>|\[\/?INST\]|<\|[a-z_]+\|>/iu',
        '/\byou\s+are\s+(now|no\s+longer)\b/iu',
        '/\b(reveal|print|output|leak|share|send|show)\b[^.\n]{0,40}\b(api[\s_-]?key|secret|password|token|credentials|\.env)\b/iu',
        '/\b(do\s+not|don\'t|never)\s+(tell|inform|mention|reveal|alert)\b[^.\n]{0,30}\b(user|owner|human)\b/iu',
        '/\b(new|updated)\s+instructions?\s*:/iu',
        '/\bas\s+an?\s+(ai|language\s+model|llm|assistant)\b/iu',
        '/\bwhen\s+(an?\s+)?(ai|assistant|llm|model|chatbot)\b[^.\n]{0,40}\b(reads?|summari[sz]es?|processes?)\b/iu',
    ];

    /** @return array{clean:string, flags:array<int,array{snippet:string}>} */
    public function scan(string $text): array
    {
        $segments = preg_split('/(?<=[.!?])\s+|\R+/u', $text) ?: [];
        $clean = [];
        $flags = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            if ($this->looksLikeInstruction($segment)) {
                if (count($flags) < 10) {
                    $flags[] = ['snippet' => mb_substr($segment, 0, 160)];
                }
                continue;
            }
            $clean[] = $segment;
        }

        return ['clean' => implode("\n", $clean), 'flags' => $flags];
    }

    private function looksLikeInstruction(string $segment): bool
    {
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $segment)) {
                return true;
            }
        }
        return false;
    }
}
