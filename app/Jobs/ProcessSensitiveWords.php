<?php

namespace App\Jobs;

use App\Models\Article;
use App\Services\SensitiveWordService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSensitiveWords implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public Article $article,
    ) {}

    public function handle(SensitiveWordService $service): void
    {
        $text = $this->article->title . ' ' . $this->article->content;
        $hits = $service->detect($text);

        $this->article->update([
            'sensitive_words_hit' => $hits,
        ]);

        // 持久化命中记录
        if (!empty($hits)) {
            $service->detectAndLog($this->article->id, $text);
        }

        Log::channel('audit')->info('敏感词检测完成', [
            'article_id' => $this->article->id,
            'hit_count' => count($hits),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('audit')->error('敏感词检测失败', [
            'article_id' => $this->article->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
