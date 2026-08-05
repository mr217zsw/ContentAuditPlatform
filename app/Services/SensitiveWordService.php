<?php

namespace App\Services;

use App\Models\SensitiveWord;
use App\Models\SensitiveWordHit;

class SensitiveWordService
{
    /**
     * 检测文本中的敏感词
     *
     * @param string $text
     * @return array [{word, position, level}]
     */
    public function detect(string $text): array
    {
        $activeWords = SensitiveWord::active()->get();
        $hits = [];

        foreach ($activeWords as $word) {
            $position = 0;
            $searchText = $text;
            while (($pos = mb_stripos($searchText, $word->word, 0, 'UTF-8')) !== false) {
                $hits[] = [
                    'word' => $word->word,
                    'level' => $word->level,
                    'position' => ($position + $pos),
                ];
                $position += $pos + mb_strlen($word->word, 'UTF-8');
                $searchText = mb_substr($searchText, $pos + mb_strlen($word->word, 'UTF-8'), null, 'UTF-8');
            }
        }

        return $hits;
    }

    /**
     * 检测并保存敏感词命中记录
     */
    public function detectAndLog(int $articleId, string $text): array
    {
        $hits = $this->detect($text);

        if (!empty($hits)) {
            $wordMap = SensitiveWord::active()
                ->whereIn('word', array_column($hits, 'word'))
                ->pluck('id', 'word');

            $records = [];
            foreach ($hits as $hit) {
                if (isset($wordMap[$hit['word']])) {
                    $records[] = [
                        'article_id' => $articleId,
                        'sensitive_word_id' => $wordMap[$hit['word']],
                        'word' => $hit['word'],
                        'position' => $hit['position'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (!empty($records)) {
                SensitiveWordHit::insert($records);
            }
        }

        return $hits;
    }

    /**
     * 检查是否包含禁止词（forbidden 级别）
     */
    public function hasForbiddenWords(string $text): bool
    {
        return SensitiveWord::active()
            ->where('level', SensitiveWord::LEVEL_FORBIDDEN)
            ->get()
            ->contains(fn($word) => mb_stripos($text, $word->word, 0, 'UTF-8') !== false);
    }

    /**
     * 获取文本最高风险等级
     */
    public function getMaxRiskLevel(string $text): ?string
    {
        $hits = $this->detect($text);
        if (empty($hits)) {
            return null;
        }

        $levels = ['forbidden', 'high', 'medium', 'low'];
        foreach ($levels as $level) {
            foreach ($hits as $hit) {
                if ($hit['level'] === $level) {
                    return $level;
                }
            }
        }

        return null;
    }
}
