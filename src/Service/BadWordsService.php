<?php

namespace App\Service;

class BadWordsService
{
    /**
     * Keep this list short and explicit. Extend as needed.
     * ASCII-only to keep matching stable.
     *
     * @var string[]
     */
    private array $badWords = [
        'fuck',
        'shit',
        'bitch',
        'asshole',
        'connard',
        'con',
        'pute',
        'salope',
        'encule',
        'merde',
    ];

    /**
     * @return array{has_bad_words: bool, matched: string[]}
     */
    public function analyze(string $text): array
    {
        $normalized = $this->normalize($text);
        $matched = [];

        foreach ($this->badWords as $word) {
            $w = $this->normalize($word);
            if ($w === '') {
                continue;
            }
            if (preg_match('/\b' . preg_quote($w, '/') . '\b/i', $normalized) === 1) {
                $matched[] = $word;
            }
        }

        $matched = array_values(array_unique($matched));

        return [
            'has_bad_words' => $matched !== [],
            'matched' => $matched,
        ];
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, [
            'a' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'e' => 'e', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'i' => 'i', 'î' => 'i', 'ï' => 'i',
            'o' => 'o', 'ô' => 'o', 'ö' => 'o',
            'u' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'c' => 'c', 'ç' => 'c',
            "'" => ' ', '-' => ' ',
        ]);

        return preg_replace('/\s+/', ' ', $text) ?? $text;
    }
}

